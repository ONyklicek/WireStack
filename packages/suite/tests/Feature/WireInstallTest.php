<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\Wire\Install\Banner;
use NyonCode\Wire\Install\Catalogue;
use NyonCode\Wire\Install\Component;
use NyonCode\Wire\Install\Setup;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/*
 * `php artisan wire:install` — a clean Laravel to a working admin in one pass.
 *
 * It runs each installed package's own installer rather than reimplementing
 * them, and it **never runs composer**: offering a module that is not installed
 * is the point of the listing, and the answer is a line to paste rather than a
 * subprocess rewriting the autoloader of the application it is running inside.
 *
 * The installs here are real. Driving the whole thing through `--dry-run` was
 * comfortable and proved the wrong half: every bug this file now pins — a failed
 * installer reported as success, `--no-interaction` stopping at the first
 * command, a second run publishing a second copy of every migration — is
 * invisible until something is actually written.
 *
 * **So everything written is put back.** Publishing lands in `base_path()`,
 * which under Testbench is the skeleton inside `vendor/`, shared by every suite
 * in this monorepo. A test that ran an installer and walked away would leave
 * config, migrations and a `views/vendor/` directory shadowing the package's own
 * views for every later run. `wiRestoring()` is not tidiness.
 */

/**
 * Everything matching these globs, as it is right now.
 *
 * @param  array<int, string>  $globs
 * @return array<string, string|null> File contents, or null for a directory.
 */
function wiSnapshot(array $globs): array
{
    $seen = [];

    foreach ($globs as $glob) {
        foreach (glob($glob) ?: [] as $path) {
            $seen[$path] = is_dir($path) ? null : (string) file_get_contents($path);
        }
    }

    return $seen;
}

function wiDelete(string $path): void
{
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }

    if (! is_dir($path)) {
        return;
    }

    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($entries as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }

    @rmdir($path);
}

/**
 * Run something with these paths put back exactly as they were.
 *
 * Globs rather than paths, because a published migration's name is not knowable
 * in advance — the toolkit stamps it with the time it built the mapping.
 *
 * @param  array<int, string>  $globs
 */
function wiRestoring(array $globs, Closure $body): void
{
    $before = wiSnapshot($globs);

    try {
        $body();
    } finally {
        foreach ($globs as $glob) {
            foreach (glob($glob) ?: [] as $path) {
                if (! array_key_exists($path, $before)) {
                    wiDelete($path);

                    continue;
                }

                if ($before[$path] !== null) {
                    file_put_contents($path, $before[$path]);
                }
            }
        }
    }
}

/**
 * Everything `wire-sortable:install` writes.
 *
 * It is the part used for the real runs below because it publishes one of each
 * kind the installer has to reason about: a config file, a timeless migration,
 * and two directories.
 *
 * @return array<int, string>
 */
function wiSortablePaths(): array
{
    return [
        config_path('wire-sortable.php'),
        database_path('migrations/*create_reorderable_column_orders_table.php'),
        lang_path('vendor/wire-sortable'),
        resource_path('views/vendor/wire-sortable'),
    ];
}

/**
 * A catalogue of exactly what a test needs.
 *
 * The same seam an application uses to add its own installable parts, which is
 * what keeps a real run pointed at one package rather than at all twelve.
 */
function wiCatalogue(Component ...$components): void
{
    app()->instance(Catalogue::class, new class(...$components) extends Catalogue
    {
        /** @var array<int, Component> */
        private array $fake;

        public function __construct(Component ...$components)
        {
            $this->fake = $components;
        }

        protected function shipped(): array
        {
            return $this->fake;
        }
    });
}

function wiSortable(): Component
{
    return new Component(
        package: 'nyoncode/wire-sortable',
        label: 'Sortable',
        description: 'Drag-and-drop row and column reordering for tables.',
        marker: 'NyonCode\\WireSortable\\WireSortableServiceProvider',
        command: 'wire-sortable:install',
    );
}

// ---------------------------------------------------------------------------
// The catalogue
// ---------------------------------------------------------------------------

/*
 * Every test in this file decides for itself which setup steps exist.
 *
 * The providers in this suite's test application register the real ones —
 * running migrations, writing routes, building assets — and a test asserting on
 * the installer's output would otherwise be reading lines from steps it never
 * meant to involve, against an application they would really change.
 */
beforeEach(function () {
    SetupRegistry::instance()->flush();
});

it('is registered', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('wire:install');
});

it('knows every part of the stack, installed or not', function () {
    $packages = array_map(static fn (Component $c): string => $c->package, app(Catalogue::class)->components());

    expect($packages)->toContain('nyoncode/wire-core')
        ->toContain('nyoncode/wire-admin')
        ->toContain('nyoncode/wire-module-auth')
        ->toContain('nyoncode/wire-module-users')
        ->toContain('nyoncode/wire-module-media')
        ->toContain('nyoncode/wire-boost');
});

it('lists the parts it will not install as well as the ones it will', function () {
    // Two of them, for opposite reasons. `wire-panels` has nothing to publish;
    // `wire-boost` has an installer this command deliberately does not call,
    // because which AI agents someone uses is not a question to answer for them
    // from inside a task spinner.
    $byPackage = collect(app(Catalogue::class)->components())->keyBy('package');

    expect($byPackage['nyoncode/wire-panels']->command)->toBeNull()
        ->and($byPackage['nyoncode/wire-boost']->command)->toBeNull()
        ->and($byPackage['nyoncode/wire-core']->command)->toBe('wire-core:install');
});

it('answers "installed" by asking the autoloader, not the lock file', function () {
    // A composer name cannot be asked at runtime without reading installed.json;
    // a class that only exists when the package does answers the same question.
    $core = collect(app(Catalogue::class)->components())->firstWhere('package', 'nyoncode/wire-core');

    expect($core->installed())->toBeTrue()
        ->and((new Component(
            package: 'nyoncode/not-real',
            label: 'Nothing',
            description: 'A package this application does not have.',
            marker: 'NyonCode\\NotReal\\ServiceProvider',
        ))->installed())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Setup — what a part's installer still has to write
// ---------------------------------------------------------------------------

it('names what a part has left to publish', function () {
    wiRestoring(wiSortablePaths(), function () {
        // Cleared first. The skeleton is shared by every suite in the monorepo,
        // so "has this been published" is a question about what ran before this
        // test unless the test answers it itself; the restore puts it all back.
        foreach (wiSortablePaths() as $glob) {
            foreach (glob($glob) ?: [] as $path) {
                wiDelete($path);
            }
        }

        $pending = app(Setup::class)->pending(wiSortable(), app(Kernel::class)->all()['wire-sortable:install']);

        expect($pending)->toBeArray()
            ->and(implode("\n", $pending))->toContain('wire-sortable.php');
    });
});

it('counts a part as done once everything it publishes is there', function () {
    wiRestoring(wiSortablePaths(), function () {
        $this->artisan('wire-sortable:install')->assertSuccessful();

        expect(app(Setup::class)->pending(wiSortable(), app(Kernel::class)->all()['wire-sortable:install']))
            ->toBe([]);
    });
});

it('recognises a migration published under an earlier run\'s timestamp', function () {
    // The bug the whole skip exists for. A migration shipped without a date
    // prefix is stamped with the time the publish mapping was built, so the
    // destination path is one this process invented and no earlier run can have
    // used. Matching on it would republish, and `migrate` would then meet
    // `create_reorderable_column_orders_table` twice.
    wiRestoring(wiSortablePaths(), function () {
        @mkdir(database_path('migrations'), 0755, true);
        file_put_contents(
            database_path('migrations/2024_01_01_000000_create_reorderable_column_orders_table.php'),
            "<?php // published by an earlier run\n",
        );
        // Everything else it publishes, so migrations are the only open question.
        $this->artisan('vendor:publish', ['--tag' => 'wire-sortable::config'])->assertSuccessful();
        $this->artisan('vendor:publish', ['--tag' => 'wire-sortable::translations'])->assertSuccessful();
        $this->artisan('vendor:publish', ['--tag' => 'wire-sortable::views'])->assertSuccessful();

        expect(app(Setup::class)->pending(wiSortable(), app(Kernel::class)->all()['wire-sortable:install']))
            ->toBe([]);
    });
});

it('refuses to call a part done when it cannot see what the command writes', function () {
    $setup = app(Setup::class);
    $commands = app(Kernel::class)->all();

    // Not the toolkit's installer: an application may name any command as a
    // part's installer, and nothing here can read what that one would do.
    $foreign = new Component(
        package: 'nyoncode/wire-core',
        label: 'Foreign',
        description: 'An installer this cannot read.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    );

    // An installer whose whole job is an `afterInstallation` hook. "Every
    // destination exists" is vacuously true of no destinations, and settling it
    // on that would mean it never ran again.
    $packager = (new Packager)->name('Hookish')->hasShortName('hookish');
    $hookOnly = new InstallCommand($packager);

    // A tag that is declared but registered under no publish group this can
    // name — a package with its own tag separator, say.
    $unknownTags = (new InstallCommand((new Packager)->name('Nope')->hasShortName('nope')))->publishConfig();

    $hookish = new Component(
        package: 'nyoncode/wire-core',
        label: 'Hookish',
        description: 'Publishes nothing and does its work in a hook.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'hookish:install',
    );

    $nope = new Component(
        package: 'nyoncode/wire-core',
        label: 'Nope',
        description: 'Declares a tag nothing registered.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'nope:install',
    );

    expect($setup->pending($foreign, $commands['about']))->toBeNull()
        ->and($setup->pending($hookish, $hookOnly))->toBeNull()
        ->and($setup->pending($nope, $unknownTags))->toBeNull()
        ->and($setup->pending(new Component(
            package: 'nyoncode/wire-panels',
            label: 'Nothing to install',
            description: 'A part with no installer at all.',
            marker: 'NyonCode\\WirePanels\\WirePanelsServiceProvider',
        ), null))->toBeNull();
});

// ---------------------------------------------------------------------------
// Running it, for real
// ---------------------------------------------------------------------------

it('runs the installer a part names, and says what is left afterwards', function () {
    wiRestoring(wiSortablePaths(), function () {
        wiCatalogue(wiSortable());

        $this->artisan('wire:install --all')
            ->expectsOutputToContain('Sortable')
            ->expectsOutputToContain('Done.')
            ->expectsOutputToContain('php artisan migrate')
            ->assertSuccessful();

        expect(is_file(config_path('wire-sortable.php')))->toBeTrue()
            ->and(glob(database_path('migrations/*create_reorderable_column_orders_table.php')) ?: [])
            ->toHaveCount(1);
    });
});

it('leaves a part that is already set up alone, and publishes nothing twice', function () {
    // The headline. Re-running after adding a module is what the docs tell
    // people to do, and before this it meant a second copy of every timeless
    // migration — `migrate` then fails on a table that already exists.
    wiRestoring(wiSortablePaths(), function () {
        wiCatalogue(wiSortable());

        $this->artisan('wire:install --all')->assertSuccessful();

        // Something of the developer's own, in a file the installer published.
        file_put_contents(config_path('wire-sortable.php'), '<?php return ["mine" => true];');

        $this->artisan('wire:install --all')
            ->expectsOutputToContain('ALREADY DONE')
            ->assertSuccessful();

        expect(glob(database_path('migrations/*create_reorderable_column_orders_table.php')) ?: [])
            ->toHaveCount(1)
            ->and(file_get_contents(config_path('wire-sortable.php')))
            ->toBe('<?php return ["mine" => true];');
    });
});

it('sets up a part that is already done when told to force it', function () {
    wiRestoring(wiSortablePaths(), function () {
        wiCatalogue(wiSortable());

        $this->artisan('wire:install --all')->assertSuccessful();

        file_put_contents(config_path('wire-sortable.php'), '<?php return ["mine" => true];');

        $this->artisan('wire:install --all --force')
            ->doesntExpectOutputToContain('Already set up')
            ->expectsOutputToContain('Done.')
            ->assertSuccessful();

        // `--force` is passed down, so the published file is the package's again.
        expect(file_get_contents(config_path('wire-sortable.php')))
            ->not->toBe('<?php return ["mine" => true];');
    });
});

it('offers only the parts that still need something', function () {
    wiRestoring(wiSortablePaths(), function () {
        wiCatalogue(wiSortable(), new Component(
            package: 'nyoncode/wire-core',
            label: 'Core',
            description: 'The engine.',
            marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
            command: 'about',
        ));

        $this->artisan('wire-sortable:install')->assertSuccessful();

        // Sortable is done; the part whose installer cannot be read is not, and
        // is the only thing left to ask about.
        $this->artisan('wire:install --dry-run')
            ->expectsChoice('Which parts should be set up?', ['about'], ['about' => 'Core'])
            ->expectsOutputToContain('ALREADY DONE')
            ->assertSuccessful();
    });
});

// ---------------------------------------------------------------------------
// Failure, and the options that have to reach the installer
// ---------------------------------------------------------------------------

it('fails when an installer it ran failed', function () {
    // The exit code is the installer's answer. Dropping it was how a failed
    // publish came out as a green tick and a zero exit — the shape a CI run
    // cannot see through.
    Artisan::command('wi:fails', fn (): int => 1);

    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Breaks',
        description: 'Its installer returns a failure exit code.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'wi:fails',
    ));

    $this->artisan('wire:install --all')
        ->expectsOutputToContain('Did not finish: Breaks')
        ->assertFailed();
});

it('passes --no-interaction on to the installer it runs', function () {
    // Every one of these commands prompts before touching a production
    // application, and the prompt is drawn on this command's own output from
    // inside a running task spinner — the one place nobody can answer it.
    Artisan::command('wi:reports', function (): int {
        $this->getOutput()->writeln('interactive='.var_export($this->input->isInteractive(), true));

        return 0;
    });

    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Reports',
        description: 'Says whether it was asked to be interactive.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'wi:reports',
    ));

    $this->artisan('wire:install --all --no-interaction')
        ->expectsOutputToContain('interactive=false')
        ->assertSuccessful();
});

it('does not hand --force to an installer that has no such option', function () {
    // An application may name any command as a part's installer, and Symfony
    // aborts the whole run on an option that does not exist.
    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Optionless',
        description: 'A command with no --force.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    ));

    $this->artisan('wire:install --all --force')
        ->expectsOutputToContain('Done.')
        ->assertSuccessful();
});

// ---------------------------------------------------------------------------
// The rest of the surface
// ---------------------------------------------------------------------------

it('lists what is here and names the installer it would run', function () {
    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('Installing packages')
        ->expectsOutputToContain('Admin shell')
        // Not the whole command line: the two-column layout pads to the terminal
        // width and truncates the right side, which is narrower under a test
        // runner than in a terminal.
        ->expectsOutputToContain('WOULD RUN')
        ->assertSuccessful();
});

it('changes nothing in a dry run, and says so', function () {
    // Asserted on whether the installer ran at all, not on whether some file
    // exists under `base_path()`. That skeleton is shared by every suite in the
    // monorepo, so a test reading it is a test that passes or fails on what ran
    // before it — which is how this one used to fail on a clean change.
    $ran = false;

    Artisan::command('wi:never', function () use (&$ran): int {
        $ran = true;

        return 0;
    });

    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Would be installed',
        description: 'Its installer must not run.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'wi:never',
    ));

    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('Nothing was changed')
        ->assertSuccessful();

    expect($ran)->toBeFalse();
});

it('offers what is not installed by name, as a line to paste', function () {
    // Composer is not run from inside the application it is about to change: the
    // autoloader in use is the one composer would rewrite, and the failure modes
    // are the ones nobody can debug from a stack trace.
    wiCatalogue(new Component(
        package: 'nyoncode/not-real',
        label: 'Nothing',
        description: 'A package this application does not have.',
        marker: 'NyonCode\\NotReal\\ServiceProvider',
    ));

    // One substring, not two: each `expectsOutputToContain` is a Mockery
    // expectation over the same `doWrite` calls, and the first one to match a
    // line consumes it — so asserting the label and the description separately
    // would pass on the label alone.
    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('Available, not installed here')
        ->expectsOutputToContain('Nothing — A package this application does not have.')
        ->expectsOutputToContain('composer require nyoncode/not-real')
        ->assertSuccessful();
});

it('reports a part whose provider is not loaded instead of aborting', function () {
    // A class can be autoloadable while its provider is absent — a
    // `dont-discover` entry, a package registered in one environment only — and
    // calling a command that is not there would otherwise kill the whole run.
    // The users module is installed in this monorepo and its provider is not
    // registered in this suite's test application, which is exactly that case.
    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('NOT REGISTERED')
        ->assertSuccessful();
});

it('asks nothing when nothing installed here has an installer', function () {
    wiCatalogue(new Component(
        package: 'nyoncode/wire-panels',
        label: 'Resources & pages',
        description: 'A part with nothing to install.',
        marker: 'NyonCode\\WirePanels\\WirePanelsServiceProvider',
    ));

    $this->artisan('wire:install')
        ->expectsOutputToContain('Installing packages')
        ->expectsOutputToContain('NOTHING TO RUN')
        ->assertSuccessful();
});

it('offers the pending parts with every one already ticked', function () {
    // Everything is offered pre-selected: an installer whose default is
    // "nothing" makes the common case the tedious one. A multiselect says that
    // in one screen, where the `choice()` this replaced needed a synthetic
    // "All of them" row to mean it — and then had to hope nobody named a
    // package that.
    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Something installable',
        description: 'Stands in for a package with an installer.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    ));

    $this->artisan('wire:install')
        ->expectsChoice('Which parts should be set up?', ['about'], ['about' => 'Something installable'])
        ->expectsOutputToContain('Done.')
        ->assertSuccessful();
});

it('sets up nothing, and says so, when everything is unticked', function () {
    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Something installable',
        description: 'Stands in for a package with an installer.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    ));

    $this->artisan('wire:install')
        ->expectsChoice('Which parts should be set up?', [], ['about' => 'Something installable'])
        ->expectsOutputToContain('LEFT ALONE')
        ->assertSuccessful();
});

it('draws the mark where somebody is watching, and nowhere else', function () {
    // The brand mark is a wire routed like an S with a terminal at each open
    // end. A banner belongs in front of a person; in a deploy log it is noise
    // in the one place the output is read by a machine.
    expect(implode("\n", (new Banner)->lines()))
        ->toContain('╭')
        ->toContain('●')
        ->toContain('WireStack');

    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Something installable',
        description: 'Stands in for a package with an installer.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    ));

    $this->artisan('wire:install --all')
        ->expectsOutputToContain('WireStack')
        ->assertSuccessful();

    $this->artisan('wire:install --all --no-interaction')
        ->doesntExpectOutputToContain('WireStack')
        ->assertSuccessful();
});

it('sets up only the parts that were picked', function () {
    // Asserted on what the installers printed rather than on the labels: every
    // installed part appears in the "Found in this application" listing whether
    // it is chosen or not, so the label proves nothing about what ran.
    Artisan::command('wi:one', function (): int {
        $this->getOutput()->writeln('the first installer ran');

        return 0;
    });

    Artisan::command('wi:two', function (): int {
        $this->getOutput()->writeln('the second installer ran');

        return 0;
    });

    wiCatalogue(
        new Component(
            package: 'nyoncode/wire-core',
            label: 'Picked',
            description: 'Chosen from the list.',
            marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
            command: 'wi:one',
        ),
        new Component(
            package: 'nyoncode/wire-forms',
            label: 'Passed over',
            description: 'Left out of the list.',
            marker: 'NyonCode\\WireForms\\WireFormsServiceProvider',
            command: 'wi:two',
        ),
    );

    $this->artisan('wire:install')
        ->expectsChoice('Which parts should be set up?', ['wi:one'], [
            'wi:one' => 'Picked',
            'wi:two' => 'Passed over',
        ])
        ->expectsOutputToContain('the first installer ran')
        ->doesntExpectOutputToContain('the second installer ran')
        ->assertSuccessful();
});

// ---------------------------------------------------------------------------
// The second half: setting up the application, not just installing packages
// ---------------------------------------------------------------------------

/**
 * A step that answers however the test needs, and records what happened to it.
 *
 * @param  array<string, mixed>  $spy
 */
function wiStep(string $label, SetupState $state, SetupOutcome $outcome, int $sort, array &$spy): string
{
    $step = new class($label, $state, $outcome, $sort, $spy) implements SetupStep
    {
        /** @param array<string, mixed> $spy */
        public function __construct(
            private string $label,
            private SetupState $state,
            private SetupOutcome $outcome,
            private int $sort,
            private array &$spy,
        ) {}

        public function label(): string
        {
            return $this->label;
        }

        public function state(): SetupState
        {
            return $this->state;
        }

        public function summary(): string
        {
            return 'do the thing for '.$this->label;
        }

        public function apply(SetupConsole $console): SetupOutcome
        {
            $this->spy['applied'][] = $this->label;

            return $this->outcome;
        }

        public function sort(): int
        {
            return $this->sort;
        }
    };

    // Under a unique alias, not `$step::class`. Both calls to this helper build
    // the same anonymous class — one declaration site, one name — so registering
    // by class name would hand the registry one step twice and it would rightly
    // keep one. The container resolves an alias the same way, which is the path
    // the command actually takes.
    $alias = 'wi-step:'.$label;

    app()->instance($alias, $step);
    SetupRegistry::instance()->register($alias);

    return $alias;
}

it('offers a pending step, and applies it when told to', function () {
    $spy = ['applied' => []];
    wiStep('First administrator', SetupState::Pending, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsOutputToContain('Setting up this application')
        ->expectsOutputToContain('First administrator')
        ->expectsConfirmation('Set it up now?', 'yes')
        ->assertSuccessful();

    expect($spy['applied'])->toBe(['First administrator']);
});

it('leaves a pending step alone when told not to', function () {
    $spy = ['applied' => []];
    wiStep('First administrator', SetupState::Pending, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsConfirmation('Set it up now?', 'no')
        ->expectsOutputToContain('LEFT ALONE')
        ->assertSuccessful();

    expect($spy['applied'])->toBe([]);
});

it('reports a step that is already done, and never offers it', function () {
    $spy = ['applied' => []];
    wiStep('Database tables', SetupState::Done, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    // No confirmation is expected, which is the assertion: a question asked
    // here would fail the test rather than pass unnoticed.
    $this->artisan('wire:install --all')
        ->expectsOutputToContain('Database tables')
        ->assertSuccessful();

    expect($spy['applied'])->toBe([]);
});

it('reports a blocked step rather than offering one that cannot work', function () {
    // The state that keeps an installer from dying inside a task spinner with a
    // PDO exception: "run migrations" on an application with no database is not
    // a question worth asking.
    $spy = ['applied' => []];
    wiStep('Database tables', SetupState::Blocked, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsOutputToContain('do the thing for Database tables')
        ->assertSuccessful();

    expect($spy['applied'])->toBe([]);
});

it('names what a step would do in a dry run, and applies nothing', function () {
    $spy = ['applied' => []];
    wiStep('First administrator', SetupState::Pending, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('do the thing for First administrator')
        ->assertSuccessful();

    expect($spy['applied'])->toBe([]);
});

it('fails the command when a step fails', function () {
    $spy = ['applied' => []];
    wiStep('Database tables', SetupState::Pending, SetupOutcome::Failed, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsConfirmation('Set it up now?', 'yes')
        ->expectsOutputToContain('Did not finish: Database tables')
        ->assertFailed();
});

it('runs the steps in the order they have to run', function () {
    // Order is correctness, not presentation: creating the first administrator
    // before the tables exist is a step that cannot work.
    $spy = ['applied' => []];
    wiStep('First administrator', SetupState::Pending, SetupOutcome::Applied, 300, $spy);
    wiStep('Database tables', SetupState::Pending, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsConfirmation('Set it up now?', 'yes')
        ->expectsConfirmation('Set it up now?', 'yes')
        ->assertSuccessful();

    expect($spy['applied'])->toBe(['Database tables', 'First administrator']);
});

it('applies what it can unattended, without standing at a question', function () {
    // `--no-interaction` means "make no choices for me that need an answer" —
    // a step that can proceed on defaults does, and one that cannot declines
    // from the inside rather than blocking on a prompt nobody is watching.
    $spy = ['applied' => []];
    wiStep('Database tables', SetupState::Pending, SetupOutcome::Applied, 100, $spy);
    wiCatalogue();

    $this->artisan('wire:install --all --no-interaction')->assertSuccessful();

    expect($spy['applied'])->toBe(['Database tables']);
});

it('says nothing about setup when no package contributed a step', function () {
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->doesntExpectOutputToContain('Setting up this application')
        ->assertSuccessful();
});

it('carries a step\'s questions through to the command, and its answers back', function () {
    // The adapter is the only class in the stack that knows the answers come
    // from a person — so it is worth driving through a real command rather than
    // trusting that four Prompts calls line up with four expectations.
    $answers = [];

    $step = new class($answers) implements SetupStep
    {
        /** @param array<string, mixed> $answers */
        public function __construct(private array &$answers) {}

        public function label(): string
        {
            return 'Everything it can ask';
        }

        public function state(): SetupState
        {
            return SetupState::Pending;
        }

        public function summary(): string
        {
            return 'ask one of each';
        }

        public function apply(SetupConsole $console): SetupOutcome
        {
            $this->answers['interactive'] = $console->isInteractive();
            $this->answers['text'] = $console->ask('Name?', 'Admin');
            $this->answers['secret'] = $console->secret('Password?');
            $this->answers['choice'] = $console->choose('Which disk?', ['public' => 'public', 's3' => 's3'], 'public');
            $this->answers['confirm'] = $console->confirm('Sure?');

            $console->note('noted');
            $console->warn('warned');

            return SetupOutcome::Applied;
        }

        public function sort(): int
        {
            return 100;
        }
    };

    app()->instance('wi-step:asks', $step);
    SetupRegistry::instance()->register('wi-step:asks');
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsConfirmation('Set it up now?', 'yes')
        ->expectsQuestion('Name?', 'Ondřej')
        ->expectsQuestion('Password?', 'hunter2')
        ->expectsChoice('Which disk?', 's3', ['public' => 'public', 's3' => 's3'])
        ->expectsConfirmation('Sure?', 'yes')
        ->expectsOutputToContain('noted')
        ->expectsOutputToContain('warned')
        ->assertSuccessful();

    expect($answers)->toBe([
        'interactive' => true,
        'text' => 'Ondřej',
        'secret' => 'hunter2',
        'choice' => 's3',
        'confirm' => true,
    ]);
});

it('answers a step with defaults, and never a prompt, when unattended', function () {
    // Laravel Prompts would stand at a question with no tty until something
    // killed it. Every method short-circuits instead, so a step that cannot
    // proceed on defaults declines from the inside.
    $answers = [];

    $step = new class($answers) implements SetupStep
    {
        /** @param array<string, mixed> $answers */
        public function __construct(private array &$answers) {}

        public function label(): string
        {
            return 'Asks nobody';
        }

        public function state(): SetupState
        {
            return SetupState::Pending;
        }

        public function summary(): string
        {
            return 'ask one of each';
        }

        public function apply(SetupConsole $console): SetupOutcome
        {
            $this->answers = [
                'interactive' => $console->isInteractive(),
                'text' => $console->ask('Name?', 'Admin'),
                'secret' => $console->secret('Password?'),
                'choice' => $console->choose('Which disk?', ['public' => 'public'], 'public'),
                'confirm' => $console->confirm('Sure?', false),
            ];

            return SetupOutcome::Applied;
        }

        public function sort(): int
        {
            return 100;
        }
    };

    app()->instance('wi-step:silent', $step);
    SetupRegistry::instance()->register('wi-step:silent');
    wiCatalogue();

    $this->artisan('wire:install --all --no-interaction')->assertSuccessful();

    expect($answers)->toBe([
        'interactive' => false,
        'text' => 'Admin',
        'secret' => '',
        'choice' => 'public',
        'confirm' => false,
    ]);
});

it('survives a step that throws, and names it', function () {
    // A step is a package's code, and `Blocked` only covers what it could see
    // coming. Everything else arrives as an exception, and one escaping here
    // takes the whole installer with it and prints a stack trace over the
    // listing — which is the failure the three states exist to keep away.
    $step = new class implements SetupStep
    {
        public function label(): string
        {
            return 'Throws';
        }

        public function state(): SetupState
        {
            return SetupState::Pending;
        }

        public function summary(): string
        {
            return 'blow up';
        }

        public function apply(SetupConsole $console): SetupOutcome
        {
            throw new RuntimeException('table "audit_logs" already exists');
        }

        public function sort(): int
        {
            return 100;
        }
    };

    app()->instance('wi-step:throws', $step);
    SetupRegistry::instance()->register('wi-step:throws');
    wiCatalogue();

    $this->artisan('wire:install --all')
        ->expectsConfirmation('Set it up now?', 'yes')
        ->expectsOutputToContain('table "audit_logs" already exists')
        ->expectsOutputToContain('Did not finish: Throws')
        ->assertFailed();
});

it('hands over everything the installer said when it failed', function () {
    // The installer's own output is buffered so it cannot flood the listing,
    // which leaves this as the thing to hold: a buffered failure nobody can read
    // is worse than the noise it was hiding.
    //
    // That the *successful* case is quiet cannot be asserted from here —
    // `PendingCommand` binds `OutputStyle` into the container, so every nested
    // command writes to the parent's mocked output whatever buffer it was given.
    // It is checked by running the command.
    Artisan::command('wi:loud', function (): int {
        $this->getOutput()->writeln('Failed to publish config');

        return 1;
    });

    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Loud',
        description: 'An installer that fails with something to say.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'wi:loud',
    ));

    $this->artisan('wire:install --all')
        ->expectsOutputToContain('Failed to publish config')
        ->assertFailed();
});
