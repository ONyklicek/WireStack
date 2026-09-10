<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\PlainUser;
use PragmaRX\Google2FA\Google2FA;

/*
 * Every shape a second factor can take, and every shape it can fail to.
 *
 * `TwoFactorTest` walks the ordinary path; `SecondFactorCodeTest` walks the
 * mailed variant. What is left is the awkward middle — the states where a person
 * *has* something set up but not quite, or has nothing and must not be asked
 * anyway. Those are the branches that lock people out when they are wrong, and
 * they are silent: an account that cannot answer a challenge it should never
 * have been shown looks exactly like an account with the wrong password.
 */

const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);
});

/** The code the authenticator app would be showing right now. */
function currentOtp(): string
{
    return (new Google2FA)->getCurrentOtp(TOTP_SECRET);
}

/** Somebody who finished setting the app up. */
function withConfirmedApp(array $recovery = ['aaaa-bbbb', 'cccc-dddd']): CodeUser
{
    return CodeWorld::user([
        'two_factor_secret' => encrypt(TOTP_SECRET),
        'two_factor_recovery_codes' => encrypt(json_encode($recovery)),
        'two_factor_confirmed_at' => now(),
    ]);
}

function passwordOf(CodeUser $user): array
{
    return ['email' => $user->email, 'password' => 'correct-horse'];
}

// ─── An authenticator app ──────────────────────────────────────────

it('opens the session on the code the app is showing', function () {
    // The half `TwoFactorTest` could not reach: a *correct* TOTP, generated the
    // way the app generates it. Without this the suite proves only that wrong
    // codes are refused, which a challenge that refuses everything also does.
    $user = withConfirmedApp();

    $this->post('/login', passwordOf($user));
    expect(Auth::check())->toBeFalse();

    $this->post('/two-factor-challenge', ['code' => currentOtp()])->assertRedirect();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->getKey());
});

it('does not accept the same app code as a password on the first screen', function () {
    $user = withConfirmedApp();

    $this->from('/login')->post('/login', ['email' => $user->email, 'password' => currentOtp()]);

    expect(Auth::check())->toBeFalse();
});

// ─── Set up, but not finished ──────────────────────────────────────

it('does not challenge somebody who never confirmed the app, where Fortify asks for confirmation', function () {
    // The lockout this branch exists to prevent: a secret was generated, the
    // person never proved the app works, and a challenge here is one they cannot
    // answer — with no way back to the setup screen, because they are not in.
    config()->set('fortify.features', [
        Features::twoFactorAuthentication(['confirm' => true]),
    ]);

    $user = CodeWorld::user([
        'two_factor_secret' => encrypt(TOTP_SECRET),
        'two_factor_confirmed_at' => null,
    ]);

    $this->post('/login', passwordOf($user));

    expect(Auth::check())->toBeTrue();
});

it('challenges an unconfirmed secret where Fortify does not ask for confirmation', function () {
    // The mirror image, and the reason the branch is a question rather than a
    // rule: with confirmation off, having the secret *is* having the factor.
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    $user = CodeWorld::user([
        'two_factor_secret' => encrypt(TOTP_SECRET),
        'two_factor_confirmed_at' => null,
    ]);

    $this->post('/login', passwordOf($user))->assertRedirect('/two-factor-challenge');

    expect(Auth::check())->toBeFalse();
});

// ─── Recovery codes ────────────────────────────────────────────────

it('spends the last recovery code and then has none left to spend', function () {
    $user = withConfirmedApp(['only-one']);

    $this->post('/login', passwordOf($user));
    $this->post('/two-factor-challenge', ['recovery_code' => 'only-one']);
    expect(Auth::check())->toBeTrue();

    // Signed out and back to the door, with the sheet now empty.
    $this->post('/logout');
    $this->post('/login', passwordOf($user));

    $this->from('/two-factor-challenge')
        ->post('/two-factor-challenge', ['recovery_code' => 'only-one'])
        ->assertSessionHasErrors('recovery_code');

    expect(Auth::check())->toBeFalse();
});

it('still takes the app once the recovery codes are gone', function () {
    // The way out of the state above: the sheet is spent, the app still works.
    $user = withConfirmedApp([]);

    $this->post('/login', passwordOf($user));
    $this->post('/two-factor-challenge', ['code' => currentOtp()]);

    expect(Auth::check())->toBeTrue();
});

// ─── Having no second factor at all ────────────────────────────────

it('never challenges a user model that cannot carry a second factor', function () {
    // The feature is on for the installation and this model does not use
    // Fortify's trait. Asking it for a code would be asking for something it has
    // no column to hold — an account locked out by a feature it never joined.
    config()->set('auth.providers.users.model', PlainUser::class);

    // Built here rather than through `CodeWorld::user()`, which returns a
    // `CodeUser`: the whole point of this account is that it is not one.
    $user = PlainUser::query()->create([
        'name' => 'Bob Plain',
        'email' => 'bob@example.com',
        'password' => Hash::make('correct-horse'),
        'email_verified_at' => now(),
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'correct-horse']);

    expect(Auth::check())->toBeTrue();
});

it('never challenges an account that simply has no secret', function () {
    $user = CodeWorld::user();

    $this->post('/login', passwordOf($user));

    expect(Auth::check())->toBeTrue();
});

// ─── With the feature switched off entirely ────────────────────────

it('lets an account that has a secret straight in once the feature is off', function () {
    // An installation that turned two-factor off must not keep challenging the
    // people who had already set it up — their rows still carry a secret.
    config()->set('fortify.features', []);

    $user = withConfirmedApp();

    $this->post('/login', passwordOf($user));

    expect(Auth::check())->toBeTrue();
});
