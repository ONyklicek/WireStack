<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\Support\Screens;

/*
 * The way out, as the chrome renders it.
 *
 * Before the USER_MENU region existed, this form was fifteen lines every
 * application wrote into a layout slot by hand, against whichever packages it
 * happened to have installed. What it has to survive is the two ways it can be
 * reached: inside the shell's menu, and inside a layout of the application's own
 * that renders the region itself.
 */

/** Render the chrome entry through a request, which is where its questions are answerable. */
function amSignOut(): string
{
    Route::get('/am-probe', fn () => view('wire-module-auth::user-menu'));

    return test()->get('/am-probe')->getContent();
}

it('posts to the route Fortify registered', function () {
    // Fortify's own logout: the session invalidation and the token regeneration
    // stay where they are maintained.
    expect(Screens::canSignOut())->toBeTrue()
        ->and(amSignOut())
        ->toContain('action="'.route('logout').'"')
        ->toContain('data-testid="auth-sign-out"')
        // A POST with a token, never a link: a GET logout is one prefetch away
        // from signing people out by accident.
        ->toContain('method="POST"')
        ->toContain('name="_token"');
});

it('draws a row of the menu it sits in, with no shell to ask', function () {
    // `<x-wire::menu-item>` is wire-core's, not the shell's, and that is what
    // lets this package contribute a row without depending on the package that
    // draws the menu. An application rendering the region in a layout of its own
    // gets the same row rather than nothing — which would be the
    // installed-and-empty failure in a different costume.
    expect(Frame::hasShell())->toBeFalse()
        ->and(amSignOut())
        ->toContain('data-testid="auth-sign-out"')
        ->toContain('group flex w-full items-center gap-2.5 px-4 py-2');
});
