<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Livewire\Livewire;
use NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication;
use NyonCode\WireModuleUsers\Pages\EditProfile;
use NyonCode\WireModuleUsers\Support\TwoFactor;
use NyonCode\WireModuleUsers\Tests\Fixtures\FortifyUser;
use NyonCode\WireModuleUsers\Tests\Support\Access;
use PragmaRX\Google2FA\Google2FA;

/*
 * Two-factor, against the real Fortify rather than a stub.
 *
 * The whole design of this card is that it drives somebody else's actions, so a
 * test that mocked them would be testing the mock. Fortify is a dev dependency
 * here for exactly this: the secret, the codes and the confirmation are its, and
 * what is asserted below is the part that is ours — which of the three states the
 * card is in, and that a half-finished setup can be left in both directions.
 */

beforeEach(function () {
    config()->set('wire-module-users.model', FortifyUser::class);
    config()->set('auth.providers.users.model', FortifyUser::class);
    config()->set('wire-module-users.roles', false);
    config()->set('fortify.features', [Features::twoFactorAuthentication(['confirm' => true])]);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->timestamps();
    });
});

function signedIn(): FortifyUser
{
    $user = FortifyUser::query()->create([
        'name' => 'Amelia',
        'email' => 'a@example.com',
        'password' => Hash::make('secret'),
    ]);

    test()->be($user);

    // The card's own guard: Fortify puts `password.confirm` on every route that
    // does these things, so the state a user reaches this card in is one where
    // the password was just confirmed. The tests about the guard itself say so.
    Access::confirmPassword();

    return $user;
}

it('sees the feature once Fortify is installed and has it switched on', function () {
    expect(TwoFactor::available())->toBeTrue()
        ->and(TwoFactor::enabled())->toBeTrue();

    // Installed-but-off has to read the same as not installed at all, or the
    // card appears and its buttons throw.
    config()->set('fortify.features', []);

    expect(TwoFactor::available())->toBeFalse();
});

it('appears on the profile page once it is available', function () {
    signedIn();

    expect(Livewire::test(EditProfile::class)->instance()->cards())
        ->toContain(TwoFactorAuthentication::class);
});

it('starts off, with nothing to scan', function () {
    $user = signedIn();

    expect(TwoFactor::pending($user))->toBeFalse()
        ->and(TwoFactor::confirmed($user))->toBeFalse();

    Livewire::test(TwoFactorAuthentication::class)
        ->assertSee('Turn on two-factor')
        ->assertDontSee('Finish setting it up');
});

it('is not "on" merely because a secret exists', function () {
    // Fortify writes the secret the moment the QR code is generated, so somebody
    // who opened the panel and closed the tab has one and is protected by
    // nothing. A card that modelled this as a boolean would tell them they were
    // safe.
    $user = signedIn();

    Livewire::test(TwoFactorAuthentication::class)->call('enable');

    $user->refresh();

    expect(TwoFactor::pending($user))->toBeTrue()
        ->and(TwoFactor::confirmed($user))->toBeFalse();
});

it('shows the code to scan and the key to type, and finishes on a right code', function () {
    $user = signedIn();

    $component = Livewire::test(TwoFactorAuthentication::class)->call('enable');

    // A desktop authenticator or a locked-down camera is common enough that a
    // setup screen with only a QR code on it is one some people cannot finish.
    expect($component->instance()->qrCodeSvg())->toContain('<svg')
        ->and($component->instance()->setupKey())->not->toBeNull();

    $code = app(Google2FA::class)
        ->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));

    $component->set('code', $code)->call('confirm');

    expect(TwoFactor::confirmed($user->refresh()))->toBeTrue()
        // The codes are shown once, right after setting up — that is the only
        // moment somebody is looking at the screen ready to write them down.
        ->and($component->instance()->recoveryCodes())->not->toBeEmpty();
});

it('asks for the code in the same boxes as the screen on the way in', function () {
    // ADR 0037 §6: one field for a six-digit code, everywhere one is typed. This
    // card used to draw a plain text input while the challenge three clicks away
    // drew boxes that advance themselves and take a pasted code apart.
    signedIn();

    Livewire::test(TwoFactorAuthentication::class)
        ->call('enable')
        ->assertSee('data-testid="form-otp-code-0"', false)
        // Still this component's own property: a field with no state path binds
        // to the name it was made with, so the boxes write into `$code` rather
        // than into a form's state array that nothing here reads.
        ->assertSee('statePath: \'code\'', false)
        ->set('code', '123456')
        ->assertSet('code', '123456');
});

it('keeps the pending state on a wrong code, and says so', function () {
    $user = signedIn();

    Livewire::test(TwoFactorAuthentication::class)
        ->call('enable')
        ->set('code', '000000')
        ->call('confirm')
        ->assertHasErrors('code');

    expect(TwoFactor::confirmed($user->refresh()))->toBeFalse();
});

it('can be left from the half-finished state, not only from the finished one', function () {
    // Or it is a trap: a setup somebody walked away from has to be leavable in
    // both directions.
    $user = signedIn();

    Livewire::test(TwoFactorAuthentication::class)
        ->call('enable')
        ->assertSee('Turn it off')
        ->call('disable');

    expect(TwoFactor::pending($user->refresh()))->toBeFalse();
});

it('regenerates the recovery codes, and shows them only when asked', function () {
    $user = signedIn();

    $component = Livewire::test(TwoFactorAuthentication::class)->call('enable');

    $code = app(Google2FA::class)
        ->getCurrentOtp(decrypt($user->refresh()->two_factor_secret));

    $component->set('code', $code)->call('confirm');

    $before = $component->instance()->recoveryCodes();

    $component->call('regenerateRecoveryCodes');

    expect($component->instance()->recoveryCodes())->not->toBe($before)
        ->and($component->instance()->recoveryCodes())->not->toBeEmpty();

    // Never on screen by default: they are the thing worth shoulder-surfing.
    $component->call('toggleRecoveryCodes');

    expect($component->instance()->recoveryCodes())->toBe([]);
});

it('honours a Fortify that was told not to ask for confirmation', function () {
    config()->set('fortify.features', [Features::twoFactorAuthentication(['confirm' => false])]);

    expect(TwoFactor::confirmationRequired())->toBeFalse();

    $user = signedIn();

    Livewire::test(TwoFactorAuthentication::class)->call('enable');

    // Without the confirmation step a secret *is* the whole of the setup, so the
    // pending state never occurs.
    expect(TwoFactor::confirmed($user->refresh()))->toBeTrue();
});

it('does nothing for nobody', function () {
    // The card can be rendered on a page nobody is signed in to; that is not an
    // error worth an exception on a profile page.
    Livewire::test(TwoFactorAuthentication::class)
        ->call('enable')
        ->assertOk();

    expect(TwoFactor::confirmed(null))->toBeFalse()
        ->and(TwoFactor::pending(null))->toBeFalse();
});

it('does nothing for nobody, whichever button is pressed', function () {
    // Every one of these can be rendered on a page nobody is signed in to, and
    // none of them is an error worth an exception on a profile page.
    $card = Livewire::test(TwoFactorAuthentication::class);

    $card->call('confirm')->assertOk();
    $card->call('disable')->assertOk();
    $card->call('regenerateRecoveryCodes')->assertOk();

    expect($card->instance()->qrCodeSvg())->toBeNull()
        ->and($card->instance()->setupKey())->toBeNull();
});

it('shows nothing rather than failing when the stored secret cannot be read', function () {
    // A secret written under a different APP_KEY, or a column somebody edited by
    // hand. The card is not the place that finds out — it draws what it can.
    $user = signedIn();

    $user->forceFill([
        'two_factor_secret' => 'not-something-decrypt-can-read',
        'two_factor_recovery_codes' => 'nor-this',
    ])->save();

    $card = Livewire::test(TwoFactorAuthentication::class);

    $card->call('toggleRecoveryCodes');

    expect($card->instance()->setupKey())->toBeNull()
        ->and($card->instance()->qrCodeSvg())->toBeNull()
        ->and($card->instance()->recoveryCodes())->toBe([]);
});
