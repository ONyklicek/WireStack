<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Support\Codes;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\OptOutUser;

/*
 * The questions every code flow asks before it does anything: is this switched
 * on, is it switched on and able to run, is this user one of the people it is
 * for, and what is their code filed under.
 */

it('is off in an installation that said nothing', function () {
    // ADR 0037 §3. The default matters more than the switch: it is what an
    // application gets for upgrading without reading anything.
    expect(Codes::login())->toBeFalse()
        ->and(Codes::secondFactor())->toBeFalse()
        ->and(Codes::verifyEmail())->toBeFalse()
        ->and(Codes::resetPassword())->toBeFalse()
        ->and(Codes::any())->toBeFalse();
});

it('needs the Fortify feature as well as its own switch', function () {
    config()->set('wire-module-auth.codes.verify_email', true);
    config()->set('wire-module-auth.codes.reset_password', true);
    config()->set('fortify.features', []);

    // A screen for a feature Fortify never routed is a 404 with a link to it.
    expect(Codes::verifyEmail())->toBeFalse()
        ->and(Codes::resetPassword())->toBeFalse();

    config()->set('fortify.features', [Features::emailVerification(), Features::resetPasswords()]);

    expect(Codes::verifyEmail())->toBeTrue()
        ->and(Codes::resetPassword())->toBeTrue()
        ->and(Codes::any())->toBeTrue();
});

it('names the second factor that is on but cannot run', function () {
    config()->set('wire-module-auth.codes.second_factor', true);
    config()->set('fortify.features', []);

    // Not the same as "off": the pipe that mails the code is a contract Fortify
    // only resolves when its two-factor feature is on (ADR 0037 §5), so this
    // installation believes it turned something on that will never happen.
    expect(Codes::secondFactor())->toBeFalse()
        ->and(Codes::secondFactorIsStranded())->toBeTrue();

    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    expect(Codes::secondFactor())->toBeTrue()
        ->and(Codes::secondFactorIsStranded())->toBeFalse();
});

it('asks the model before it asks config', function () {
    config()->set('wire-module-auth.codes.second_factor', true);
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    expect(Codes::wantedBy(new CodeUser))->toBeTrue()
        ->and(Codes::wantedBy(new OptOutUser))->toBeFalse()
        ->and(Codes::wantedBy(null))->toBeFalse();
});

it('wants nothing from anybody while the flow is off', function () {
    expect(Codes::wantedBy(new CodeUser))->toBeFalse();
});

it('files an address in lower case and a user by key', function () {
    // The address is typed twice — once to ask for the code, once to use it —
    // and the two have to meet. The key is not typed at all.
    expect(Codes::identifierFor('  Ann@Example.com '))->toBe('ann@example.com')
        ->and(Codes::identifierFor(new CodeUser(['id' => 7])))->toBe('7');
});

it('sees an authenticator app only where Fortify would', function () {
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    $plain = new class extends User {};
    expect(Codes::usesAuthenticatorApp($plain))->toBeFalse();

    $user = new CodeUser;
    expect(Codes::usesAuthenticatorApp($user))->toBeFalse();

    $user->two_factor_secret = 'secret';
    expect(Codes::usesAuthenticatorApp($user))->toBeTrue();

    // With confirmation asked for, a setup nobody finished is not a second
    // factor — challenging for it would lock somebody behind a code they cannot
    // produce.
    // `confirmsTwoFactorAuthentication()` reads the feature's own options, so
    // this is the switch an application actually turns.
    config()->set('fortify.features', [Features::twoFactorAuthentication(['confirm' => true])]);

    expect(Codes::usesAuthenticatorApp($user))->toBeFalse();

    $user->two_factor_confirmed_at = now();
    expect(Codes::usesAuthenticatorApp($user))->toBeTrue();

    config()->set('fortify.features', []);
    expect(Codes::usesAuthenticatorApp($user))->toBeFalse();
});

it('names one wording per purpose, and keeps the stored value stable', function () {
    // The value is what is written into rows that are still live, so renaming a
    // case rewrites somebody's outstanding code into a wrong one.
    expect(CodePurpose::Login->value)->toBe('login')
        ->and(CodePurpose::SecondFactor->value)->toBe('second-factor')
        ->and(CodePurpose::VerifyEmail->translationKey())->toBe('wire-module-auth::messages.code_mail.verify_email')
        ->and(CodePurpose::ResetPassword->translationKey())->toBe('wire-module-auth::messages.code_mail.reset_password');
});
