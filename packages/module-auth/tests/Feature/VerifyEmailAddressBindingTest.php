<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * A verify-email code proves one address, and only the one it was mailed to.
 *
 * `Codes::identifierFor()` files the code under the user *key* on purpose, so a
 * code survives the person editing their address mid-flow. "Survive" must not
 * mean "follow": before the address rode along on the payload, the code was
 * checked against the key and then `markEmailAsVerified()` stamped whatever the
 * column happened to say — so requesting a code, changing the address, and
 * typing the digits confirmed an address that had never been sent anything.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::emailVerification()]);

    CodeWorld::enable(['verify_email' => true]);

    Notification::fake();
});

function verifyEmailCode(CodeUser $user): string
{
    return Notification::sent($user, OneTimeCodeNotification::class)->first()->code->code;
}

it('confirms the address the code was actually mailed to', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send');

    $this->actingAs($user)->post('/email/verify/code', ['code' => verifyEmailCode($user)]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('will not let a code follow the address to a new one', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send');

    $code = verifyEmailCode($user);

    // The move the payload exists to stop: the code was mailed to the address
    // above, and this one was never sent anything.
    $user->forceFill(['email' => 'not-mine@example.com'])->saveQuietly();

    $this->actingAs($user->fresh())
        ->post('/email/verify/code', ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('treats a change of case as the same address', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send');

    $code = verifyEmailCode($user);

    $user->forceFill(['email' => strtoupper((string) $user->email)])->saveQuietly();

    // Compared the way every other address in this package is compared. A
    // refusal here would be a lockout dressed up as a security check.
    $this->actingAs($user->fresh())->post('/email/verify/code', ['code' => $code]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('spends the code even when the address moved, so it cannot be retried', function () {
    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->post('/email/verify/code/send');

    $code = verifyEmailCode($user);

    $user->forceFill(['email' => 'not-mine@example.com'])->saveQuietly();

    $this->actingAs($user->fresh())->post('/email/verify/code', ['code' => $code]);

    // Changing the address back must not resurrect a code that was already
    // typed — verifying consumes, whichever way the answer went.
    $user->forceFill(['email' => 'ann@example.com'])->saveQuietly();

    $this->actingAs($user->fresh())
        ->post('/email/verify/code', ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
