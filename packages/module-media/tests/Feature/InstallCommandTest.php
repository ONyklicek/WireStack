<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/*
 * `php artisan wire-module-media:install`.
 *
 * There is little to install — the module registers itself — so what the command
 * is really for is saying which shape this installation got, and what is still
 * missing for the screens to have anything in them.
 *
 * Published files are put back: everything lands in the skeleton inside
 * `vendor/`, and a file left behind (or deleted after Testbench cached its path)
 * turns into a failure in a completely different suite.
 */

function miRestoring(array $paths, Closure $body): void
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
function miCleaning(array $patterns, Closure $body): void
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
        ->toContain('wire-module-media:install');
});

it('says what this installation got', function () {
    miCleaning([database_path('migrations/*create_wire_media*.php')], fn () => miRestoring([config_path('wire-module-media.php')], function () {
        $this->artisan('wire-module-media:install')
            ->expectsOutputToContain('`media` module')
            ->assertSuccessful();
    }));
});

it('says what is still missing for it to show anything', function () {
    config()->set('wire-module-media.disk', 'public');

    miCleaning([database_path('migrations/*create_wire_media*.php')], fn () => miRestoring([config_path('wire-module-media.php')], function () {
        $this->artisan('wire-module-media:install')
            ->expectsOutputToContain('storage:link')
            ->assertSuccessful();
    }));
});
