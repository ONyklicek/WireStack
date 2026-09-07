<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

/*
 * The frame for a page nobody is signed in to.
 *
 * It contains no authentication, and that is the decision under test as much as
 * the markup: Laravel owns login through Fortify and Breeze, and writing it here
 * would mean owning rate limiting, reset-token expiry, email verification, 2FA
 * and session fixation for no framework advantage. This is the card those
 * packages render inside.
 */

function alAuth(): string
{
    View::addLocation(__DIR__.'/../fixtures/views');
    Route::get('/al-auth', fn () => view('auth'));

    return test()->get('/al-auth')->getContent();
}

it('frames a sign-in card with the application brand', function () {
    $html = alAuth();

    expect($html)->toContain('data-testid="admin-auth"')
        ->and($html)->toContain('data-testid="admin-auth-brand"')
        ->and($html)->toContain('Acme')
        ->and($html)->toContain('data-testid="auth-form"')
        ->and($html)->toContain('<title>Sign in</title>');
});

it('draws no menu, no palette and no bell', function () {
    // None of it means anything before a user exists, and a palette over
    // resources they cannot reach is worse than no palette.
    $html = alAuth();

    expect($html)->not->toContain('data-testid="admin-sidebar"')
        ->and($html)->not->toContain('data-testid="global-search-trigger"')
        ->and($html)->not->toContain('data-testid="notification-bell"');
});

it('decides the theme before the page paints, like the admin does', function () {
    // A login screen is the first thing a user sees; a white flash before it
    // turns dark is the whole judgement of a dark mode.
    //
    // "Like the admin does" is now literal: the same partial, not a second copy
    // that read the same key with a two-state rule and so showed `system` as
    // light here and followed the operating system everywhere else.
    expect(alAuth())->toContain("const key = 'wire-admin.theme';")
        ->and(alAuth())->toContain("chosen !== 'light' && system.matches");
});

it('takes a footer slot for the links a login page needs', function () {
    expect(alAuth())->toContain('Forgot your password?');
});
