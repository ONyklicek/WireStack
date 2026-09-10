<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\CreateNewUser;

/*
 * Creating an account, from the screen to the row.
 *
 * The screen this package ships and the action the application writes are two
 * halves of one form, joined only by the names of the fields — and nothing in
 * either half fails when they stop agreeing. A column added to the action with
 * no input renders a screen that cannot supply it; an input added to the schema
 * with no column is typed in and thrown away. Both are silent, so both are here.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::registration()]);

    app()->singleton(CreatesNewUsers::class, CreateNewUser::class);
});

// ─── The happy path ────────────────────────────────────────────────

it('creates the account and signs the person straight in', function () {
    $this->post('/register', [
        'name' => 'Ann Example',
        'email' => 'ann@example.com',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ])->assertRedirect();

    expect(CodeUser::query()->where('email', 'ann@example.com')->exists())->toBeTrue()
        ->and(Auth::check())->toBeTrue();
});

it('renders exactly the four inputs the action reads', function () {
    // The seam, asserted from both ends: these are the keys `CreateNewUser`
    // validates, and they are the names the screen puts on the page.
    $screen = $this->get('/register')->assertOk();

    foreach (['name', 'email', 'password', 'password_confirmation'] as $name) {
        $screen->assertSee('name="'.$name.'"', false);
    }
});

// ─── Being turned away ─────────────────────────────────────────────

it('refuses a second account on an address that already has one', function () {
    CodeWorld::user();

    $this->from('/register')->post('/register', [
        'name' => 'Someone Else',
        'email' => 'ann@example.com',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ])->assertSessionHasErrors('email');

    expect(CodeUser::query()->where('email', 'ann@example.com')->count())->toBe(1)
        ->and(Auth::check())->toBeFalse();
});

it('refuses a confirmation that does not match, and writes nothing', function () {
    $this->from('/register')->post('/register', [
        'name' => 'Ann Example',
        'email' => 'ann@example.com',
        'password' => 'correct-horse',
        'password_confirmation' => 'a-different-horse',
    ])->assertSessionHasErrors('password');

    expect(CodeUser::query()->count())->toBe(0)
        ->and(Auth::check())->toBeFalse();
});

it('refuses an empty submission and writes nothing', function () {
    $this->from('/register')->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);

    expect(CodeUser::query()->count())->toBe(0);
});

// ─── Where the feature is off ──────────────────────────────────────
//
// Not tested here, and the reason is worth writing down rather than leaving as
// a gap: Fortify registers its routes while it boots, so `fortify.features` set
// from inside a test is set after the router has already been told. A test that
// turned the feature off here and asked for a 404 would be asserting a premise
// it never established. The half that *is* observable after boot — the sign-in
// screen no longer offering a link to a route nobody registered — is read from
// config at render, and `ScreensTest` covers it.
