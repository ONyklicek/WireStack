<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Laravel\Fortify\Features;
use Laravel\Fortify\FortifyServiceProvider;
use NyonCode\WireModuleAuth\Support\Frame;
use NyonCode\WireModuleAuth\Tests\Fixtures\AuthFrame;

/*
 * The seven screens, reached the way a browser reaches them.
 *
 * Through Fortify's own routes rather than by rendering a view directly, which
 * is the only version of this test that can fail for the right reason: it is the
 * *wiring* that was missing before this package — the routes existed, the
 * controllers existed, and the view callbacks answered with Laravel's own
 * "please define a view" error.
 *
 * The frame stands in for the shell (see the fixture), because these assertions
 * are about the screens and the shell has its own suite.
 */

beforeEach(function () {
    View::addNamespace('wire-admin', __DIR__.'/../Fixtures/views');
    Blade::component(Frame::SHELL_LAYOUT, AuthFrame::class);
});

it('answers Fortify with a login screen', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('data-testid="auth-login-form"', false)
        // Posting to the path it was served from: Fortify serves both verbs at
        // one URL and has renamed the POST across major versions.
        ->assertSee('action="'.route('login').'"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false)
        // The frame is the shell's, not one this package ships.
        ->assertSee('data-testid="auth-frame"', false);
});

it('links to the reset and the registration Fortify actually routes', function () {
    $this->get('/login')
        ->assertSee('data-testid="auth-forgot-link"', false)
        ->assertSee('data-testid="auth-register-link"', false);
});

it('drops those links where the features are off', function () {
    // A link to a route Fortify never registered is a 404 an application finds
    // out about from a user. Drawn from the same switch that creates the route.
    config()->set('fortify.features', []);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('data-testid="auth-forgot-link"', false)
        ->assertDontSee('data-testid="auth-register-link"', false);
});

it('answers Fortify with a registration screen', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('data-testid="auth-register-form"', false)
        ->assertSee('name="password_confirmation"', false);
});

it('answers Fortify with a request-a-link screen', function () {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertSee('data-testid="auth-forgot-form"', false)
        ->assertSee('action="'.route('password.request').'"', false);
});

it('answers Fortify with a set-a-new-password screen, carrying the token', function () {
    // The one screen whose POST is at a different URL from its GET — the link in
    // the mail carries the token in the path — so the action is the named route
    // and the token rides in a hidden input.
    $this->get('/reset-password/the-token?email=someone@example.com')
        ->assertOk()
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('value="the-token"', false)
        ->assertSee('value="someone@example.com"', false);
});

it('answers Fortify with a verify-your-address screen', function () {
    $this->actingAs(new AuthScreenUser)
        ->get('/email/verify')
        ->assertOk()
        ->assertSee('data-testid="auth-verify-form"', false)
        // The way out is on it: a screen whose only path onward is a link in an
        // e-mail is a trap without one.
        ->assertSee('data-testid="auth-sign-out"', false);
});

it('answers Fortify with a confirm-your-password screen', function () {
    $this->actingAs(new AuthScreenUser)
        ->get('/user/confirm-password')
        ->assertOk()
        ->assertSee('data-testid="auth-confirm-form"', false)
        ->assertSee('action="'.route('password.confirm').'"', false);
});

it('answers Fortify with a two-factor challenge that can switch to a recovery code', function () {
    // The challenge is served from a half-authenticated session, so it is
    // reached by putting Fortify's own session key in place rather than by
    // signing in — the login flow that gets here has its own owner, and it
    // looks the user up by that id.
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    AuthScreenUser::create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $this->withSession(['login.id' => 1, 'login.remember' => false])
        ->get('/two-factor-challenge')
        ->assertOk()
        ->assertSee('data-testid="auth-two-factor-form"', false)
        // Six boxes, and one input behind them carrying the name Fortify reads.
        // That input is what a browser with no Alpine types into, so it is on
        // the page rather than replaced by the boxes.
        ->assertSee('data-testid="form-otp-code-0"', false)
        ->assertSee('name="code"', false)
        ->assertSee('name="recovery_code"', false)
        ->assertSee('data-testid="auth-two-factor-toggle"', false);
});

it('shows what Fortify put in the session on the way here', function () {
    $this->withSession(['status' => 'We sent you a link.'])
        ->get('/forgot-password')
        ->assertSee('data-testid="auth-status"', false)
        ->assertSee('We sent you a link.');
});

it('says what Fortify answered in the panel own notice surface', function () {
    // The status and the error summary are `<x-wire::callout>`, the same box the
    // panel uses behind the door — they used to be hand-written green and red.
    $this->withSession(['status' => 'We sent you a link.'])
        ->get('/forgot-password')
        ->assertSee('data-testid="auth-status"', false)
        ->assertSee('role="alert"', false);
});

it('shows what the last attempt got wrong', function () {
    // Summarised as well as placed under the field: a failed sign-in is reported
    // against `email` whichever half was wrong, so the message alone under that
    // input is a message under the wrong one.
    // A `ViewErrorBag`, because that is what Laravel actually flashes: a failed
    // validation redirects `withErrors()`, which wraps the bag before it reaches
    // the session. A bare MessageBag here would be a fixture no request produces.
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => ['These credentials do not match.']]));

    $this->withSession(['errors' => $errors])
        ->get('/login')
        ->assertSee('data-testid="auth-errors"', false)
        ->assertSee('These credentials do not match.');
});

class AuthScreenUser extends User
{
    protected $table = 'users';

    protected $guarded = [];
}

it('offers a passkey where Fortify routes one, and the field the browser anchors to', function () {
    // The button is drawn from the same switch that registers the ceremony's
    // routes, like every other link on this screen — and the e-mail field grows
    // the `webauthn` token, which is what the browser's own credential dropdown
    // attaches to. Without an input carrying it, autofill shows nothing at all
    // and says nothing about why.
    config()->set('fortify.features', [Features::passkeys()]);
    app()->register(FortifyServiceProvider::class, force: true);

    $this->get('/login')
        ->assertOk()
        ->assertSee('data-testid="auth-passkey-button"', false)
        ->assertSee('wirePasskey(', false)
        ->assertSee('autocomplete="username webauthn"', false);
});

it('draws no passkey button where the feature is off', function () {
    config()->set('fortify.features', []);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('data-testid="auth-passkey-button"', false)
        ->assertSee('autocomplete="username"', false);
});
