<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * ADR 0021 asks for the timezone round trip to be asserted against a *real*
 * database, not a unit test: the app zone, the field zone and the column type
 * interact, and SQLite stores datetimes as plain strings — it would happily
 * accept a wrong implementation. These run on the MySQL/MariaDB/PostgreSQL
 * matrix (.github/workflows/database-tests.yml), where DATETIME/TIMESTAMPTZ is
 * a real type that rejects a malformed value instead of storing it verbatim.
 *
 * The bug being guarded against is a *one-sided* conversion: hydrate into the
 * field zone but write back without converting, and every save silently shifts
 * the stored instant by the UTC offset. It compounds on each round trip, which
 * is why the "save twice" case below matters.
 */

class DtpEvent extends Model
{
    protected $table = 'dtp_events';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['event_at' => 'datetime', 'starts_on' => 'date'];
}

class DtpHost extends Component
{
    use WithForms;

    public ?int $eventId = null;

    public string $zone = 'Europe/Prague';

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(DtpEvent::find($this->eventId))
            ->statePath('data')
            ->schema([
                DateTimePicker::make('event_at')
                    ->asDateTime()
                    ->timezone($this->zone)
                    ->format('Y-m-d H:i:s'),
            ]);
    }

    public function mount(): void
    {
        $record = DtpEvent::find($this->eventId);

        $this->form->fill($record ? $record->attributesToArray() : []);
    }

    public function save(): void
    {
        $this->form->save();
    }

    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');

    Schema::create('dtp_events', function (Blueprint $table) {
        $table->id();
        $table->dateTime('event_at')->nullable();
        $table->date('starts_on')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('dtp_events');
});

/** The value as the driver actually returns it, before any Eloquent cast. */
function dtpRaw(int $id): string
{
    $value = DB::table('dtp_events')->where('id', $id)->value('event_at');

    return $value instanceof DateTimeInterface
        ? $value->format('Y-m-d H:i:s')
        : (string) $value;
}

it('writes the app-zone instant to the column, not the field-zone wall clock', function () {
    // 12:00 UTC — the app zone is what Eloquent reads and writes.
    $event = DtpEvent::create(['event_at' => '2026-03-09 12:00:00']);

    $component = Livewire::test(DtpHost::class, ['eventId' => $event->id]);

    // Read path: 12:00 UTC is 13:00 in Prague on 9 March (CET, +1).
    expect($component->get('data.event_at'))->toBe('2026-03-09T13:00');

    // The user leaves it untouched and saves. A one-sided timezone would store
    // 13:00 here — the silent shift ADR 0021 exists to prevent.
    $component->call('save');

    expect(dtpRaw($event->id))->toBe('2026-03-09 12:00:00');
})->group('database');

it('does not drift when the same value is saved repeatedly', function () {
    // The signature of a one-sided conversion: each save moves the instant again.
    $event = DtpEvent::create(['event_at' => '2026-03-09 12:00:00']);

    foreach (range(1, 3) as $_) {
        $component = Livewire::test(DtpHost::class, ['eventId' => $event->id]);
        $component->call('save');
    }

    expect(dtpRaw($event->id))->toBe('2026-03-09 12:00:00');
})->group('database');

it('stores an edited wall-clock time as the correct instant', function () {
    $event = DtpEvent::create(['event_at' => '2026-03-09 12:00:00']);

    Livewire::test(DtpHost::class, ['eventId' => $event->id])
        ->set('data.event_at', '2026-03-09T15:30') // Prague wall clock
        ->call('save');

    // 15:30 Prague (CET, +1) is 14:30 UTC.
    expect(dtpRaw($event->id))->toBe('2026-03-09 14:30:00');
})->group('database');

it('applies the summer-time offset, not a fixed one', function () {
    // 9 July is CEST (+2); 9 March is CET (+1). A hardcoded offset passes one and
    // fails the other, which a single-date test would not reveal.
    $event = DtpEvent::create(['event_at' => '2026-07-09 12:00:00']);

    $component = Livewire::test(DtpHost::class, ['eventId' => $event->id]);
    expect($component->get('data.event_at'))->toBe('2026-07-09T14:00');

    $component->call('save');

    expect(dtpRaw($event->id))->toBe('2026-07-09 12:00:00');
})->group('database');

it('round-trips through the database in a zone the DB itself does not share', function () {
    // The app zone is not UTC and not the field zone: three zones in play, which
    // is where an implementation that leans on the server default falls over.
    config(['app.timezone' => 'America/New_York']);

    $event = DtpEvent::create(['event_at' => '2026-03-09 08:00:00']); // 08:00 NY

    $component = Livewire::test(DtpHost::class, ['eventId' => $event->id, 'zone' => 'Asia/Tokyo']);

    // 08:00 NY (EDT, -4) = 12:00 UTC = 21:00 Tokyo.
    expect($component->get('data.event_at'))->toBe('2026-03-09T21:00');

    $component->call('save');

    // Back to the app zone, unchanged.
    expect(dtpRaw($event->id))->toBe('2026-03-09 08:00:00');
})->group('database');

it('leaves null null instead of writing the epoch', function () {
    $event = DtpEvent::create(['event_at' => null]);

    Livewire::test(DtpHost::class, ['eventId' => $event->id])->call('save');

    expect(DB::table('dtp_events')->where('id', $event->id)->value('event_at'))->toBeNull();
})->group('database');
