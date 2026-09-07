<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/*
 * `php artisan wire-module-settings:install`.
 *
 * There is little to install — the module registers itself — so what the command
 * is really for is saying which shape this installation got, and what is still
 * missing for the screens to have anything in them.
 *
 * Published files are put back: everything lands in the skeleton inside
 * `vendor/`, and a file left behind (or deleted after Testbench cached its path)
 * turns into a failure in a completely different suite.
 */

function siRestoring(array $paths, Closure $body): void
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

/**
 * Delete files a publish created, matched by shape rather than by name.
 *
 * A published migration is timestamped, so it has a different name on every run
 * and a path-keyed restore can never remove it. Left behind, the copies pile up
 * in whatever skeleton the tests run against until `migrate` refuses.
 *
 * @param  array<int, string>  $patterns
 */
function siCleaning(array $patterns, Closure $body): void
{
    $before = [];

    foreach ($patterns as $pattern) {
        $before[$pattern] = glob($pattern) ?: [];
    }

    try {
        $body();
    } finally {
        foreach ($before as $pattern => $existing) {
            foreach (glob($pattern) ?: [] as $path) {
                if (! in_array($path, $existing, true)) {
                    unlink($path);
                }
            }
        }
    }
}

it('is registered under the package short name', function () {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('wire-module-settings:install');
});

it('says what this installation got', function () {
    siCleaning([database_path('migrations/*create_wire_settings_table.php')], fn () => siRestoring([config_path('wire-module-settings.php')], function () {
        $this->artisan('wire-module-settings:install')
            ->expectsOutputToContain('`settings` module')
            ->assertSuccessful();
    }));
});

it('says what is still missing for it to show anything', function () {
    config()->set('wire-module-settings.groups', []);

    siCleaning([database_path('migrations/*create_wire_settings_table.php')], fn () => siRestoring([config_path('wire-module-settings.php')], function () {
        $this->artisan('wire-module-settings:install')
            ->expectsOutputToContain('SettingsGroup')
            ->assertSuccessful();
    }));
});

it('names the screen as unguarded while nothing guards it', function () {
    // The settings screen is where an application's behaviour is changed without
    // a deploy, and an unguarded route to it looks exactly like a guarded one
    // until somebody who should not have it finds the URL.
    config()->set('wire-module-settings.permission', null);

    siCleaning([database_path('migrations/*create_wire_settings_table.php')], fn () => siRestoring([config_path('wire-module-settings.php')], function () {
        $this->artisan('wire-module-settings:install')
            ->expectsOutputToContain('wire-module-settings.permission')
            ->assertSuccessful();
    }));
});

it('says nothing about the permission once one is named', function () {
    config()->set('wire-module-settings.permission', 'settings.manage');

    siCleaning([database_path('migrations/*create_wire_settings_table.php')], fn () => siRestoring([config_path('wire-module-settings.php')], function () {
        $this->artisan('wire-module-settings:install')
            ->doesntExpectOutputToContain('wire-module-settings.permission')
            ->assertSuccessful();
    }));
});

it('warns when settings would be cached in the database they came from', function () {
    config()->set('cache.default', 'database');
    config()->set('wire-module-settings.cache.store', null);

    siCleaning([database_path('migrations/*create_wire_settings_table.php')], fn () => siRestoring([config_path('wire-module-settings.php')], function () {
        $this->artisan('wire-module-settings:install')
            ->expectsOutputToContain('cache.store')
            ->assertSuccessful();
    }));
});
