<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * The store, which is the whole of the security surface ADR 0037 admits.
 *
 * Everything here is a rule one of the four flows leans on, and each one fails
 * silently if it is wrong: a code that survives being used, a code that outlives
 * its expiry, a code that can be guessed for ever, a code from one flow that
 * opens another.
 */

beforeEach(function () {
    CodeWorld::migrate();

    $this->codes = app(OneTimeCodes::class);
});

it('mints digits of the configured length and stores only a hash', function () {
    $code = $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    expect($code->code)->toMatch('/^\d{6}$/');

    $row = DB::table('wire_auth_one_time_codes')->first();

    // A database copy is a list of dead hashes, not a set of live credentials.
    expect($row->code)->not->toBe($code->code)
        ->and($row->identifier)->toBe('ann@example.com')
        ->and($row->purpose)->toBe('login');
});

it('accepts the right code once', function () {
    $code = $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    expect($this->codes->verify(CodePurpose::Login, 'ann@example.com', $code->code))->not->toBeNull()
        // Verifying consumes: a code that can be tried twice can be tried a
        // thousand times.
        ->and($this->codes->verify(CodePurpose::Login, 'ann@example.com', $code->code))->toBeNull();
});

it('will not open another flow with the same digits', function () {
    $code = $this->codes->issue(CodePurpose::VerifyEmail, 'ann@example.com');

    expect($this->codes->verify(CodePurpose::Login, 'ann@example.com', $code->code))->toBeNull()
        ->and($this->codes->verify(CodePurpose::VerifyEmail, 'ann@example.com', $code->code))->not->toBeNull();
});

it('hands the payload back, and only to the right code', function () {
    $code = $this->codes->issue(CodePurpose::ResetPassword, 'ann@example.com', ['token' => 'broker-token']);

    $verified = $this->codes->verify(CodePurpose::ResetPassword, 'ann@example.com', $code->code);

    expect($verified?->payload('token'))->toBe('broker-token')
        ->and($verified?->payload('nothing'))->toBeNull()
        // The digits are not handed back out: by now they are what the caller
        // typed, and the row never had them.
        ->and($verified?->code)->toBe('');
});

it('treats an expired code as a wrong one, and clears it away', function () {
    $code = $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    $this->travel(11)->minutes();

    expect($this->codes->verify(CodePurpose::Login, 'ann@example.com', $code->code))->toBeNull()
        ->and(DB::table('wire_auth_one_time_codes')->count())->toBe(0);
});

it('throws the code away after too many wrong guesses', function () {
    config()->set('wire-module-auth.codes.attempts', 3);

    $code = $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    $this->codes->verify(CodePurpose::Login, 'ann@example.com', '000000');
    $this->codes->verify(CodePurpose::Login, 'ann@example.com', '000000');

    expect(DB::table('wire_auth_one_time_codes')->value('attempts'))->toBe(2);

    $this->codes->verify(CodePurpose::Login, 'ann@example.com', '000000');

    // Gone rather than merely refused: a code left in the table after its last
    // attempt is one a slow attacker can come back to once the route throttle
    // has forgotten them — so the right digits no longer work either.
    expect(DB::table('wire_auth_one_time_codes')->count())->toBe(0)
        ->and($this->codes->verify(CodePurpose::Login, 'ann@example.com', $code->code))->toBeNull();
});

it('replaces rather than accumulates, so the newest mail is the live one', function () {
    $first = $this->codes->issue(CodePurpose::Login, 'ann@example.com');
    $second = $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    expect(DB::table('wire_auth_one_time_codes')->count())->toBe(1)
        ->and($this->codes->verify(CodePurpose::Login, 'ann@example.com', $first->code))->toBeNull();

    $this->codes->issue(CodePurpose::Login, 'ann@example.com');
    expect($second->code)->not->toBeEmpty();
});

it('knows when one has just gone out', function () {
    $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    expect($this->codes->recentlyIssued(CodePurpose::Login, 'ann@example.com'))->toBeTrue();

    $this->travel(2)->minutes();

    expect($this->codes->recentlyIssued(CodePurpose::Login, 'ann@example.com'))->toBeFalse()
        ->and($this->codes->recentlyIssued(CodePurpose::Login, 'nobody@example.com'))->toBeFalse();
});

it('can be thrown away on the way out of a flow', function () {
    $this->codes->issue(CodePurpose::Login, 'ann@example.com');

    $this->codes->invalidate(CodePurpose::Login, 'ann@example.com');

    expect(DB::table('wire_auth_one_time_codes')->count())->toBe(0);
});
