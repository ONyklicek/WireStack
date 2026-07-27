<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use NyonCode\WireCore\Exceptions\InvalidDateBoundaryException;
use NyonCode\WireCore\Foundation\Support\DateBoundary;

// ─── Reading the owner's bound ──────────────────────────────────────────────

it('returns null for an absent bound', function () {
    expect(DateBoundary::min(null, 'Y-m-d'))->toBeNull()
        ->and(DateBoundary::min('', 'Y-m-d'))->toBeNull()
        ->and(DateBoundary::max(null, 'Y-m-d'))->toBeNull()
        ->and(DateBoundary::max('', 'Y-m-d'))->toBeNull();
});

it('reformats a bound into the widget format', function () {
    expect(DateBoundary::min('2026-07-10', 'Y-m-d'))->toBe('2026-07-10')
        ->and(DateBoundary::min('2026-07-10', 'Y-m'))->toBe('2026-07')
        ->and(DateBoundary::min('2026-07-10 08:30', 'Y-m-d\TH:i'))->toBe('2026-07-10T08:30')
        ->and(DateBoundary::min('2026-07-10', 'Y-m-d\TH:i'))->toBe('2026-07-10T00:00');
});

it('reads the date shapes an owner would reasonably write', function () {
    expect(DateBoundary::min('10.07.2026', 'Y-m-d'))->toBe('2026-07-10')
        ->and(DateBoundary::min('2026/07/10', 'Y-m-d'))->toBe('2026-07-10')
        ->and(DateBoundary::min(Carbon::parse('2026-07-10 08:30'), 'Y-m-d\TH:i'))->toBe('2026-07-10T08:30')
        ->and(DateBoundary::min(CarbonImmutable::parse('2026-07-10'), 'Y-m-d'))->toBe('2026-07-10')
        ->and(DateBoundary::min(new DateTimeImmutable('2026-07-10'), 'Y-m-d'))->toBe('2026-07-10');
});

it('reads a relative bound against today', function () {
    Carbon::setTestNow('2026-07-27 12:00:00');

    expect(DateBoundary::min('today', 'Y-m-d'))->toBe('2026-07-27')
        ->and(DateBoundary::min('+1 week', 'Y-m-d'))->toBe('2026-08-03');

    Carbon::setTestNow();
});

it('refuses a bound it cannot read', function () {
    DateBoundary::min('not a date at all', 'Y-m-d');
})->throws(InvalidDateBoundaryException::class, 'not a date at all');

it('refuses a bound of an unsupported type', function () {
    DateBoundary::max(['2026-07-10'], 'Y-m-d');
})->throws(InvalidDateBoundaryException::class, 'array');

// ─── An upper bound covers its whole day ────────────────────────────────────

it('carries a day-granular upper bound to the end of that day', function () {
    expect(DateBoundary::max('2026-07-20', 'Y-m-d\TH:i'))->toBe('2026-07-20T23:59')
        ->and(DateBoundary::max('2026-07-20', 'Y-m-d\TH:i:s'))->toBe('2026-07-20T23:59:59');
});

it('leaves an upper bound that names a time alone', function () {
    expect(DateBoundary::max('2026-07-20 17:30', 'Y-m-d\TH:i'))->toBe('2026-07-20T17:30');
});

it('only stretches the day on a widget that shows a date and a time', function () {
    // A date-only widget drops the time anyway; on a time-only one midnight is
    // the owner's actual choice, not an unspoken "end of day".
    expect(DateBoundary::max('2026-07-20', 'Y-m-d'))->toBe('2026-07-20')
        ->and(DateBoundary::max('00:00', 'H:i'))->toBe('00:00');
});

// ─── Splitting a bound for the calendar and the clock ───────────────────────

it('splits a bound into its day and its time', function () {
    expect(DateBoundary::datePart('2026-07-10T08:30'))->toBe('2026-07-10')
        ->and(DateBoundary::timePart('2026-07-10T08:30'))->toBe('08:30:00')
        ->and(DateBoundary::datePart('2026-07-10 08:30:45'))->toBe('2026-07-10')
        ->and(DateBoundary::timePart('2026-07-10 08:30:45'))->toBe('08:30:45');
});

it('reports the half a bound does not carry as absent', function () {
    expect(DateBoundary::datePart('08:30'))->toBeNull()
        ->and(DateBoundary::timePart('2026-07-10'))->toBeNull()
        ->and(DateBoundary::datePart(null))->toBeNull()
        ->and(DateBoundary::timePart(null))->toBeNull()
        ->and(DateBoundary::timePart('2026-07'))->toBeNull();
});

it('reads a time-only bound as a time', function () {
    expect(DateBoundary::timePart('08:30'))->toBe('08:30:00')
        ->and(DateBoundary::timePart('08:30:45'))->toBe('08:30:45');
});
