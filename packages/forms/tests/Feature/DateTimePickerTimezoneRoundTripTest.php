<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireForms\Components\DateTimePicker;

/*
 * timezone() round trip through a real database column (ADR 0021).
 *
 * The unit tests next door prove hydrateState()/dehydrateState() convert; they
 * cannot prove the value survives a round trip through a column, where the app
 * zone, the field zone and Eloquent's cast all meet. Runs on the MySQL/Postgres
 * matrix (database-tests.yml).
 *
 * Measured, not assumed: injecting the one-sided conversion fails 3 of these 6
 * on SQLite, MySQL 8 and Postgres 16 alike — the field converts in PHP and stores
 * a plain string, so for a dateTime column this is driver-independent. The matrix
 * run still earns its seconds against a driver that coerces on write (a Postgres
 * timestamptz would), but do not expect SQLite to be the weak one here.
 *
 * The bug being guarded: converting inbound only. State would land in the
 * field's zone, then be written straight back and read as if it were the app's —
 * silently shifting the time by the offset, once per save.
 */

class TzEvent extends Model
{
    protected $table = 'tz_events';

    protected $guarded = [];

    protected $casts = ['starts_at' => 'datetime'];
}

beforeEach(function () {
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');

    Schema::create('tz_events', function (Blueprint $table) {
        $table->id();
        $table->dateTime('starts_at')->nullable();
        $table->timestamps();
    });
});

afterEach(fn () => Schema::dropIfExists('tz_events'));

/** The value as the database actually holds it, past Eloquent's cast. */
function storedStartsAt(): string
{
    return (string) DB::table('tz_events')->orderByDesc('id')->value('starts_at');
}

it('stores the instant the user picked, not the wall clock they saw', function () {
    // Prague is UTC+1 on this date, so 13:00 local is 12:00 UTC.
    $field = DateTimePicker::make('starts_at')->asDateTime()->timezone('Europe/Prague')->format('Y-m-d H:i:s');

    $record = TzEvent::create(['starts_at' => $field->dehydrateState('2026-03-09T13:00')]);

    expect(storedStartsAt())->toStartWith('2026-03-09 12:00')
        ->and($record->fresh()->starts_at->format('H:i'))->toBe('12:00');
});

it('shows the stored instant back in the field timezone', function () {
    TzEvent::create(['starts_at' => '2026-03-09 12:00:00']);

    $field = DateTimePicker::make('starts_at')->asDateTime()->timezone('Europe/Prague');

    expect($field->hydrateState(TzEvent::first()->starts_at->format('Y-m-d\TH:i')))->toBe('2026-03-09T13:00');
});

// The regression that matters: a one-sided conversion drifts by the offset on
// every save, so the same value written twice must not move.
it('does not drift when a loaded value is saved again untouched', function () {
    $field = DateTimePicker::make('starts_at')->asDateTime()->timezone('Europe/Prague')->format('Y-m-d H:i:s');

    TzEvent::create(['starts_at' => '2026-03-09 12:00:00']);

    for ($i = 0; $i < 3; $i++) {
        $record = TzEvent::first();
        $state = $field->hydrateState($record->starts_at->format('Y-m-d\TH:i'));
        $record->update(['starts_at' => $field->dehydrateState($state)]);
    }

    expect(storedStartsAt())->toStartWith('2026-03-09 12:00');
});

it('honours a summer-time offset, not a fixed one', function () {
    // Prague is +2 in July and +1 in March; a hardcoded offset would fail one.
    $field = DateTimePicker::make('starts_at')->asDateTime()->timezone('Europe/Prague')->format('Y-m-d H:i:s');

    TzEvent::create(['starts_at' => $field->dehydrateState('2026-07-09T14:00')]);

    expect(storedStartsAt())->toStartWith('2026-07-09 12:00');
});

it('leaves a value alone when no timezone is configured', function () {
    $field = DateTimePicker::make('starts_at')->asDateTime()->format('Y-m-d H:i:s');

    TzEvent::create(['starts_at' => $field->dehydrateState('2026-03-09T12:00')]);

    expect(storedStartsAt())->toStartWith('2026-03-09 12:00');
});

it('keeps a date-only field on its calendar day across a zone', function () {
    // A bare date has no instant: shifting it by an offset would move the day,
    // which is why dehydrateState() only converts a datetime.
    $field = DateTimePicker::make('starts_at')->asDate()->timezone('Europe/Prague')->format('Y-m-d');

    TzEvent::create(['starts_at' => $field->dehydrateState('2026-03-09')]);

    expect(storedStartsAt())->toStartWith('2026-03-09');
});
