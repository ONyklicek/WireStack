<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Confirming an address by typing a code.
 *
 * Beside Fortify's signed link rather than instead of it — so the two things
 * under test are that the code confirms the address the way Laravel confirms it
 * (`markEmailAsVerified()`, then `Verified`), and that the screen is reachable
 * only by the person it belongs to.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::emailVerification()]);

    CodeWorld::enable(['verify_email' => true]);

    Notification::fake();
});

it('offers the code from the screen Fortify already shows', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->get('/email/verify')
        ->assertOk()
        ->assertSee('data-testid="auth-verify-code-form"', false);
});

it('mails a code and shows the boxes', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send')
        ->assertRedirect(route('wire-auth.verify-email-code'));

    Notification::assertSentTo($user, OneTimeCodeNotification::class, function ($notification) {
        return $notification->code->purpose === CodePurpose::VerifyEmail;
    });

    $this->actingAs($user)->get('/email/verify/code')
        ->assertOk()
        ->assertSee('data-testid="auth-code-form"', false);
});

it('confirms the address the way Laravel confirms it', function () {
    Event::fake([Verified::class]);

    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send');

    $code = Notification::sent($user, OneTimeCodeNotification::class)->first()->code->code;

    $this->actingAs($user)->post('/email/verify/code', ['code' => $code])
        ->assertRedirect(config('fortify.home').'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    Event::assertDispatched(Verified::class);
});

it('refuses a wrong code and leaves the address unconfirmed', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send');

    $this->actingAs($user)->post('/email/verify/code', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends somebody who is already confirmed on their way', function () {
    $user = CodeWorld::user();

    $this->actingAs($user)->get('/email/verify/code')->assertRedirect(config('fortify.home'));
    $this->actingAs($user)->post('/email/verify/code/send')->assertRedirect(config('fortify.home'));
    // Including the post that would confirm it: a second tab, or a link followed
    // on a phone, must not report "that code is wrong" for an address that is
    // already confirmed.
    $this->actingAs($user)->post('/email/verify/code', ['code' => '000000'])
        ->assertRedirect(config('fortify.home'));

    Notification::assertNothingSent();
});

it('is behind auth, like the screen it stands beside', function () {
    $this->get('/email/verify/code')->assertRedirect(route('login'));
});
