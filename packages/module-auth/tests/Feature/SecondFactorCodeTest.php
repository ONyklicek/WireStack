<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Support\Codes;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;
use NyonCode\WireModuleAuth\Tests\Fixtures\OptOutUser;

/*
 * The mailed second factor, driven through Fortify's own login route.
 *
 * Which is the only version of this test worth having: the flow is a *binding*
 * into Fortify's login pipeline (ADR 0037 §2), so posting to `/login` is what
 * proves the pipe is in the pipeline at all. A test that called the pipe
 * directly would pass with the binding deleted.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    CodeWorld::enable(['second_factor' => true]);

    Notification::fake();
});

function secondFactorCode(): string
{
    return Notification::sent(CodeUser::query()->firstOrFail(), OneTimeCodeNotification::class)
        ->first()->code->code;
}

it('stops a correct password at a challenge and mails the code', function () {
    $user = CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertRedirect(route('wire-auth.second-factor'));

    // Fortify's own key for a pending sign-in — the password *was* checked, so
    // this is exactly the state its own challenge runs in.
    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBe($user->getKey());

    Notification::assertSentTo($user, OneTimeCodeNotification::class, function ($notification) {
        return $notification->code->purpose === CodePurpose::SecondFactor;
    });
});

it('lets a wrong password through to Fortify failure, with no code sent', function () {
    CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'nope'])
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
});

it('opens the session once the code is right, and fires Fortify events', function () {
    Event::fake([ValidTwoFactorAuthenticationCodeProvided::class, TwoFactorAuthenticationFailed::class]);

    $user = CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->post('/two-factor/code', ['code' => secondFactorCode()])
        ->assertRedirect(config('fortify.home'));

    expect(auth()->id())->toBe($user->getKey())
        ->and(session()->has('login.id'))->toBeFalse();

    Event::assertDispatched(ValidTwoFactorAuthenticationCodeProvided::class);
});

it('refuses a wrong code and says so on Fortify own event', function () {
    Event::fake([TwoFactorAuthenticationFailed::class]);

    CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->post('/two-factor/code', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(auth()->check())->toBeFalse();

    Event::assertDispatched(TwoFactorAuthenticationFailed::class);
});

it('draws the boxes for the pending sign-in', function () {
    CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->get('/two-factor/code')
        ->assertOk()
        ->assertSee('data-testid="auth-code-form"', false)
        ->assertSee('data-testid="form-otp-code-0"', false)
        ->assertSee('data-testid="auth-code-resend"', false);
});

it('mails another code on request, once the window has passed', function () {
    $user = CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    $this->post('/two-factor/code/send')
        ->assertSessionHas('status', __('wire-module-auth::messages.code_sent_recently'));

    $this->travel(2)->minutes();

    $this->post('/two-factor/code/send')
        ->assertSessionHas('status', __('wire-module-auth::messages.code_sent'));

    Notification::assertSentToTimes($user, OneTimeCodeNotification::class, 2);
});

it('answers a JSON sign-in the way Fortify answers its own challenge', function () {
    // Fortify replies `{"two_factor": true}` to an API client whose password was
    // right and whose session is not open yet. A second factor that answered
    // something else would need every such client changed.
    CodeWorld::user();

    $this->postJson('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertOk()
        ->assertJson(['two_factor' => true]);
});

it('sends anybody without a pending sign-in back to the login screen', function () {
    $this->get('/two-factor/code')->assertRedirect(route('login'));
    $this->post('/two-factor/code', ['code' => '123456'])->assertRedirect(route('login'));
});

it('leaves an authenticator app in charge where there is one', function () {
    // Fortify's branch, inherited unchanged: a confirmed TOTP secret is a
    // stronger factor than an inbox, and the person set it up on purpose.
    CodeWorld::user([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertRedirect(route('two-factor.login'));

    Notification::assertNothingSent();
});

it('lets a user model opt out for itself', function () {
    // ADR 0037 §4: the model answers first, and a package has no business
    // writing a column onto an application's users table to ask.
    config()->set('auth.providers.users.model', OptOutUser::class);

    CodeWorld::user(model: OptOutUser::class);

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertRedirect(config('fortify.home'));

    expect(auth()->check())->toBeTrue();

    Notification::assertNothingSent();
});

it('is switched off, and says why, when Fortify two-factor feature is off', function () {
    // The one way this flow can be on and useless: with the feature off, the
    // contract this binds is never in Fortify's pipeline, so no code is ever
    // sent. Reported rather than silently "off" (ADR 0037 §5).
    config()->set('fortify.features', []);

    expect(Codes::secondFactor())->toBeFalse()
        ->and(Codes::secondFactorIsStranded())->toBeTrue();
});

it('will not mail a second factor to somebody Fortify challenged for their app', function () {
    // The bypass this guard exists for, walked end to end rather than asserted
    // at the pipeline. The old test stopped at "no code was sent on login",
    // which was true and not enough: the pipe declined to send one, and the
    // challenge screen would have sent it anyway to anybody who asked.
    $user = CodeWorld::user([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertRedirect(route('two-factor.login'));

    // The precondition of the attack: a pending sign-in is on the session, and
    // it looks exactly like the one a mailed code would have written.
    expect(session('login.id'))->toBe($user->getKey());

    // Ignore that redirect and ask for a code anyway.
    $this->post('/two-factor/code/send')->assertRedirect(route('two-factor.login'));
    $this->get('/two-factor/code')->assertRedirect(route('two-factor.login'));

    Notification::assertNothingSent();

    // And no code can be typed in either, so a code from some earlier flow is
    // not a way in either.
    $this->post('/two-factor/code', ['code' => '123456'])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse();
});

it('will not turn the passwordless hand-off to an app into a mailed code', function () {
    // CodeLoginController refuses to open a session for a TOTP user and writes
    // `login.id` instead. Without this guard the very next request converted
    // that refusal into the thing it refused.
    CodeWorld::enable(['second_factor' => true, 'login' => true]);

    $user = CodeWorld::user([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login/code', ['email' => 'ann@example.com']);

    $code = Notification::sent($user, OneTimeCodeNotification::class)->first()->code->code;

    $this->post('/login/code/challenge', ['code' => $code])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBe($user->getKey());

    $this->post('/two-factor/code/send')->assertRedirect(route('two-factor.login'));

    // Only the passwordless one, never a second-factor one.
    Notification::assertSentToTimes($user, OneTimeCodeNotification::class, 1);
    expect(auth()->check())->toBeFalse();
});

it('will not mail a second factor to a user who declined them', function () {
    // No legitimate path leaves this state behind — the opt-out user walks
    // straight through the pipeline — so the session is seeded directly. That is
    // the point: the guard has to hold for a session key however it got there.
    config()->set('auth.providers.users.model', OptOutUser::class);

    $user = CodeWorld::user(model: OptOutUser::class);

    session(['login.id' => $user->getKey()]);

    $this->post('/two-factor/code/send')->assertRedirect(route('login'));
    $this->get('/two-factor/code')->assertRedirect(route('login'));

    Notification::assertNothingSent();
    expect(auth()->check())->toBeFalse();
});
