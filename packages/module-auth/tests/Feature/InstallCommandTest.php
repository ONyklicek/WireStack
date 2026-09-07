<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\View;
use NyonCode\WireModuleAuth\Install\LayoutScaffold;

/*
 * `php artisan wire-module-auth:install`.
 *
 * There is almost nothing to install — Fortify owns the routes and this package
 * answers its view callbacks from boot — so what the command is really for is
 * saying which of the shapes an application got, and naming the one thing a
 * login screen cannot fix on its own: a panel with no guard on its routes.
 */

/**
 * Run the installer without leaving anything it wrote behind.
 *
 * Both files, not just the config. The layout lands in the skeleton's
 * `resources/views/`, where the *next* test finds it — and finding it is not
 * harmless: `Frame` prefers the application's own layout, and that one calls
 * `@vite`, which has no manifest here. A leaked file turned every screen test in
 * this package red, in a run where the installer had passed.
 */
function amInstall(callable $assertions): void
{
    $written = [config_path('wire-module-auth.php'), base_path(LayoutScaffold::PATH)];
    $existed = array_values(array_filter($written, 'is_file'));

    try {
        $assertions(test()->artisan('wire-module-auth:install'));
    } finally {
        foreach (array_diff($written, $existed) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

it('is registered under the package short name', function () {
    expect(array_keys(app(Kernel::class)->all()))
        ->toContain('wire-module-auth:install');
});

it('says the screens are answered and names the line a missing shell needs', function () {
    // No shell in this suite, which is also the application this line is for:
    // the screens work, and they have nowhere to render until someone names a
    // layout. Silence there is a 500 on the login page.
    amInstall(fn ($command) => $command
        ->expectsOutputToContain('Fortify\'s screens are answered')
        ->expectsOutputToContain('No shell')
        ->assertSuccessful());
});

it('warns when the panel routes let anyone in', function () {
    // The finding this package exists beside: a login screen in front of an
    // unguarded panel is decoration, and every diagnostic short of visiting the
    // URL signed out reports success.
    config()->set('wire-panels.routes.enabled', true);
    config()->set('wire-panels.routes.middleware', ['web']);

    amInstall(fn ($command) => $command
        ->expectsOutputToContain('reachable signed out')
        ->assertSuccessful());
});

it('confirms the guard when the routes have one', function () {
    config()->set('wire-panels.routes.enabled', true);
    config()->set('wire-panels.routes.middleware', ['web', 'auth']);

    amInstall(fn ($command) => $command
        ->expectsOutputToContain('require a signed-in user')
        ->assertSuccessful());
});

it('says what it cannot see when the routes are written by hand', function () {
    // `Route::wireResources()` inside the application's own group is the
    // reference path, and nothing here can read which middleware that group
    // has. Guessing would be worse than saying so.
    config()->set('wire-panels.routes.enabled', false);

    amInstall(fn ($command) => $command
        ->expectsOutputToContain('routed by hand')
        ->assertSuccessful());
});

it('writes the application own layout where there is a shell frame to name', function () {
    // The gap this closes: the shell's frame takes the stylesheet from a `head`
    // slot — a package cannot know an application's Vite entry names — so
    // without this file the login page loads the framework's markup and none of
    // the application's styles, with no error anywhere on it.
    View::addNamespace('wire-admin', __DIR__.'/../Fixtures/views');

    amInstall(function ($command) {
        $command->expectsOutputToContain('Wrote '.LayoutScaffold::PATH)->assertSuccessful();
        $command->run();

        expect(base_path(LayoutScaffold::PATH))->toBeFile()
            ->and(file_get_contents(base_path(LayoutScaffold::PATH)))
            ->toContain('x-wire-admin::auth-layout')
            ->toContain('@vite');
    });
});

it('leaves a layout the application already edited alone', function () {
    View::addNamespace('wire-admin', __DIR__.'/../Fixtures/views');

    $path = base_path(LayoutScaffold::PATH);
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, 'mine');

    try {
        $this->artisan('wire-module-auth:install')
            ->expectsOutputToContain('already exists')
            ->assertSuccessful()
            ->run();

        expect(file_get_contents($path))->toBe('mine');
    } finally {
        unlink($path);

        if (is_file(config_path('wire-module-auth.php'))) {
            unlink(config_path('wire-module-auth.php'));
        }
    }
});
