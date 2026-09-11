<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use NyonCode\WireModuleAuth\Http\Controllers\CodeLoginController;
use NyonCode\WireModuleAuth\Notifications\OneTimeCodeNotification;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeUser;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * The sign-in code, and the address as somebody actually types it.
 *
 * `store()` used to look the user up with the address exactly as typed while
 * filing the code under the lower-cased one. On SQLite — and MySQL's default
 * collation — `LIKE`/`=` ignore case for ASCII and the two agree, which is why
 * this went unnoticed. On PostgreSQL, or MySQL with a binary collation, they
 * come apart: the lookup misses, `SendOneTimeCode` returns false, and the screen
 * says a code was sent that was never minted.
 *
 * The tests below are collation-independent on purpose. Rather than trying to
 * make SQLite case-sensitive, they assert the thing that was actually wrong —
 * that all three of the session key, the lookup and the code's identifier are
 * the same normalised string, whatever case arrives.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::frame();

    config()->set('auth.providers.users.model', CodeUser::class);
    CodeWorld::enable(['login' => true]);

    Notification::fake();
});

it('files the code under one identifier however the address is typed', function () {
    $user = CodeWorld::user();

    $this->post('/login/code', ['email' => 'Ann@Example.COM'])
        ->assertRedirect(route('wire-auth.login-code.challenge'));

    // One row, keyed by the normalised address — not by what the browser sent.
    $rows = DB::table('wire_auth_one_time_codes')->pluck('identifier')->all();

    expect($rows)->toBe(['ann@example.com']);
    Notification::assertSentTo($user, OneTimeCodeNotification::class);
});

it('signs in from a code requested with a differently-cased address', function () {
    $user = CodeWorld::user();

    // The whole journey, mixed case on the way in and the code typed after.
    $this->post('/login/code', ['email' => 'ANN@EXAMPLE.COM']);

    $code = Notification::sent($user, OneTimeCodeNotification::class)->first()->code->code;

    $this->post('/login/code/challenge', ['code' => $code]);

    expect(auth()->check())->toBeTrue()
        ->and(auth()->id())->toBe($user->getKey());
});

it('puts the same identifier in the session as the one the code is filed under', function () {
    CodeWorld::user();

    $this->post('/login/code', ['email' => '  Ann@Example.com  ']);

    // Trimmed as well as lower-cased: `identifierFor()` does both, and the
    // session key has to be the string `verify()` will look the code up by.
    $filed = DB::table('wire_auth_one_time_codes')->value('identifier');

    expect(session(CodeLoginController::SESSION_KEY))->toBe($filed)
        ->and($filed)->toBe('ann@example.com');
});

it('says the same thing for an address that belongs to nobody', function () {
    // The reply must not change with the case either — an address that does not
    // exist and one typed in a different case have to look identical from
    // outside, or the screen becomes an account oracle.
    $this->post('/login/code', ['email' => 'Nobody@Example.com'])
        ->assertRedirect(route('wire-auth.login-code.challenge'))
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});
