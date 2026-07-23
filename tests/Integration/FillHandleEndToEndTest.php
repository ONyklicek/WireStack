<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Whole-system test: a fill driven through the live Livewire component, crossing
 * the request/response boundary that a direct method call never exercises —
 * payload serialization, skipRender, and the per-record version stamps the
 * client needs to reconcile its cells with.
 *
 * Covers text / toggle / select columns, since each dehydrates its state
 * differently and a fill must survive all three.
 */

class FeTicket extends Model
{
    protected $table = 'fe_tickets';

    protected $guarded = [];

    protected $casts = ['urgent' => 'boolean'];
}

class FeTableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FeTicket::class)
            ->paginated(false)
            ->fillHandle()
            ->columns([
                TextInputColumn::make('note'),
                ToggleColumn::make('urgent'),
                SelectColumn::make('state')->options(['open' => 'Open', 'closed' => 'Closed']),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('fe_tickets', function (Blueprint $table) {
        $table->id();
        $table->string('note')->default('');
        $table->boolean('urgent')->default(false);
        $table->string('state')->default('open');
        $table->timestamps();
    });

    foreach ([1, 2, 3] as $id) {
        FeTicket::create(['id' => $id, 'note' => "n{$id}", 'urgent' => false, 'state' => 'open']);
    }
});

afterEach(fn () => Schema::dropIfExists('fe_tickets'));

/**
 * Ordered explicitly: an unordered pluck returns whatever physical order the
 * server happens to hold, and a fill rewrites rows — so on Postgres the updated
 * row moves and an identity comparison against a literal array fails on key
 * order alone.
 *
 * @return array<int, string>
 */
function feNotes(): array
{
    return FeTicket::orderBy('id')->pluck('note', 'id')->all();
}

/**
 * @param  array<int, int>  $ids
 * @return array<int, array<string, mixed>>
 */
function feFill(string $column, mixed $value, array $ids): array
{
    return [[
        'column' => $column,
        'value' => $value,
        'records' => array_fill_keys(array_map('strval', $ids), null),
    ]];
}

it('fills a text column across rows through the live component', function () {
    Livewire::test(FeTableComponent::class)
        ->call('fillTableCells', feFill('note', 'copied', [1, 2, 3]))
        ->assertReturned(fn (array $r) => $r['success'] === true);

    expect(feNotes())
        ->toBe([1 => 'copied', 2 => 'copied', 3 => 'copied']);
});

it('fills toggle and select columns through the live component', function () {
    Livewire::test(FeTableComponent::class)
        ->call('fillTableCells', feFill('urgent', true, [1, 2]))
        ->assertReturned(fn (array $r) => $r['success'] === true)
        ->call('fillTableCells', feFill('state', 'closed', [1, 2]))
        ->assertReturned(fn (array $r) => $r['success'] === true);

    $first = FeTicket::find(1);
    expect($first->urgent)->toBeTrue()
        ->and($first->state)->toBe('closed')
        ->and(FeTicket::find(3)->urgent)->toBeFalse();   // untouched
});

it('writes several columns in one request', function () {
    $payload = [
        ['column' => 'note', 'value' => 'batch', 'records' => ['1' => null, '2' => null]],
        ['column' => 'state', 'value' => 'closed', 'records' => ['1' => null, '2' => null]],
    ];

    Livewire::test(FeTableComponent::class)
        ->call('fillTableCells', $payload)
        ->assertReturned(fn (array $r) => $r['success'] === true
            && count($r['results']) === 2);

    $first = FeTicket::find(1);
    expect($first->note)->toBe('batch')->and($first->state)->toBe('closed');
});

// The pipeline a real session runs: fill, take the versions the response handed
// back, fill again with those, repeat. Every round must land, and the rows must
// agree with the last value — this is the sequence the browser broke by firing
// the second request before the first had answered.
it('chains repeated fills through the live component, each using the last answer', function () {
    $test = Livewire::test(FeTableComponent::class);

    $versions = [];
    foreach (FeTicket::orderBy('id')->get() as $ticket) {
        $versions[(string) $ticket->getKey()] = app(RecordVersion::class)->stamp($ticket);
    }

    foreach (['one', 'two', 'three', 'four'] as $value) {
        // Each round moves the clock on, so a version genuinely goes stale if a
        // round were to reuse an older one.
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $answer = null;

        $test->call('fillTableCells', [[
            'column' => 'note',
            'value' => $value,
            'records' => $versions,
        ]])->assertReturned(function (array $result) use (&$answer): bool {
            $answer = $result;

            return $result['success'] === true;
        });

        $versions = [];
        foreach ($answer['results']['note'] as $key => $row) {
            $versions[(string) $key] = $row['version'];
        }
    }

    Carbon::setTestNow();

    expect(feNotes())->toBe([1 => 'four', 2 => 'four', 3 => 'four']);
});

it('rejects only the row whose version moved underneath the client', function () {
    $stale = app(RecordVersion::class)->stamp(FeTicket::find(2));

    // A concurrent edit moves row 2 forward; rows 1 and 3 are still current.
    FeTicket::find(2)->forceFill(['updated_at' => Carbon::now()->addMinutes(5)])->saveQuietly();

    $versions = [];
    foreach (FeTicket::orderBy('id')->get() as $ticket) {
        $versions[(string) $ticket->getKey()] = app(RecordVersion::class)->stamp($ticket);
    }
    $versions['2'] = $stale;

    Livewire::test(FeTableComponent::class)
        ->call('fillTableCells', [['column' => 'note', 'value' => 'copied', 'records' => $versions]])
        ->assertReturned(fn (array $r) => $r['success'] === false
            && $r['results']['note']['2']['conflict'] === true);

    expect(feNotes())
        ->toBe([1 => 'copied', 2 => 'n2', 3 => 'copied']);
});
