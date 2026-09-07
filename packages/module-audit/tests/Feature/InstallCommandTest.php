<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `php artisan wire-module-audit:install`.
 *
 * There is little to install — the module registers itself — so what the command
 * is really for is saying which shape this installation got, and what is still
 * missing for the screens to have anything in them.
 *
 * Published files are put back: everything lands in the skeleton inside
 * `vendor/`, and a file left behind (or deleted after Testbench cached its path)
 * turns into a failure in a completely different suite.
 */

function aiRestoring(array $paths, Closure $body): void
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
        ->toContain('wire-module-audit:install');
});

it('says what this installation got', function () {
    aiRestoring([config_path('wire-module-audit.php')], function () {
        $this->artisan('wire-module-audit:install')
            ->expectsOutputToContain('`audit` module')
            ->assertSuccessful();
    });
});

it('says what is still missing for it to show anything', function () {
    config()->set('wire-core.audit.enabled', false);

    aiRestoring([config_path('wire-module-audit.php')], function () {
        $this->artisan('wire-module-audit:install')
            ->expectsOutputToContain('nothing is being recorded')
            ->assertSuccessful();
    });
});

it('says the table core owns is not there yet', function () {
    // wire-core publishes the audit_logs migration on demand, so an application
    // can require this package, register it and have nowhere for the rows to be.
    // A screen that is empty for that reason reads as a broken package.
    aiRestoring([config_path('wire-module-audit.php')], function () {
        $this->artisan('wire-module-audit:install')
            ->expectsOutputToContain('No `audit_logs` table')
            ->assertSuccessful();
    });
});

it('says nothing about the table once it is there', function () {
    Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->string('event');
        $table->string('auditable_type');
        $table->timestamp('created_at')->useCurrent();
    });

    aiRestoring([config_path('wire-module-audit.php')], function () {
        $this->artisan('wire-module-audit:install')
            ->doesntExpectOutputToContain('No `audit_logs` table')
            ->assertSuccessful();
    });
});
