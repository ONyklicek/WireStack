<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\ResetUserPassword;

/*
 * Resetting a password from the link in the mail, end to end.
 *
 * `ResetPasswordCodeTest` walks the *code* variant this package added. This one
 * walks the variant Laravel already had, because that is the one these screens
 * are answering for — and the two screens involved are the awkward pair: the
 * second one has to carry a token it never asked the person for, and the whole
 * reset is worthless if it arrives empty.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('auth.passwords.users.provider', 'users');
    config()->set('fortify.features', [Features::resetPasswords()]);

    app()->singleton(ResetsUserPasswords::class, ResetUserPassword::class);
});

/** The token Laravel's broker minted, read off the notification it sent. */
function mailedResetToken(CodeUser $user): string
{
    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    return (string) $token;
}

// ─── Asking for the link ───────────────────────────────────────────

it('sends a link to an address that has an account', function () {
    Notification::fake();
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com'])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('answers an address with no account the same way, and sends nothing', function () {
    // The enumeration guard on this screen: the reply cannot depend on whether
    // the address exists, or the form becomes a way to ask.
    Notification::fake();

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'nobody@example.com']);

    Notification::assertNothingSent();
});

// ─── Following it ──────────────────────────────────────────────────

it('changes the password, and the new one is the one that works', function () {
    Notification::fake();
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->post('/reset-password', [
        'token' => mailedResetToken($user),
        'email' => 'ann@example.com',
        'password' => 'a-brand-new-horse',
        'password_confirmation' => 'a-brand-new-horse',
    ])->assertSessionHas('status');

    expect(Hash::check('a-brand-new-horse', $user->fresh()->password))->toBeTrue();

    // The half that proves it end to end rather than in the column: the old
    // password stops working and the new one starts.
    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);
    expect(Auth::check())->toBeFalse();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'a-brand-new-horse']);
    expect(Auth::check())->toBeTrue();
});

it('carries the token on the screen, because the browser has nowhere else to keep it', function () {
    // The reset screen is the one place a native form has to hold a value the
    // person never typed. If the hidden input stopped rendering, the screen
    // would look perfect and every reset would fail on a missing token.
    CodeWorld::user();

    $this->get('/reset-password/the-token?email=ann@example.com')
        ->assertOk()
        ->assertSee('name="token" value="the-token"', false)
        ->assertSee('value="ann@example.com"', false);
});

// ─── Being turned away ─────────────────────────────────────────────

it('refuses a token that was never minted, and leaves the password alone', function () {
    $user = CodeWorld::user();

    $this->from('/reset-password/made-up')->post('/reset-password', [
        'token' => 'made-up',
        'email' => 'ann@example.com',
        'password' => 'a-brand-new-horse',
        'password_confirmation' => 'a-brand-new-horse',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();
});

it('refuses a mismatched confirmation, and leaves the password alone', function () {
    Notification::fake();
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);

    $this->from('/reset-password')->post('/reset-password', [
        'token' => mailedResetToken($user),
        'email' => 'ann@example.com',
        'password' => 'a-brand-new-horse',
        'password_confirmation' => 'a-different-horse',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();
});

it('will not spend the same token twice', function () {
    Notification::fake();
    $user = CodeWorld::user();

    $this->post('/forgot-password', ['email' => 'ann@example.com']);
    $token = mailedResetToken($user);

    $payload = [
        'token' => $token,
        'email' => 'ann@example.com',
        'password' => 'a-brand-new-horse',
        'password_confirmation' => 'a-brand-new-horse',
    ];

    $this->post('/reset-password', $payload)->assertSessionHas('status');

    $this->from('/reset-password')
        ->post('/reset-password', $payload)
        ->assertSessionHasErrors('email');
});
