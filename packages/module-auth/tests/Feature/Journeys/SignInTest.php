<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Signing in, from the screen to the session.
 *
 * The other suites here prove the screens *render*. This one presses the button:
 * it posts what the rendered form would post and asks whether the person ended
 * up signed in. That seam is the one this package can break on its own — a field
 * renamed in the schema still renders beautifully and posts a key Fortify never
 * reads, and every markup assertion stays green while nobody can log in.
 *
 * What is deliberately not tested here is Fortify's half: the hashing, the
 * throttle's own arithmetic, the session fixation guard. Those are its tests.
 * What is tested is that our screen drives them.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::registration(), Features::resetPasswords()]);
});

// ─── The happy path ────────────────────────────────────────────────

it('signs a person in with the credentials the screen asked for', function () {
    CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse'])
        ->assertRedirect();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->email)->toBe('ann@example.com');
});

it('posts under exactly the names the screen rendered', function () {
    // The test that ties the schema to the flow. `AuthForms::login()` decides
    // these names and Fortify's request reads them; if the two ever disagree the
    // screen still renders and nobody can sign in.
    CodeWorld::user();

    $screen = $this->get('/login')->assertOk();

    foreach (['email', 'password'] as $name) {
        $screen->assertSee('name="'.$name.'"', false);
    }

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    expect(Auth::check())->toBeTrue();
});

// ─── Being turned away ─────────────────────────────────────────────

it('refuses a wrong password and says so against the identity field', function () {
    CodeWorld::user();

    $this->from('/login')
        ->post('/login', ['email' => 'ann@example.com', 'password' => 'not-it'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('answers an unknown address the same way as a wrong password', function () {
    // Same field, same shape of reply: a login screen that says "no such
    // account" is a login screen that confirms which addresses have one.
    CodeWorld::user();

    $this->from('/login')
        ->post('/login', ['email' => 'nobody@example.com', 'password' => 'correct-horse'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

it('refuses an empty submission before it reaches the credential check', function () {
    $this->from('/login')
        ->post('/login', [])
        ->assertSessionHasErrors('email');

    expect(Auth::check())->toBeFalse();
});

// ─── Staying signed in ─────────────────────────────────────────────

it('remembers the person when the box is ticked', function () {
    // `remember` is the one field on this screen whose absence is meaningful, so
    // both halves are worth pinning: the browser sends the key only when the box
    // is ticked, and Fortify reads that as the difference.
    CodeWorld::user();

    $this->post('/login', [
        'email' => 'ann@example.com',
        'password' => 'correct-horse',
        'remember' => '1',
    ]);

    expect(Auth::viaRemember())->toBeFalse()
        ->and(Auth::user()->getRememberToken())->not->toBeEmpty();
});

it('does not remember when the box was left alone', function () {
    CodeWorld::user();

    $this->post('/login', ['email' => 'ann@example.com', 'password' => 'correct-horse']);

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->getRememberToken())->toBeEmpty();
});

// ─── On the way back out ───────────────────────────────────────────

it('ends the session on the route the user menu posts to', function () {
    // SignOutTest proves the menu posts here; this proves what happens when it
    // does. Split because the markup and the effect fail for different reasons.
    $user = CodeWorld::user();

    $this->actingAs($user)->post('/logout')->assertRedirect();

    expect(Auth::check())->toBeFalse();
});
