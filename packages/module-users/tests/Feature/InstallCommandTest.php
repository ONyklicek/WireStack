<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

/*
 * `php artisan wire-module-users:install`.
 *
 * There is almost nothing to install — the module registers itself and runs over
 * the application's own table — so what the command is really for is telling the
 * developer which of the two shapes they got: with roles, or without.
 */

it('is registered under the package short name', function () {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('wire-module-users:install');
});

it('says the module is registered, and that roles are part of this installation', function () {
    $config = config_path('wire-module-users.php');
    $existed = is_file($config);

    try {
        $this->artisan('wire-module-users:install')
            ->expectsOutputToContain('users` module')
            ->expectsOutputToContain('Roles found')
            ->assertSuccessful();
    } finally {
        if (! $existed && is_file($config)) {
            unlink($config);
        }
    }
});

it('points at the package that adds roles when the application has none', function () {
    // The module works without roles; an installer that stayed silent about it
    // would leave someone looking for a screen that was never going to appear.
    config()->set('wire-module-users.roles', false);

    $config = config_path('wire-module-users.php');
    $existed = is_file($config);

    try {
        // A short fragment only: a long comment line wraps at the terminal
        // width, so the wording is pinned where it is produced.
        $this->artisan('wire-module-users:install')
            ->expectsOutputToContain('No roles')
            ->assertSuccessful();
    } finally {
        if (! $existed && is_file($config)) {
            unlink($config);
        }
    }
});
