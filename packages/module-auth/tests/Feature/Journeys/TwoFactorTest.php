<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * The challenge between a correct password and a session.
 *
 * `SecondFactorCodeTest` walks the mailed-code second factor this package added.
 * This one walks Fortify's own: an authenticator app, or one of the recovery
 * codes saved when it was switched on.
 *
 * The screen is the awkward one in the set — a single `<form>` holding two
 * inputs, one of them hidden, and only ever one of them filled in. That shape is
 * why the fields are two schemas rather than one with a conditional, and why
 * neither is `required()`: a browser asked to validate a control it cannot focus
 * refuses the submit and reports it nowhere.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);
});

/** Somebody with an authenticator app and a sheet of recovery codes. */
function personWithSecondFactor(array $recovery = ['aaaa-bbbb', 'cccc-dddd']): CodeUser
{
    return CodeWorld::user([
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_recovery_codes' => encrypt(json_encode($recovery)),
        'two_factor_confirmed_at' => now(),
    ]);
}

// ─── Stopping at the door ──────────────────────────────────────────

it('does not sign the person in on the password alone', function () {
    personWithSecondFactor();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertRedirect('/two-factor-challenge');

    expect(Auth::check())->toBeFalse();
});

it('renders both inputs, so the person can switch to a recovery code', function () {
    // Both in the document at once: the screen swaps which one is shown in the
    // browser, and a challenge that rendered only one would strand anybody whose
    // phone is the thing they lost.
    personWithSecondFactor();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->get('/two-factor-challenge')
        ->assertOk()
        ->assertSee('name="code"', false)
        ->assertSee('name="recovery_code"', false);
});

// ─── Getting through ───────────────────────────────────────────────

it('signs the person in on a recovery code, and spends it', function () {
    $user = personWithSecondFactor(['aaaa-bbbb', 'cccc-dddd']);

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->post('/two-factor-challenge', ['recovery_code' => 'aaaa-bbbb'])
        ->assertRedirect();

    expect(Auth::check())->toBeTrue();

    // Spent, not merely accepted: a recovery code that survives its own use is a
    // password that never expires.
    $left = json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true);
    expect($left)->not->toContain('aaaa-bbbb')
        ->and($left)->toContain('cccc-dddd');
});

// ─── Being turned away ─────────────────────────────────────────────

it('refuses a recovery code that was never issued', function () {
    personWithSecondFactor();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->from('/two-factor-challenge')
        ->post('/two-factor-challenge', ['recovery_code' => 'never-issued'])
        ->assertSessionHasErrors('recovery_code');

    expect(Auth::check())->toBeFalse();
});

it('refuses a wrong authenticator code', function () {
    personWithSecondFactor();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->from('/two-factor-challenge')
        ->post('/two-factor-challenge', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(Auth::check())->toBeFalse();
});

it('refuses a challenge answered with neither', function () {
    personWithSecondFactor();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->from('/two-factor-challenge')->post('/two-factor-challenge', []);

    expect(Auth::check())->toBeFalse();
});

// ─── Somebody without one ──────────────────────────────────────────

it('lets a person with no second factor straight through', function () {
    // The branch that proves the challenge is conditional rather than universal:
    // the same screens, the same post, no detour.
    CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    expect(Auth::check())->toBeTrue();
});
