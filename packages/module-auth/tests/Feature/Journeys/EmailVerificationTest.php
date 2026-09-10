<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Confirming an address from the link in the mail.
 *
 * `VerifyEmailCodeTest` walks the code variant. This is the signed-link one, and
 * the screen behind it is the odd member of the set: it has no fields at all,
 * only two buttons and a way out. What is worth pinning is therefore not a
 * schema but the wiring — that the buttons post where they claim to, and that
 * the link Laravel signed is the only thing that verifies.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::emailVerification()]);
});

/** The signed URL Laravel would have mailed. */
function verificationLink(CodeUser $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1($user->getEmailForVerification()),
    ]);
}

// ─── Following the link ────────────────────────────────────────────

it('marks the address confirmed when the signed link is followed', function () {
    Event::fake();
    $user = CodeWorld::user(['email_verified_at' => null]);

    expect($user->hasVerifiedEmail())->toBeFalse();

    $this->actingAs($user)->get(verificationLink($user))->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

it('refuses a link whose hash was tampered with', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $tampered = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)->get($tampered)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('refuses a link that was never signed', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get('/email/verify/'.$user->getKey().'/'.sha1($user->getEmailForVerification()))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

// ─── The screen in between ─────────────────────────────────────────

it('sends another one when the button on the screen is pressed', function () {
    Notification::fake();
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)
        ->post('/email/verification-notification')
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('offers the way out that the screen draws, and it ends the session', function () {
    // This screen is reached by somebody who is signed in but cannot go
    // anywhere, so the sign-out on it is not decoration — it is the only exit.
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get('/email/verify')
        ->assertOk()
        ->assertSee('data-testid="auth-sign-out"', false);

    $this->actingAs($user)->post('/logout');

    expect(auth()->check())->toBeFalse();
});
