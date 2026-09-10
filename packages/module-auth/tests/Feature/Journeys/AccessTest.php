<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Who may be where, under the configurations an application actually ships.
 *
 * This package authenticates nobody (ADR 0032 §1), so what it owes the rest of
 * the framework is narrower and easier to break than it looks: that the guest
 * middleware can find the sign-in screen, that a signed-in person is not sent
 * back to it, and that a screen behind a permission answers the way the
 * application's own Gate says rather than the way this package guesses.
 *
 * The last one is the reason this file exists rather than living in the panel's
 * suite: `can:` middleware resolves through Laravel's Gate, so an admin guarded
 * by a permission and an admin guarded by a closure have to behave identically
 * from here — and neither is allowed to depend on anything this package knows.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    config()->set('fortify.features', [Features::registration(), Features::resetPasswords()]);
});

// ─── The guest side of the door ────────────────────────────────────

it('sends a guest at a protected page to the sign-in screen', function () {
    // The contract behind `auth` middleware is a route *named* `login`, and it
    // is Fortify that names it. If that ever stopped resolving, every guarded
    // page in an application would fail with a routing error instead of a form.
    Route::middleware(['web', 'auth'])->get('/members', fn (): string => 'members only');

    $this->get('/members')->assertRedirect(route('login'));
});

it('lets a guest reach every screen meant for one', function () {
    foreach (['/login', '/register', '/forgot-password'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('turns a signed-in person away from the sign-in screen', function () {
    // `guest` middleware, and worth pinning because the failure is a loop: a
    // signed-in person who can still reach /login can sign in again, and an
    // application that redirects them there on success never settles.
    $this->actingAs(CodeWorld::user())
        ->get('/login')
        ->assertRedirect();
});

// ─── Behind a permission ───────────────────────────────────────────

it('lets a permission decide, and answers a refusal with 403 rather than the sign-in screen', function () {
    // The distinction that matters to somebody reading a log: not signed in is a
    // redirect, signed in and not allowed is a refusal. A page that answered
    // both with the login screen would send an admin round in circles.
    Gate::define('manage-billing', fn (CodeUser $user): bool => $user->email === 'boss@example.com');

    Route::middleware(['web', 'auth', 'can:manage-billing'])
        ->get('/billing', fn (): string => 'the ledger');

    $this->actingAs(CodeWorld::user())->get('/billing')->assertForbidden();

    $boss = CodeWorld::user(['email' => 'boss@example.com']);
    $this->actingAs($boss)->get('/billing')->assertOk()->assertSee('the ledger');
});

it('still sends a guest at a permission-guarded page to sign in first', function () {
    // Order of middleware, stated as behaviour: `auth` answers before `can:`,
    // so a guest gets the door rather than a refusal about a permission no
    // anonymous request could ever hold.
    Gate::define('manage-billing', fn (CodeUser $user): bool => true);

    Route::middleware(['web', 'auth', 'can:manage-billing'])
        ->get('/billing', fn (): string => 'the ledger');

    $this->get('/billing')->assertRedirect(route('login'));
});

it('takes a wildcard permission the same as an exact one', function () {
    // `nyoncode/laravel-permission-extended` answers `billing.manage` through a
    // wildcard grant, and it answers it *at the Gate*. Modelled here with a Gate
    // that matches a prefix, because what this package must not do is care which
    // of the two it is — the moment it does, an installation with wildcards
    // behaves differently from one without.
    Gate::before(fn (CodeUser $user, string $ability): ?bool => str_starts_with($ability, 'billing.') ? true : null);

    Route::middleware(['web', 'auth', 'can:billing.manage'])
        ->get('/billing', fn (): string => 'the ledger');

    $this->actingAs(CodeWorld::user())->get('/billing')->assertOk();
});

// ─── Verified addresses as a condition ─────────────────────────────

it('holds an unverified person at the verify screen, and lets them past once confirmed', function () {
    config()->set('fortify.features', [Features::emailVerification()]);

    Route::middleware(['web', 'auth', 'verified'])->get('/members', fn (): string => 'members only');

    $user = CodeWorld::user(['email_verified_at' => null]);

    $this->actingAs($user)->get('/members')->assertRedirect(route('verification.notice'));

    $user->forceFill(['email_verified_at' => now()])->save();

    $this->actingAs($user->fresh())->get('/members')->assertOk();
});
