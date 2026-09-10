<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Asking again, before something that deserves it.
 *
 * The one screen here that a signed-in person meets. It matters because the
 * thing it guards is behind `password.confirm` middleware, and the middleware is
 * satisfied by a timestamp in the session rather than by anything on the page —
 * so a screen that posted the right password to the wrong place would look like
 * it worked and guard nothing.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    // Something worth guarding, so the middleware has a door to stand in front
    // of rather than being asserted about in the abstract.
    Route::middleware(['web', 'auth', 'password.confirm'])
        ->get('/vault', fn (): string => 'the vault')
        ->name('vault');
});

// ─── The guard ─────────────────────────────────────────────────────

it('sends somebody at a guarded page to the screen first', function () {
    $this->actingAs(CodeWorld::user())
        ->get('/vault')
        ->assertRedirect(route('password.confirm'));
});

it('renders one field, and it is the one the controller reads', function () {
    $this->actingAs(CodeWorld::user())
        ->get('/user/confirm-password')
        ->assertOk()
        ->assertSee('name="password"', false);
});

// ─── Getting through ───────────────────────────────────────────────

it('opens the door for the rest of the window once the password is right', function () {
    $user = CodeWorld::user();

    $this->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'correct-horse'])
        ->assertRedirect();

    // The point of the whole screen: the guarded page is now reachable.
    $this->actingAs($user)->get('/vault')->assertOk()->assertSee('the vault');
});

// ─── Being turned away ─────────────────────────────────────────────

it('refuses the wrong password and leaves the door shut', function () {
    $user = CodeWorld::user();

    $this->from('/user/confirm-password')
        ->actingAs($user)
        ->post('/user/confirm-password', ['password' => 'not-it'])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)->get('/vault')->assertRedirect(route('password.confirm'));
});

it('refuses an empty submission', function () {
    $user = CodeWorld::user();

    $this->from('/user/confirm-password')
        ->actingAs($user)
        ->post('/user/confirm-password', [])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)->get('/vault')->assertRedirect(route('password.confirm'));
});

// ─── And it is only for people who are already in ──────────────────

it('is behind auth itself, like the thing it guards', function () {
    $this->get('/user/confirm-password')->assertRedirect();
});
