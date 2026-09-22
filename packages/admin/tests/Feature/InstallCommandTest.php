<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/*
 * `php artisan wire-admin:install` — the moment an application asks for the
 * shell, as opposed to merely having the package on disk.
 *
 * That distinction is ADR 0028's: no provider sets `livewire.component_layout`,
 * so installing adopts nothing. Running this command is the ask, and it does the
 * three things composer cannot — write the application's own layout view,
 * register the provider that names it, and point Tailwind at views that live
 * under a gitignored `vendor/`.
 *
 * **Everything is restored afterwards.** The installer writes to `base_path()`,
 * which under Testbench is the skeleton inside `vendor/`, so a test that ran it
 * and walked away would leave a provider registered in every later run of the
 * whole monorepo suite. Found the hard way.
 */

/**
 * Run something with these paths put back exactly as they were, whether they
 * existed or not.
 *
 * @param  array<int, string>  $paths
 */
function icRestoring(array $paths, Closure $body): void
{
    $before = [];

    foreach ($paths as $path) {
        $before[$path] = is_file($path) ? (string) file_get_contents($path) : null;
    }

    try {
        $body();
    } finally {
        foreach ($before as $path => $contents) {
            if ($contents === null) {
                if (is_file($path)) {
                    unlink($path);
                }

                continue;
            }

            file_put_contents($path, $contents);
        }
    }
}

it('is registered under the package short name', function () {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('wire-admin:install');
});

it('publishes the provider template the application owns', function () {
    // A .stub in the package, a .php in the app: the template names App\ classes,
    // so this package's own suite, PHPStan and coverage must never load it.
    icRestoring([app_path('Providers/WireAdminServiceProvider.php')], function () {
        $this->artisan('vendor:publish', ['--tag' => 'wire-admin::providers'])->assertSuccessful();

        expect(file_get_contents(app_path('Providers/WireAdminServiceProvider.php')))
            ->toContain('livewire.component_layout')
            ->toContain('components.layouts.admin');
    });
});

it('writes the layout and registers the provider that names it', function () {
    icRestoring([
        base_path('resources/views/components/layouts/admin.blade.php'),
        base_path('bootstrap/providers.php'),
        app_path('Providers/WireAdminServiceProvider.php'),
    ], function () {
        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('Wrote resources/views/components/layouts/admin.blade.php')
            ->expectsOutputToContain('Registered App\Providers\WireAdminServiceProvider')
            ->assertSuccessful();

        expect(file_get_contents(base_path('resources/views/components/layouts/admin.blade.php')))
            ->toContain('<x-wire-admin::layout')
            ->and(file_get_contents(base_path('bootstrap/providers.php')))
            ->toContain('App\Providers\WireAdminServiceProvider::class,');
    });
});

it('leaves a second run alone and says so', function () {
    // Idempotent, because an installer that eats the edits someone made after the
    // first run is worse than one that does nothing.
    icRestoring([
        base_path('resources/views/components/layouts/admin.blade.php'),
        base_path('bootstrap/providers.php'),
        app_path('Providers/WireAdminServiceProvider.php'),
    ], function () {
        $this->artisan('wire-admin:install')->assertSuccessful();

        file_put_contents(base_path('resources/views/components/layouts/admin.blade.php'), 'mine');

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('already exists')
            ->expectsOutputToContain('already registered')
            ->assertSuccessful();

        expect(file_get_contents(base_path('resources/views/components/layouts/admin.blade.php')))->toBe('mine');
    });
});

it('points Tailwind at the packages, once', function () {
    // The step nobody would think to test until they saw the shell render with
    // no width and no colour: Tailwind skips `vendor/` because .gitignore does,
    // so the classes in the packages' views are never compiled.
    icRestoring([
        base_path('resources/css/app.css'),
        base_path('resources/views/components/layouts/admin.blade.php'),
        base_path('bootstrap/providers.php'),
        app_path('Providers/WireAdminServiceProvider.php'),
    ], function () {
        @mkdir(base_path('resources/css'), 0755, true);
        file_put_contents(base_path('resources/css/app.css'), "@import \"tailwindcss\";\n");

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('Pointed Tailwind at the packages')
            ->assertSuccessful();

        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('@source "../../vendor/nyoncode";');

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('already scans vendor/nyoncode')
            ->assertSuccessful();

        expect(substr_count((string) file_get_contents(base_path('resources/css/app.css')), '@source'))->toBe(1);
    });
});

it('adds the forms plugin to package.json, once', function () {
    // The field views take their borders and padding from @tailwindcss/forms;
    // the stylesheet line is no use until the package is installed.
    icRestoring([
        base_path('package.json'),
        base_path('resources/views/components/layouts/admin.blade.php'),
        base_path('bootstrap/providers.php'),
        app_path('Providers/WireAdminServiceProvider.php'),
    ], function () {
        file_put_contents(base_path('package.json'), json_encode(['private' => true, 'devDependencies' => ['vite' => '^7.0']]));

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('Added @tailwindcss/forms to package.json')
            ->assertSuccessful();

        expect(json_decode((string) file_get_contents(base_path('package.json')), true)['devDependencies'])
            ->toHaveKey('@tailwindcss/forms');

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('@tailwindcss/forms is already in package.json')
            ->assertSuccessful();
    });
});

it('finishes with a warning when there is no provider list to edit', function () {
    // A Laravel 10 application. Everything else the command did still stands, so
    // it prints the line to add rather than failing the run — the one
    // AdminInstallException the command catches on purpose.
    icRestoring([
        base_path('resources/views/components/layouts/admin.blade.php'),
        base_path('bootstrap/providers.php'),
        app_path('Providers/WireAdminServiceProvider.php'),
    ], function () {
        unlink(base_path('bootstrap/providers.php'));

        // Only a short fragment is asserted here: `warn()` wraps a long message
        // at the terminal width, so the phrasing is pinned where it is produced
        // (InstallScaffoldTest) rather than through the console.
        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('does not exist')
            ->assertSuccessful();

        expect(is_file(base_path('resources/views/components/layouts/admin.blade.php')))->toBeTrue();
    });
});

it('writes the dashboard the admin opens on, and registers it in the published config', function () {
    // The step that makes signing in land somewhere. Both halves are asked on
    // every run and report separately, so this runs the command twice: once to
    // write and register the pair, once to be told it is already there.
    icRestoring([
        base_path('config/wire-core.php'),
        base_path('resources/views/components/layouts/admin.blade.php'),
        base_path('bootstrap/providers.php'),
        app_path('Providers/WireAdminServiceProvider.php'),
        app_path('Dashboards/OverviewDashboard.php'),
        app_path('Livewire/Dashboards/ShowOverview.php'),
    ], function () {
        // The workbench has a dashboard of its own, and Testbench copies it into
        // the skeleton — so this starts from the shape a clean application has.
        foreach ([app_path('Dashboards/OverviewDashboard.php'), app_path('Livewire/Dashboards/ShowOverview.php')] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        // The published config, with the `//` an empty list carries.
        file_put_contents(base_path('config/wire-core.php'), <<<'PHP'
        <?php

        return [
            'dashboards' => [
                //
            ],
        ];
        PHP);

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('Wrote app/Dashboards/OverviewDashboard.php')
            ->expectsOutputToContain('Registered App\Dashboards\OverviewDashboard')
            ->assertSuccessful();

        expect(file_get_contents(base_path('config/wire-core.php')))
            ->toContain('\App\Dashboards\OverviewDashboard::class,')
            ->and(file_get_contents(app_path('Livewire/Dashboards/ShowOverview.php')))
            ->toContain('OverviewDashboard::class');

        $this->artisan('wire-admin:install')
            ->expectsOutputToContain('app/Dashboards/OverviewDashboard.php already exists')
            ->expectsOutputToContain('App\Dashboards\OverviewDashboard is already registered')
            ->assertSuccessful();
    });
});
