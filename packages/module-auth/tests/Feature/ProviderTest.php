<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\View;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\ConfirmPasswordViewResponse;
use Laravel\Fortify\Contracts\LoginViewResponse;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\RegisterViewResponse;
use Laravel\Fortify\Contracts\RequestPasswordResetLinkViewResponse;
use Laravel\Fortify\Contracts\ResetPasswordViewResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\Contracts\VerifyEmailViewResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Responses\SimpleViewResponse;
use NyonCode\WireCore\Foundation\View\PageChrome;
use NyonCode\WireModuleAuth\Actions\RedirectIfCodeRequired;
use NyonCode\WireModuleAuth\Http\Responses\RedirectToResetCodeScreen;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\WireModuleAuthServiceProvider;

/*
 * What the package does at boot, and what it deliberately does not.
 *
 * Two switches and one region. Everything here has the same shape of failure,
 * and it is the one that matters for a package like this: silence. A view
 * callback never answered, a menu entry never registered, a panel with no guard
 * on it — none of them raise anything, and all three look installed.
 */

/** Re-run the boot callbacks against config a test just set. */
function amRebootWith(array $config): void
{
    foreach ($config as $key => $value) {
        config()->set($key, $value);
    }

    app()->register(new WireModuleAuthServiceProvider(app()), force: true);
}

it('answers every one of Fortify view callbacks', function () {
    // All seven rather than one per enabled feature: a view for a feature that
    // is off is never routed to, so gating them here would be a second copy of
    // `fortify.features` that can disagree with the first.
    expect(app()->make(LoginViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class)
        ->and(app()->make(RegisterViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class)
        ->and(app()->make(RequestPasswordResetLinkViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class)
        ->and(app()->make(ResetPasswordViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class)
        ->and(app()->make(VerifyEmailViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class)
        ->and(app()->make(ConfirmPasswordViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class)
        ->and(app()->make(TwoFactorChallengeViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class);
});

it('leaves an answer of the application own alone when told not to answer', function () {
    // The switch exists for an application migrating from views it already has.
    // A sentinel binding rather than an absent one, because Fortify's contracts
    // are interfaces: "did not rebind" and "was never bound" are different
    // states and only the first is what this switch promises.
    app()->singleton(LoginViewResponse::class, fn () => new class implements LoginViewResponse
    {
        public function toResponse($request)
        {
            return 'the application\'s own';
        }
    });

    amRebootWith(['wire-module-auth.views' => false]);

    expect(app()->make(LoginViewResponse::class))->not->toBeInstanceOf(SimpleViewResponse::class);
});

it('takes the answer over again when told to', function () {
    // The other half, and the reason the test above is not a test of nothing:
    // the same sentinel, the same reboot, the switch the other way.
    app()->singleton(LoginViewResponse::class, fn () => new class implements LoginViewResponse
    {
        public function toResponse($request)
        {
            return 'the application\'s own';
        }
    });

    amRebootWith(['wire-module-auth.views' => true]);

    expect(app()->make(LoginViewResponse::class))->toBeInstanceOf(SimpleViewResponse::class);
});

it('puts the way out in the user menu', function () {
    expect(app(PageChrome::class)->has('wire-module-auth::user-menu', PageChrome::USER_MENU))->toBeTrue();
});

it('registers the way out whether or not the shell has booted yet', function () {
    // The condition that is deliberately absent. Provider order is composer's
    // discovery order, so a check for the shell here would answer false for a
    // shell that boots afterwards — and the entry would be missing from a menu
    // that exists. Whether there is a shell is asked at render.
    expect(Frame::hasShell())->toBeFalse()
        ->and(app(PageChrome::class)->has('wire-module-auth::user-menu', PageChrome::USER_MENU))->toBeTrue();
});

it('sorts the way out after entries other packages contribute', function () {
    // "Sign out" above "Profile" reads as a bug, and neither package can see the
    // other to avoid it.
    $chrome = app(PageChrome::class);
    $chrome->add('wire-module-users::profile-menu-item', PageChrome::USER_MENU, 10);

    expect($chrome->views(PageChrome::USER_MENU))->toBe([
        'wire-module-users::profile-menu-item',
        'wire-module-auth::user-menu',
    ]);
});

it('leaves the menu alone where the application asked it to', function () {
    // A fresh registry: the one on the booted application already carries the
    // entry from this suite's own boot, and asserting against it would pass for
    // a switch that does nothing.
    app()->instance(PageChrome::class, new PageChrome);

    amRebootWith(['wire-module-auth.user_menu' => false]);

    expect(app(PageChrome::class)->views(PageChrome::USER_MENU))->toBe([]);
});

it('says what the installation got, in php artisan about', function () {
    $data = (new WireModuleAuthServiceProvider(app()))->aboutData();

    expect($data['Screens'])->toBe('answered by wire-module-auth')
        // No shell in this suite, so the frame is whatever config names — which
        // is the state an application in this position is actually in.
        ->and($data['Frame'])->toBe('auto')
        ->and($data['Registration'])->toBe('open')
        ->and($data['Two-factor'])->toBe('enabled');
});

it('names the frame the screens will actually use, in about', function () {
    // The other branch of that line, and the one an installed application is
    // in: with a frame resolvable, `about` says which — a config value of
    // `auto` tells nobody anything.
    View::addNamespace('wire-admin', __DIR__.'/../Fixtures/views');

    $data = (new WireModuleAuthServiceProvider(app()))->aboutData();

    expect($data['Frame'])->toBe(Frame::SHELL_LAYOUT);
});

it('says so in about when the application answers Fortify itself', function () {
    config()->set('wire-module-auth.views', false);
    config()->set('fortify.features', []);

    $data = (new WireModuleAuthServiceProvider(app()))->aboutData();

    expect($data['Screens'])->toBe('left to the application')
        ->and($data['Registration'])->toBe('closed')
        ->and($data['Two-factor'])->toBe('off');
});

it('reports the code flows in about, and the one that cannot run', function () {
    $provider = new WireModuleAuthServiceProvider(app());

    expect($provider->aboutData()['One-time codes'])->toBe('off');

    config()->set('wire-module-auth.codes.login', true);
    config()->set('wire-module-auth.codes.verify_email', true);

    expect($provider->aboutData()['One-time codes'])->toBe('sign-in, verification');

    // The state a switch alone cannot express: on, and never going to happen,
    // because Fortify's two-factor feature is what puts the pipe in the
    // pipeline (ADR 0037 §5). Reported instead of "off", which would agree with
    // the config and describe nothing.
    config()->set('wire-module-auth.codes.second_factor', true);
    config()->set('fortify.features', []);

    expect($provider->aboutData()['One-time codes'])->toBe('second factor on, but Fortify two-factor is off');
});

it('binds Fortify two-factor pipe only where the mailed second factor is on', function () {
    // The whole of the mailed second factor is this binding: Fortify's own login
    // pipeline resolves the contract, so replacing it inserts a code without a
    // copy of that pipeline existing anywhere (ADR 0037 §2).
    expect(app()->make(RedirectsIfTwoFactorAuthenticatable::class))
        ->toBeInstanceOf(RedirectIfTwoFactorAuthenticatable::class)
        ->not->toBeInstanceOf(RedirectIfCodeRequired::class);

    amRebootWith([
        'wire-module-auth.codes.second_factor' => true,
        'fortify.features' => [Features::twoFactorAuthentication()],
    ]);

    expect(app()->make(RedirectsIfTwoFactorAuthenticatable::class))->toBeInstanceOf(RedirectIfCodeRequired::class);
});

it('leaves Laravel reset mail alone until the reset flow is on', function () {
    expect(ResetPassword::$toMailCallback)->toBeNull();

    amRebootWith([
        'wire-module-auth.codes.reset_password' => true,
        'fortify.features' => [Features::resetPasswords()],
    ]);

    // A code in the mail, and Fortify's "we sent a link" response replaced by
    // one that lands on a screen with a field for what the mail contains.
    expect(ResetPassword::$toMailCallback)->not->toBeNull()
        ->and(app()->make(SuccessfulPasswordResetLinkRequestResponse::class, ['status' => 'passwords.sent']))
        ->toBeInstanceOf(RedirectToResetCodeScreen::class);
});
