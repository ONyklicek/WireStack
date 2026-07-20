<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\KeyValue;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Regression: a form field bound to an `array`/`json`-cast column was
 * double-JSON-encoded on save — the dehydrator pre-encoded the array to a
 * string and Eloquent's cast encoded it again, so the column read back as a
 * string instead of an array. SQLite stores JSON as plain text and would
 * happily accept the corrupt value, so this is asserted end-to-end.
 */

class AcrtEvent extends Model
{
    protected $table = 'acrt_events';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['meta' => 'array'];
}

class AcrtHost extends Component
{
    use WithForms;

    public ?int $eventId = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(AcrtEvent::find($this->eventId))
            ->statePath('data')
            ->schema([KeyValue::make('meta')]);
    }

    public function mount(): void
    {
        $record = AcrtEvent::find($this->eventId);
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
    Schema::create('acrt_events', function (Blueprint $table) {
        $table->id();
        $table->json('meta')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('acrt_events');
});

it('round-trips an array-cast column as an array, not a double-encoded string', function () {
    $event = AcrtEvent::create(['meta' => ['x' => 'y']]);

    Livewire::test(AcrtHost::class, ['eventId' => $event->id])
        ->set('data.meta', ['a' => 'b', 'c' => 'd'])
        ->call('save');

    // Raw column: a single JSON object, not a JSON-encoded string literal. Decode
    // and compare as an array so the assertion is driver-agnostic — MySQL's native
    // JSON type re-serializes with whitespace and may reorder keys. The guard still
    // holds: a double-encoded value would decode to a string, not an array.
    $raw = DB::table('acrt_events')->where('id', $event->id)->value('meta');
    expect(json_decode($raw, true))->toEqual(['a' => 'b', 'c' => 'd']);

    // Cast read-back yields the array.
    expect($event->fresh()->meta)->toEqual(['a' => 'b', 'c' => 'd']);
});

it('does not compound the encoding across repeated saves', function () {
    $event = AcrtEvent::create(['meta' => ['k' => 'v']]);

    foreach (range(1, 3) as $_) {
        Livewire::test(AcrtHost::class, ['eventId' => $event->id])->call('save');
    }

    expect($event->fresh()->meta)->toBe(['k' => 'v']);
});
