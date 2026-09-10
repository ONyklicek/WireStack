<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Signing in with a code and no password.
 *
 * The flow with no Fortify half to keep, so it is the one that has to be tested
 * end to end: what it does to the session, what it says to a stranger, and the
 * two things it must refuse — a code that has been used, and a code used to walk
 * past somebody's authenticator app.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();
    CodeWorld::enable(['login' => true]);

    config()->set('auth.providers.users.model', CodeUser::class);

    Notification::fake();
});

/** The digits, read back out of the notification the way a person reads their mail. */
function mailedCode(): string
{
    $sent = Notification::sent(CodeUser::query()->firstOrFail(), OneTimeCodeNotification::class);

    return $sent->first()->code->code;
}

it('offers the way in from the sign-in screen, only where it is switched on', function () {
    $this->get('/login')->assertSee('data-testid="auth-code-link"', false);

    config()->set('wire-module-auth.codes.login', false);

    $this->get('/login')->assertDontSee('data-testid="auth-code-link"', false);
});

it('draws the screen that asks for an address', function () {
    $this->get('/login/code')
        ->assertOk()
        ->assertSee('data-testid="auth-login-code-form"', false)
        ->assertSee('name="email"', false)
        ->assertSee('data-testid="auth-back-link"', false);
});

it('draws the boxes, naming the address the code went to', function () {
    CodeWorld::user();

    $this->post('/login/code', ['email' => 'ann@example.com']);

    $this->get('/login/code/challenge')
        ->assertOk()
        ->assertSee('data-testid="auth-code-form"', false)
        ->assertSee('data-testid="form-otp-code-0"', false)
        // The screen says which inbox to look in — a person with three of them
        // otherwise has to guess which one they typed.
        ->assertSee('ann@example.com');
});

it('asks for an address and mails a code', function () {
    $user = CodeWorld::user();

    $this->post('/login/code', ['email' => 'ann@example.com'])
        ->assertRedirect(route('wire-auth.login-code.challenge'));

    Notification::assertSentTo($user, OneTimeCodeNotification::class, function ($notification) {
        return $notification->code->purpose === CodePurpose::Login;
    });
});

it('says the same thing to an address that has no account', function () {
    // The reply is Fortify's forgot-password reply for the same reason: a screen
    // that says "no such account" is an account enumerator, and this one would
    // be a faster one.
    $this->post('/login/code', ['email' => 'nobody@example.com'])
        ->assertRedirect(route('wire-auth.login-code.challenge'));

    Notification::assertNothingSent();
});

it('signs the right code in, through Fortify own response', function () {
    $user = CodeWorld::user();

    $this->post('/login/code', ['email' => 'ann@example.com']);

    $this->post('/login/code/challenge', ['code' => mailedCode()])
        ->assertRedirect(config('fortify.home'));

    expect(auth()->id())->toBe($user->getKey());
});

it('refuses a wrong code, and the right one only once', function () {
    CodeWorld::user();

    $this->post('/login/code', ['email' => 'ann@example.com']);
    $code = mailedCode();

    $this->post('/login/code/challenge', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect(auth()->check())->toBeFalse();

    $this->post('/login/code/challenge', ['code' => $code]);
    expect(auth()->check())->toBeTrue();

    // Verifying consumes: the same digits a second time are a wrong code, which
    // is what stops a code left open in an inbox being a spare key.
    auth()->logout();
    $this->post('/login/code', ['email' => 'ann@example.com']);
    $this->post('/login/code/challenge', ['code' => $code])
        ->assertSessionHasErrors('code');
});

it('hands somebody with an authenticator app to Fortify challenge instead', function () {
    // The rule that makes this flow safe to switch on: a code to an inbox is one
    // factor, and it must not be the way around the second.
    config()->set('fortify.features', [Features::twoFactorAuthentication()]);

    $user = CodeWorld::user([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post('/login/code', ['email' => 'ann@example.com']);

    $this->post('/login/code/challenge', ['code' => mailedCode()])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBe($user->getKey());
});

it('sends nobody to the challenge screen without an address to challenge', function () {
    $this->get('/login/code/challenge')->assertRedirect(route('wire-auth.login-code'));
    $this->post('/login/code/challenge', ['code' => '123456'])->assertRedirect(route('wire-auth.login-code'));
});

it('has nothing to resend without an address in the session', function () {
    $this->post('/login/code/challenge/send')->assertRedirect(route('wire-auth.login-code'));
});

it('waits before mailing a second code, and says so', function () {
    CodeWorld::user();

    $this->post('/login/code', ['email' => 'ann@example.com']);

    $this->post('/login/code/challenge/send')
        ->assertSessionHas('status', __('wire-module-auth::messages.code_sent_recently'));

    Notification::assertSentToTimes(CodeUser::query()->firstOrFail(), OneTimeCodeNotification::class, 1);
});

it('mails another once the window has passed', function () {
    CodeWorld::user();

    $this->post('/login/code', ['email' => 'ann@example.com']);

    $this->travel(2)->minutes();

    $this->post('/login/code/challenge/send')
        ->assertSessionHas('status', __('wire-module-auth::messages.code_sent'));

    Notification::assertSentToTimes(CodeUser::query()->firstOrFail(), OneTimeCodeNotification::class, 2);
});

it('registers the routes of the flow that is on, and of no other', function () {
    // ADR 0037 §3: four switches, four groups of routes. A flow that is off
    // cannot be reached by guessing its URL, and an installation that turned
    // nothing on has no new lines in `route:list` at all.
    // Read off the collection rather than through `hasNamedRoute()`: the name
    // lookup is a cache the router rebuilds when it first matches something, so
    // in a test that has made no request it answers no for routes that are
    // registered.
    $names = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values();

    expect($names)->toContain('wire-auth.login-code', 'wire-auth.login-code.challenge')
        ->not->toContain('wire-auth.second-factor')
        ->not->toContain('wire-auth.verify-email-code')
        ->not->toContain('wire-auth.reset-code');
});
