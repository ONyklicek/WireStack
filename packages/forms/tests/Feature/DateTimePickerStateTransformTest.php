<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireForms\Components\DateTimePicker;

/*
 * ADR 0021: format() and timezone() were dead because the save path had no seam
 * for a field to act on. These pin the round trip in both directions — a
 * one-sided timezone would silently shift the stored time, which is the whole
 * reason the contract has two halves.
 */

function dtpRecord(): Model
{
    return new class extends Model
    {
        protected $guarded = [];
    };
}

// ─── format(): how the value is stored ──────────────────────────────────────

it('stores the value in the configured format', function () {
    $field = DateTimePicker::make('event_at')->asDate()->format('d/m/Y');

    expect($field->dehydrateState('2026-03-09'))->toBe('09/03/2026');
});

// The BC guard: getFormat() falls back to config('wire-forms.date_format')
// ('d.m.Y' by default), so reformatting without an explicit format() would make
// every existing field silently start writing a different shape to its column.
it('stores the state untouched when format() was never called', function () {
    expect(DateTimePicker::make('d')->asDate()->dehydrateState('2026-03-09'))->toBe('2026-03-09')
        ->and(DateTimePicker::make('d')->asMonth()->dehydrateState('2026-03'))->toBe('2026-03')
        ->and(DateTimePicker::make('d')->asDateTime()->dehydrateState('2026-03-09T12:00'))->toBe('2026-03-09T12:00');
});

it('keeps null as null rather than inventing a date', function () {
    expect(DateTimePicker::make('d')->asDate()->dehydrateState(null))->toBeNull()
        ->and(DateTimePicker::make('d')->asDate()->dehydrateState(''))->toBeNull();
});

it('passes an unparseable value through untouched instead of throwing', function () {
    expect(DateTimePicker::make('d')->asDate()->dehydrateState('not a date'))->toBe('not a date');
});

it('returns an unparseable value verbatim when a format forces a parse attempt', function () {
    // With a display format set, dehydrateState() parses the state to reformat
    // it. An unparseable value makes the parse throw; it is caught and the raw
    // state is handed back unchanged rather than fataling the save.
    expect(DateTimePicker::make('d')->format('d/m/Y')->dehydrateState('zzz not a date zzz'))
        ->toBe('zzz not a date zzz');
});

// ─── timezone(): both directions, or the time moves ─────────────────────────

it('shows a stored app-zone datetime in the field timezone', function () {
    config(['app.timezone' => 'UTC']);

    $field = DateTimePicker::make('event_at')->asDateTime()->timezone('Europe/Prague');

    // 12:00 UTC is 14:00 in Prague (CEST, +2 in March 9? -> +1 CET).
    expect($field->hydrateState('2026-03-09T12:00'))->toBe('2026-03-09T13:00');
});

it('converts the picked value back to the app zone on save', function () {
    config(['app.timezone' => 'UTC']);

    $field = DateTimePicker::make('event_at')->asDateTime()->timezone('Europe/Prague')->format('Y-m-d H:i');

    expect($field->dehydrateState('2026-03-09T13:00'))->toBe('2026-03-09 12:00');
});

it('round-trips a datetime through both halves without drifting', function () {
    config(['app.timezone' => 'UTC']);

    $field = DateTimePicker::make('event_at')->asDateTime()->timezone('Europe/Prague')->format('Y-m-d\TH:i');

    $stored = '2026-03-09T12:00';
    $state = $field->hydrateState($stored);

    // The half that matters: what goes back must equal what came out.
    expect($field->dehydrateState($state))->toBe($stored);
});

it('leaves a wall-clock value alone — a date has no instant to shift', function () {
    config(['app.timezone' => 'UTC']);

    // Converting a bare date by a timezone would move the day; a time-only value
    // has no date to anchor a conversion to.
    $date = DateTimePicker::make('d')->asDate()->timezone('Europe/Prague');
    $time = DateTimePicker::make('t')->asTime()->timezone('Europe/Prague')->format('H:i');

    expect($date->hydrateState('2026-03-09'))->toBe('2026-03-09')
        ->and($date->dehydrateState('2026-03-09'))->toBe('2026-03-09')
        ->and($time->hydrateState('23:30'))->toBe('23:30')
        ->and($time->dehydrateState('23:30'))->toBe('23:30');
});

it('does nothing when no timezone is configured', function () {
    config(['app.timezone' => 'UTC']);

    $field = DateTimePicker::make('event_at')->asDateTime();

    expect($field->hydrateState('2026-03-09T12:00'))->toBe('2026-03-09T12:00');
});
