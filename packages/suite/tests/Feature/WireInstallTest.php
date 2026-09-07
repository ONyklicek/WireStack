<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use NyonCode\Wire\Install\Catalogue;
use NyonCode\Wire\Install\Component;

/*
 * `php artisan wire:install` — a clean Laravel to a working admin in one pass.
 *
 * It runs each installed package's own installer rather than reimplementing
 * them, and it **never runs composer**: offering a module that is not installed
 * is the point of the listing, and the answer is a line to paste rather than a
 * subprocess rewriting the autoloader of the application it is running inside.
 *
 * Driven through `--dry-run` here, and that is not timidity: everything the real
 * run writes lands in `base_path()`, which under Testbench is the skeleton inside
 * `vendor/`. Publishing there and deleting it afterwards left Testbench holding a
 * cached path to a file that no longer existed, and the next suite to boot died
 * with "Path must not be empty" — a failure that appeared only in the full run
 * and pointed nowhere near this file. Each package's own install test covers the
 * publishing; this one covers the orchestration.
 */

it('is registered', function () {
    expect(array_keys(app(Kernel::class)->all()))->toContain('wire:install');
});

it('knows every part of the stack, installed or not', function () {
    $packages = array_map(static fn (Component $c): string => $c->package, app(Catalogue::class)->components());

    expect($packages)->toContain('nyoncode/wire-core')
        ->toContain('nyoncode/wire-admin')
        ->toContain('nyoncode/wire-module-users')
        ->toContain('nyoncode/wire-module-media');
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

it('lists what is here and names the installer it would run', function () {
    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('Found in this application')
        ->expectsOutputToContain('Admin shell')
        // Not the whole command line: the two-column layout pads to the terminal
        // width and truncates the right side, which is narrower under a test
        // runner than in a terminal.
        ->expectsOutputToContain('would run')
        ->assertSuccessful();
});

it('changes nothing in a dry run, and says so', function () {
    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('Nothing was changed')
        ->assertSuccessful();

    expect(is_file(config_path('wire-core.php')))->toBeFalse()
        ->and(is_file(base_path('resources/views/components/layouts/admin.blade.php')))->toBeFalse();
});

it('offers what is not installed as a line to paste, never as a subprocess', function () {
    // Composer is not run from inside the application it is about to change: the
    // autoloader in use is the one composer would rewrite, and the failure modes
    // are the ones nobody can debug from a stack trace.
    //
    // Everything in this monorepo is installed, so the absent part is bound in —
    // which is the same seam an application uses to add its own.
    app()->instance(Catalogue::class, new class extends Catalogue
    {
        protected function shipped(): array
        {
            return [
                new Component(
                    package: 'nyoncode/not-real',
                    label: 'Nothing',
                    description: 'A package this application does not have.',
                    marker: 'NyonCode\\NotReal\\ServiceProvider',
                ),
            ];
        }
    });

    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('Available, not installed here')
        ->expectsOutputToContain('composer require nyoncode/not-real')
        ->expectsOutputToContain('A package this application does not have.')
        ->assertSuccessful();
});

it('asks which parts to set up when it can ask', function () {
    // Everything is offered pre-selected: an installer whose default is
    // "nothing" makes the common case the tedious one.
    $this->artisan('wire:install --dry-run')
        ->expectsChoice('Which parts should be set up?', ['Admin shell'], [
            'Core',
            'Forms',
            'Tables',
            'Sortable',
            'Admin shell',
            'Sign in',
            'Users',
            'Settings',
            'Audit log',
            'Notifications',
            'Media library',
            'All of them',
        ])
        ->expectsOutputToContain('Admin shell')
        ->assertSuccessful();
});

it('reports a part whose provider is not loaded instead of aborting', function () {
    // A class can be autoloadable while its provider is absent — a
    // `dont-discover` entry, a package registered in one environment only — and
    // calling a command that is not there would otherwise kill the whole run.
    // The users module is installed in this monorepo and its provider is not
    // registered in this suite's test application, which is exactly that case.
    $this->artisan('wire:install --all --dry-run')
        ->expectsOutputToContain('is not registered')
        ->assertSuccessful();
});

/**
 * A catalogue of exactly what a test needs.
 *
 * The same seam an application uses to add its own installable parts, which is
 * what makes the real (non-dry) run drivable without publishing anything into
 * whatever skeleton the tests are running against.
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

it('runs the installer a part names, and says what is left afterwards', function () {
    // A part whose "installer" is an ordinary registered command: the run is
    // real — task, Artisan::call, the closing advice — and nothing is published.
    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Something installable',
        description: 'Stands in for a package with an installer.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    ));

    $this->artisan('wire:install --all')
        ->expectsOutputToContain('Something installable')
        ->expectsOutputToContain('Done. What is left is yours')
        ->expectsOutputToContain('php artisan migrate')
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
        ->expectsOutputToContain('Found in this application')
        ->assertSuccessful();
});

it('takes "all of them" as the answer it offers first', function () {
    wiCatalogue(new Component(
        package: 'nyoncode/wire-core',
        label: 'Something installable',
        description: 'Stands in for a package with an installer.',
        marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
        command: 'about',
    ));

    $this->artisan('wire:install')
        ->expectsChoice('Which parts should be set up?', ['All of them'], [
            'Something installable',
            'All of them',
        ])
        ->expectsOutputToContain('Done. What is left is yours')
        ->assertSuccessful();
});
