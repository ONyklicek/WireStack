<?php

declare(strict_types=1);

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
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

it('fills the address in on the screen, without putting it in the URL', function () {
    CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->get('/reset-password-code')
        ->assertOk()
        ->assertSee('data-testid="auth-reset-code-form"', false)
        ->assertSee('value="ann@example.com"', false);
});
