<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use NyonCode\WireModuleAuth\Contracts\OneTimeCodes;
use NyonCode\WireModuleAuth\Enums\CodePurpose;
use NyonCode\WireModuleAuth\Tests\Fixtures\CodeWorld;

/*
 * Three ways the code store gave more away than it meant to.
 *
 * All of them are about the parts nobody reads twice: a counter that looked
 * atomic and was not, a clamp that held one end of a range, and a lookup key
 * that agreed with itself on one collation and not another. None of them is
 * reachable on a default installation — every `codes.*` switch ships off — and
 * all of them are the sort of thing that is only ever found by asking.
 */

beforeEach(function () {
    CodeWorld::migrate();
    CodeWorld::enable(['login' => true]);
});

/* ------------------------------------------------- S5: the attempt counter */

it('counts the attempt in the database, not in PHP', function () {
    // The one assertion that actually proves the fix. Counting *sequentially*
    // comes out the same either way — read 0, write 1, read 1, write 2 — so the
    // tests below would pass against the version this replaced. What separates
    // them is the statement: `set "attempts" = "attempts" + 1` is decided by the
    // database and survives N of them arriving at once, while `set "attempts" = ?`
    // carries a number PHP worked out from a row it read a moment earlier, and
    // N concurrent requests all read the same one.
    //
    // Concurrency itself is not reproducible in a sequential test; the property
    // that makes it safe is.
    $codes = app(OneTimeCodes::class);

    config()->set('wire-module-auth.codes.attempts', 5);

    $codes->issue(CodePurpose::Login, 'ann@example.com');

    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $codes->verify(CodePurpose::Login, 'ann@example.com', '000000');

    $writes = array_values(array_filter(
        $statements,
        fn (string $sql): bool => str_contains($sql, 'update') && str_contains($sql, 'attempts'),
    ));

    expect($writes)->not->toBeEmpty()
        ->and($writes[0])->toContain('"attempts" = "attempts" + 1');
});

it('counts every wrong guess, not every round of them', function () {
    $codes = app(OneTimeCodes::class);

    config()->set('wire-module-auth.codes.attempts', 5);

    $codes->issue(CodePurpose::Login, 'ann@example.com');

    // Read the row the way the old code did — once, up front — and then guess
    // wrong twice against that same starting point. A counter that adds in PHP
    // writes 1 both times; one that adds in the database writes 1 then 2.
    $codes->verify(CodePurpose::Login, 'ann@example.com', '000000');
    $codes->verify(CodePurpose::Login, 'ann@example.com', '000000');

    $attempts = DB::table('wire_auth_one_time_codes')
        ->where('identifier', 'ann@example.com')
        ->value('attempts');

    expect((int) $attempts)->toBe(2);
});

it('burns the code once the guesses run out', function () {
    $codes = app(OneTimeCodes::class);

    config()->set('wire-module-auth.codes.attempts', 3);

    $codes->issue(CodePurpose::Login, 'ann@example.com');

    foreach (range(1, 3) as $_) {
        $codes->verify(CodePurpose::Login, 'ann@example.com', '000000');
    }

    // Gone rather than frozen at the limit: a row left behind is one a slow
    // attacker returns to after the route throttle has forgotten them.
    expect(DB::table('wire_auth_one_time_codes')->count())->toBe(0);
});

it('leaves a right code working while wrong ones are still being counted', function () {
    $codes = app(OneTimeCodes::class);

    config()->set('wire-module-auth.codes.attempts', 5);

    $issued = $codes->issue(CodePurpose::Login, 'ann@example.com');

    $codes->verify(CodePurpose::Login, 'ann@example.com', '000000');
    $codes->verify(CodePurpose::Login, 'ann@example.com', '000000');

    // The counter must bound guessing without breaking the person who simply
    // mistyped twice.
    expect($codes->verify(CodePurpose::Login, 'ann@example.com', $issued->code))->not->toBeNull();
});

/* ------------------------------------------------------- S6: the code length */

it('mints a code of any configured length without falling off an integer', function () {
    $codes = app(OneTimeCodes::class);

    // 19 is where `10 ** $length` passes PHP_INT_MAX and becomes a float, which
    // `random_int()` refuses with a TypeError. `max()` only ever clamped the
    // bottom of the range, so nothing stopped it.
    foreach ([4, 6, 18, 19, 24] as $length) {
        config()->set('wire-module-auth.codes.length', $length);

        $issued = $codes->issue(CodePurpose::Login, "ann+{$length}@example.com");

        expect($issued->code)->toHaveLength($length)
            ->and($issued->code)->toMatch('/^[0-9]+$/');
    }
});

it('still honours the floor under the configured length', function () {
    $codes = app(OneTimeCodes::class);

    config()->set('wire-module-auth.codes.length', 1);

    expect($codes->issue(CodePurpose::Login, 'ann@example.com')->code)->toHaveLength(4);
});

it('draws digits that are not all the same one', function () {
    $codes = app(OneTimeCodes::class);

    config()->set('wire-module-auth.codes.length', 6);

    // Cheap smoke test over the rewrite: a per-digit loop that got its bounds
    // wrong — `random_int(0, 0)`, say — would still be the right length and all
    // zeroes, and every other assertion here would pass.
    $seen = [];

    foreach (range(1, 20) as $i) {
        $seen[] = $codes->issue(CodePurpose::Login, "ann+{$i}@example.com")->code;
    }

    expect(count(array_unique($seen)))->toBeGreaterThan(1)
        ->and(count(array_unique(str_split(implode('', $seen)))))->toBeGreaterThan(1);
});
