<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use NyonCode\WireAdmin\Install\BuildFrontend;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;

/*
 * The build without which the admin renders, correctly, with no styling at all.
 *
 * This package's installer points Tailwind at `vendor/nyoncode` and defines the
 * `primary` palette; until a build runs, both are instructions to nothing and
 * the shell has no width, no colour and no spacing — with no error anywhere.
 */
/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function bfConsole(array $answers = [], array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($answers, $said, $interactive) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(private array $answers, private array &$said, private bool $interactive) {}

        public function ask(string $question, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function secret(string $question): string
        {
            return array_shift($this->answers) ?? '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        /**
         * @param  array<int|string, string>  $options
         * @param  array<int, int|string>  $default
         * @return array<int, int|string>
         */
        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return $this->interactive;
        }
    };
}

/** Run something with `package.json` and `public/build` put back as they were. */
function bfRestoring(Closure $body): void
{
    $package = base_path('package.json');
    $had = is_file($package) ? (string) file_get_contents($package) : null;

    // Every manifest under `public/build`, and whether it was there at all.
    //
    // Deleting the directory was the obvious thing and it was wrong: the root
    // `Pest.php` points `public_path()` at one throwaway directory for the
    // *whole* monorepo run, so `public/build/manifest.json` there is the one
    // `@vite` reads for every suite. Removing it left every later test that
    // renders a layout answering 500 with a `ViteManifestNotFoundException`,
    // several packages away from anything to do with this file.
    $manifests = ['build/manifest.json', 'build/.vite/manifest.json'];
    $before = [];

    foreach ($manifests as $manifest) {
        $path = public_path($manifest);
        $before[$path] = is_file($path) ? (string) file_get_contents($path) : null;
    }

    try {
        $body();
    } finally {
        $had === null ? @unlink($package) : file_put_contents($package, $had);

        foreach ($before as $path => $contents) {
            if ($contents === null) {
                @unlink($path);

                continue;
            }

            File::ensureDirectoryExists(dirname($path));
            file_put_contents($path, $contents);
        }
    }
}

it('is contributed by this package', function () {
    expect(SetupRegistry::instance()->all())->toContain(BuildFrontend::class);
});

it('has nothing to do where there is nothing to build', function () {
    bfRestoring(function () {
        @unlink(base_path('package.json'));

        $step = new BuildFrontend;

        expect($step->state())->toBe(SetupState::Done)
            ->and($step->summary())->toBe('no package.json, so there is nothing to build')
            ->and($step->label())->toBe('Frontend build')
            ->and($step->sort())->toBe(900);
    });
});

it('is pending while nothing has been compiled', function () {
    bfRestoring(function () {
        file_put_contents(base_path('package.json'), '{}');

        $step = new BuildFrontend;

        expect($step->state())->toBe(SetupState::Pending)
            ->and($step->summary())->toContain('renders unstyled');
    });
});

it('reads the manifest rather than the directory around it', function () {
    // `public/build` survives a failed build and a cleared `assets/`; the
    // manifest is what `@vite` actually reads.
    bfRestoring(function () {
        file_put_contents(base_path('package.json'), '{}');
        File::ensureDirectoryExists(public_path('build'));

        expect((new BuildFrontend)->state())->toBe(SetupState::Pending);

        file_put_contents(public_path('build/manifest.json'), '{}');

        expect((new BuildFrontend)->state())->toBe(SetupState::Done)
            ->and((new BuildFrontend)->summary())->toBe('assets are built');
    });
});

it('asks for a rebuild when the stylesheet changed after the last build', function () {
    // `laravel new` builds as it creates the application, and this package's
    // installer edits `app.css` afterwards. A manifest was there, the step said
    // Done, and the admin rendered unstyled.
    bfRestoring(function () {
        $stylesheet = resource_path('css/app.css');
        $had = is_file($stylesheet) ? (string) file_get_contents($stylesheet) : null;

        try {
            file_put_contents(base_path('package.json'), '{}');
            File::ensureDirectoryExists(public_path('build'));
            file_put_contents(public_path('build/manifest.json'), '{}');
            File::ensureDirectoryExists(dirname($stylesheet));
            file_put_contents($stylesheet, '@import "tailwindcss";');
            touch(public_path('build/manifest.json'), time() - 60);
            touch($stylesheet, time());
            clearstatcache();

            expect((new BuildFrontend)->state())->toBe(SetupState::Pending)
                ->and((new BuildFrontend)->summary())->toBe('rebuild the assets — resources/css/app.css changed since they were built');

            touch(public_path('build/manifest.json'), time() + 60);
            clearstatcache();

            expect((new BuildFrontend)->state())->toBe(SetupState::Done);
        } finally {
            $had === null ? @unlink($stylesheet) : file_put_contents($stylesheet, $had);
        }
    });
});

it('asks for a rebuild when packages were installed after the last build', function () {
    // A module required later ships views with classes the last build never saw.
    bfRestoring(function () {
        $installed = base_path('vendor/composer/installed.json');
        $stamp = is_file($installed) ? (int) filemtime($installed) : null;

        try {
            file_put_contents(base_path('package.json'), '{}');
            File::ensureDirectoryExists(public_path('build'));
            file_put_contents(public_path('build/manifest.json'), '{}');
            File::ensureDirectoryExists(dirname($installed));

            if ($stamp === null) {
                file_put_contents($installed, '{}');
            }

            touch(public_path('build/manifest.json'), time() - 60);
            touch($installed, time());
            clearstatcache();

            expect((new BuildFrontend)->summary())->toBe('rebuild the assets — the installed packages changed since they were built');
        } finally {
            $stamp === null ? @unlink($installed) : touch($installed, $stamp);
        }
    });
});

it('finds a manifest Vite wrote under its own directory', function () {
    bfRestoring(function () {
        file_put_contents(base_path('package.json'), '{}');
        File::ensureDirectoryExists(public_path('build/.vite'));
        file_put_contents(public_path('build/.vite/manifest.json'), '{}');

        expect((new BuildFrontend)->state())->toBe(SetupState::Done);
    });
});

it('installs and builds, in that order, when the modules are missing', function () {
    Process::fake();
    $said = [];

    expect((new BuildFrontend)->apply(bfConsole([], $said)))->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->toContain('Assets built');

    Process::assertRan(fn ($process) => str_contains($process->command, 'npm install'));
    Process::assertRan(fn ($process) => str_contains($process->command, 'npm run build'));
});

it('declines rather than failing where there is no npm to run', function () {
    // A deployment or a container, not a broken install — the answer there is
    // the two commands rather than a failure.
    Process::fake(['*npm' => Process::result(exitCode: 1)]);
    $said = [];

    expect((new BuildFrontend)->apply(bfConsole([], $said)))->toBe(SetupOutcome::Skipped)
        ->and(implode("\n", $said))->toContain('npm install && npm run build');
});

it('fails when the install does not finish', function () {
    Process::fake([
        'npm install' => Process::result(exitCode: 1),
        '*' => Process::result(exitCode: 0),
    ]);
    $said = [];

    expect((new BuildFrontend)->apply(bfConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('`npm install` did not finish');
});

it('fails when the build does not finish', function () {
    Process::fake([
        'npm run build' => Process::result(exitCode: 1),
        '*' => Process::result(exitCode: 0),
    ]);
    $said = [];

    expect((new BuildFrontend)->apply(bfConsole([], $said)))->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('`npm run build` did not finish');
});

it('belongs to its own package, so unticking that package skips it', function () {
    // What the first half of the installer was told, the second half obeys.
    expect((new BuildFrontend)->package())->toBe('nyoncode/wire-admin');
});
