<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\KeyValue;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/*
 * Seam test: the whole save pipeline (Livewire state → validate → dehydrateFields
 * → ValueTransformer → Model::setAttribute → save → Eloquent cast read-back) for
 * each Eloquent cast type. Isolated unit tests of the transformer pinned its
 * intermediate output (which for array/json was the buggy double-encoded string);
 * only a round-trip against a real column proves the contract end to end.
 */

class CrtRecord extends Model
{
    protected $table = 'crt_records';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'qty' => 'integer',
        'active' => 'boolean',
        'meta' => 'array',
        'config' => 'json',
        'labels' => 'collection',
    ];
}

class CrtHost extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->model(CrtRecord::class)
            ->statePath('data')
            ->schema([
                TextInput::make('title'),
                TextInput::make('qty'),
                Toggle::make('active'),
                KeyValue::make('meta'),
                KeyValue::make('config'),
                KeyValue::make('labels'),
            ]);
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
    Schema::create('crt_records', function (Blueprint $t) {
        $t->id();
        $t->string('title')->nullable();
        $t->integer('qty')->nullable();
        $t->boolean('active')->default(false);
        $t->json('meta')->nullable();
        $t->json('config')->nullable();
        $t->json('labels')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('crt_records');
});

it('round-trips every cast type through the full save pipeline', function () {
    Livewire::test(CrtHost::class)
        ->set('data.title', 'Hello')
        ->set('data.qty', '42')               // string in → int column
        ->set('data.active', true)
        ->set('data.meta', ['a' => 'b', 'c' => 'd'])
        ->set('data.config', ['x' => '1'])
        ->set('data.labels', ['one' => 'uno'])
        ->call('save')
        ->assertHasNoErrors();

    $fresh = CrtRecord::first();

    // Scalars keep their cast types.
    expect($fresh->title)->toBe('Hello')
        ->and($fresh->qty)->toBe(42)
        ->and($fresh->active)->toBeTrue();

    // array / json / collection casts read back as their PHP type, not a string.
    // toEqual (not toBe) so JSON object key order stays driver-agnostic — MySQL's
    // native JSON type may reorder keys on storage.
    expect($fresh->meta)->toEqual(['a' => 'b', 'c' => 'd'])
        ->and($fresh->config)->toEqual(['x' => '1'])
        ->and($fresh->labels)->toBeInstanceOf(Collection::class)
        ->and($fresh->labels->toArray())->toEqual(['one' => 'uno']);
});

it('stores JSON-cast columns single-encoded (not a double-encoded string)', function () {
    Livewire::test(CrtHost::class)
        ->set('data.meta', ['a' => 'b'])
        ->set('data.config', ['x' => '1'])
        ->set('data.labels', ['k' => 'v'])
        ->call('save');

    $raw = DB::table('crt_records')->first();

    // A single JSON object — decode and compare as an array so the assertion is
    // driver-agnostic (MySQL's native JSON re-serializes with whitespace and may
    // reorder keys). The guard still holds: a double-encoded value would decode to
    // a string, not an array (the buggy shape was "\"{\\\"a\\\":\\\"b\\\"}\"").
    expect(json_decode($raw->meta, true))->toEqual(['a' => 'b'])
        ->and(json_decode($raw->config, true))->toEqual(['x' => '1'])
        ->and(json_decode($raw->labels, true))->toEqual(['k' => 'v']);
});

it('does not compound the encoding across repeated saves of a cast column', function () {
    $record = CrtRecord::create(['meta' => ['n' => 1]]);

    foreach (range(1, 3) as $_) {
        Livewire::test(CrtHost::class)->call('save');
    }

    // The value is untouched and still an array, not a re-encoded string.
    expect(CrtRecord::find($record->id)->meta)->toBe(['n' => 1]);
});
