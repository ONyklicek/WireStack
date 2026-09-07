<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/*
 * `php artisan wire-module-notifications:install`.
 *
 * There is little to install — the module registers itself — so what the command
 * is really for is saying which shape this installation got, and what is still
 * missing for the screens to have anything in them.
 *
 * Published files are put back: everything lands in the skeleton inside
 * `vendor/`, and a file left behind (or deleted after Testbench cached its path)
 * turns into a failure in a completely different suite.
 */

function niRestoring(array $paths, Closure $body): void
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
        ->toContain('wire-module-notifications:install');
});

it('says what this installation got', function () {
    niRestoring([config_path('wire-module-notifications.php')], function () {
        $this->artisan('wire-module-notifications:install')
            ->expectsOutputToContain('`notifications` module')
            ->assertSuccessful();
    });
});

it('says what is still missing for it to show anything', function () {
    config()->set('wire-core.notifications.default', 'session');

    niRestoring([config_path('wire-module-notifications.php')], function () {
        $this->artisan('wire-module-notifications:install')
            ->expectsOutputToContain('nothing is stored to show')
            ->assertSuccessful();
    });
});
