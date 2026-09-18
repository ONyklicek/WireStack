<?php

declare(strict_types=1);

use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\ResetUserPassword;

/*
 * Resetting a password from a code instead of a link.
 *
 * The flow that keeps the most of Laravel: the broker still mints the token,
 * still expires it, still refuses it twice — the code is a short-lived key to
 * that token and nothing else (ADR 0037 §2). So what these tests watch is the
 * seam: that the token really is what resets the password, and that the code
 * cannot be got round.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('auth.passwords.users.provider', 'users');
    config()->set('fortify.features', [Features::resetPasswords()]);

    CodeWorld::enable(['reset_password' => true]);

    // A transport that keeps what it was given, and a note of what was minted:
    // this code is created *inside* the mail Laravel's broker sends, so there is
    // no notification object to read it off — and a faked notification would
    // never build the mail at all, which would leave every test here passing
    // against a flow that does nothing.
    config()->set('mail.default', 'array');

    $this->codes = CodeWorld::recordCodes();

    // The one part of a reset Fortify leaves to the application, bound here as
    // an application's own `FortifyServiceProvider` binds it. Without it
    // Fortify's own reset screen is broken too, which is why the code flow does
    // not try to supply one.
    app()->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
});

/** The digits, as the person reading the mail has them. */
function mailedResetCode(): string
{
    return test()->codes->last->code;
}

it('mails a code instead of a link, and lands on the screen for it', function () {
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com'])
        ->assertRedirect(route('wire-auth.reset-code'));

    // A code, not a link: what a person retypes rather than clicks.
    expect(mailedResetCode())->toHaveLength(6);

    // The broker's token is untouched underneath: it was minted, mailed as a
    // code, and is what the reset will actually run on.
    expect(DB::table('password_reset_tokens')->where('email', 'ann@example.com')->exists())->toBeTrue();
});

it('sets the new password through Fortify own reset', function () {
    Event::fake([PasswordReset::class]);

    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $code = mailedResetCode();

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => $code,
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertRedirect();

    expect(Hash::check('a-brand-new-one', $user->fresh()->password))->toBeTrue();

    // Fortify's own action ran, so anything listening for a reset hears it.
    Event::assertDispatched(PasswordReset::class);
});

it('refuses a wrong code without touching the password', function () {
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => '000000',
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();
});

it('refuses the same code twice', function () {
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $code = mailedResetCode();

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => $code,
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ]);

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => $code,
        'password' => 'and-another-one',
        'password_confirmation' => 'and-another-one',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('a-brand-new-one', $user->fresh()->password))->toBeTrue();
});

it('keeps no readable credential in the codes table', function () {
    // The defect this pins: the code's payload used to carry the broker's token
    // verbatim. Laravel files that token hashed precisely so a copy of the table
    // is not a set of live credentials — and a second table holding the readable
    // original hands an attacker the reset without the six digits, past the
    // attempt counter, the resend window and the route throttle alike.
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $row = DB::table('wire_auth_one_time_codes')->where('purpose', 'reset-password')->first();
    $filed = (string) DB::table('password_reset_tokens')->where('email', 'ann@example.com')->value('token');

    $payload = json_decode((string) ($row->payload ?? '{}'), true);

    expect($payload)->toBe([])
        // Belt and braces: whatever a later flow decides to carry, nothing in
        // the row may verify against the token the broker filed.
        ->and(Hash::check((string) $row->code, $filed))->toBeFalse();
});

it('mints the token it resets with, rather than carrying one', function () {
    // The token is the broker's either way — what changed is when it exists in
    // readable form. It is asked for at the moment the code is redeemed, so the
    // reset still runs on Laravel's own expiry, single use and rules.
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $code = mailedResetCode();

    // Whatever `sendResetLink` filed is thrown away, exactly as a second request
    // for a link would throw it away. Nobody can have been holding it.
    DB::table('password_reset_tokens')->delete();

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => $code,
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-one', $user->fresh()->password))->toBeTrue();
});

it('refuses rather than fatals when the application swapped the broker out', function () {
    // `createToken()` is on Laravel's concrete PasswordBroker, not on the
    // contract `Password::broker()` is typed against — so an application that
    // bound a broker of its own would otherwise reach a fatal on a call that is
    // not there. It cannot mint, so the code cannot be redeemed, and the honest
    // report is that the code did not work.
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $code = mailedResetCode();

    // The facade caches the manager it already resolved for `/forgot-password`,
    // so binding alone would change nothing.
    Password::clearResolvedInstances();

    app()->instance('auth.password', new class
    {
        public function broker(?string $name = null): PasswordBrokerContract
        {
            return new class implements PasswordBrokerContract
            {
                public function sendResetLink(array $credentials, ?Closure $callback = null)
                {
                    return PasswordBrokerContract::RESET_LINK_SENT;
                }

                public function reset(array $credentials, Closure $callback)
                {
                    return PasswordBrokerContract::PASSWORD_RESET;
                }
            };
        }
    });

    $this->post('/reset-password-code', [
        'email' => 'ann@example.com',
        'code' => $code,
        'password' => 'a-brand-new-one',
        'password_confirmation' => 'a-brand-new-one',
    ])->assertSessionHasErrors('code');

    expect(Hash::check('a-brand-new-one', $user->fresh()->password))->toBeFalse();
});

it('fills the address in on the screen, without putting it in the URL', function () {
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->get('/reset-password-code')
        ->assertOk()
        ->assertSee('data-testid="auth-reset-code-form"', false)
        ->assertSee('value="ann@example.com"', false);
});

it('gives every field on it a name the browser will post', function () {
    // The screen renders four fields and posts them itself. A field bound by
    // `wire:model` would carry no name, so the form would submit nothing and
    // nothing on the page would say so — this is the assertion that notices.
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->get('/reset-password-code')
        ->assertSee('name="email"', false)
        ->assertSee('name="code"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false);
});
