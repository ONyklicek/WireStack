<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The fill endpoint: one request writing one value to many records.
 *
 * Most of what matters here is what must NOT happen — a key outside the table's
 * own query is never written, a column that opted out is never written, and one
 * record losing its optimistic-lock race does not discard the rows that landed.
 */

class FhTask extends Model
{
    protected $table = 'fh_tasks';

    protected $guarded = [];
}

class FhComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhTask::class)
            ->paginated(false)
            ->fillHandle()
            ->columns([
                TextInputColumn::make('status'),
                TextInputColumn::make('code')->fillable(false),
                TextColumn::make('title'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Fill is off — the default. */
class FhDisabledComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhTask::class)
            ->paginated(false)
            ->columns([TextInputColumn::make('status')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Scoped to team 1 — the base query is the write boundary. */
class FhScopedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(FhTask::query()->where('team_id', 1))
            ->paginated(false)
            ->fillHandle()
            ->columns([TextInputColumn::make('status')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Rules that need no record, so the refusal happens once for the whole column. */
class FhRulesComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhTask::class)
            ->paginated(false)
            ->fillHandle()
            ->columns([TextColumn::make('status')->editable()->editableRules(fn () => ['required'])]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class FhCappedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhTask::class)
            ->paginated(false)
            ->fillHandle()
            ->fillMaxRecords(2)
            ->columns([TextInputColumn::make('status')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class FhPermissionComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(FhTask::class)
            ->paginated(false)
            ->fillHandle()
            ->columns([TextInputColumn::make('status')->permission('edit-status')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('fh_tasks', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status')->default('open');
        $table->string('code')->default('');
        $table->unsignedInteger('team_id')->default(1);
        $table->timestamps();
    });

    foreach ([1, 2, 3] as $id) {
        FhTask::create(['id' => $id, 'title' => "Task {$id}", 'status' => 'open', 'code' => "C{$id}"]);
    }

    FhTask::create(['id' => 9, 'title' => 'Other team', 'status' => 'open', 'code' => 'C9', 'team_id' => 2]);
});

afterEach(fn () => Schema::dropIfExists('fh_tasks'));

/**
 * @param  class-string<Component>  $class
 */
function fhComponent(string $class = FhComponent::class): Component
{
    $component = new $class;
    $component->mountWithTable();

    return $component;
}

/**
 * One fill entry, no client-held versions.
 *
 * @param  array<int, int|string>  $keys
 * @return array<int, array<string, mixed>>
 */
function fhFill(string $column, mixed $value, array $keys): array
{
    return [[
        'column' => $column,
        'value' => $value,
        'records' => array_fill_keys(array_map('strval', $keys), null),
    ]];
}

/**
 * One fill entry carrying an explicit version per record.
 *
 * @param  array<int|string, string|null>  $versions  record key => version
 * @return array<int, array<string, mixed>>
 */
function fhFillVersioned(string $column, mixed $value, array $versions): array
{
    $records = [];

    foreach ($versions as $key => $version) {
        $records[(string) $key] = $version;
    }

    return [['column' => $column, 'value' => $value, 'records' => $records]];
}

/**
 * The versions the client would be holding right now.
 *
 * @param  array<int, int>  $ids
 * @return array<string, string|null>
 */
function fhVersions(array $ids): array
{
    $version = app(RecordVersion::class);
    $held = [];

    foreach (FhTask::whereIn('id', $ids)->orderBy('id')->get() as $task) {
        $held[(string) $task->getKey()] = $version->stamp($task);
    }

    return $held;
}

/** @return array<string, string> id => status */
function fhStatuses(): array
{
    return FhTask::orderBy('id')->pluck('status', 'id')->all();
}

// ─── The happy path ──────────────────────────────────────────────────────────

it('writes one value to every named record in a single request', function () {
    Livewire::test(FhComponent::class)
        ->call('fillTableCells', fhFill('status', 'done', [1, 2, 3]))
        ->assertReturned(fn (array $r) => $r['success'] === true && $r['message'] === null);

    expect(fhStatuses())->toBe([1 => 'done', 2 => 'done', 3 => 'done', 9 => 'open']);
});

it('hands back a new version per record so each cell can reconcile', function () {
    $result = fhComponent()->fillTableCells(fhFill('status', 'done', [1, 2]));

    expect($result['results']['status']['1']['version'])
        ->toBe(app(RecordVersion::class)->stamp(FhTask::find(1)))
        ->and($result['results']['status']['2']['version'])
        ->toBe(app(RecordVersion::class)->stamp(FhTask::find(2)));
});

it('announces every filled cell the way a single edit does', function () {
    Event::fake([CellUpdated::class]);

    fhComponent()->fillTableCells(fhFill('status', 'done', [1, 2]));

    Event::assertDispatchedTimes(CellUpdated::class, 2);
    Event::assertDispatched(CellUpdated::class, fn (CellUpdated $e) => $e->column === 'status'
        && $e->oldValue === 'open'
        && $e->newValue === 'done');
});

// ─── Refusals ────────────────────────────────────────────────────────────────

it('refuses outright when the table never offered a fill handle', function () {
    Livewire::test(FhDisabledComponent::class)
        ->call('fillTableCells', fhFill('status', 'done', [1]))
        ->assertReturned(fn (array $r) => $r['success'] === false
            && $r['message'] === __('wire-table::messages.fill_not_enabled'));

    expect(FhTask::find(1)->status)->toBe('open');
});

it('refuses a column that opted out of filling', function () {
    $result = fhComponent()->fillTableCells(fhFill('code', 'X', [1, 2]));

    expect($result['success'])->toBeFalse()
        ->and($result['results']['code']['1']['message'])->toBe(__('wire-table::messages.column_not_fillable'))
        ->and($result['results']['code']['2']['message'])->toBe(__('wire-table::messages.column_not_fillable'))
        ->and(FhTask::find(1)->code)->toBe('C1');
});

it('refuses a column that is not editable at all', function () {
    $result = fhComponent()->fillTableCells(fhFill('title', 'X', [1]));

    expect($result['results']['title']['1']['message'])->toBe(__('wire-table::messages.column_not_fillable'))
        ->and(FhTask::find(1)->title)->toBe('Task 1');
});

it('refuses an unknown column', function () {
    $result = fhComponent()->fillTableCells(fhFill('nope', 'X', [1]));

    expect($result['results']['nope']['1']['message'])->toBe(__('wire-table::messages.column_not_found'));
});

it('refuses a column the user may not write, without touching a row', function () {
    Gate::define('edit-status', fn () => false);

    $result = fhComponent(FhPermissionComponent::class)->fillTableCells(fhFill('status', 'done', [1, 2]));

    expect($result['success'])->toBeFalse()
        ->and($result['results']['status']['1']['message'])->toBe(__('wire-table::messages.no_permission_view'))
        ->and(fhStatuses())->toBe([1 => 'open', 2 => 'open', 3 => 'open', 9 => 'open']);
});

// The value is identical for every row, so a rule that needs no record refuses
// the whole column once — before a single record is fetched or locked.
it('refuses the whole column when the shared value fails the record-less rules', function () {
    $result = fhComponent(FhRulesComponent::class)->fillTableCells(fhFill('status', '', [1, 2, 3]));

    expect($result['success'])->toBeFalse()
        ->and($result['results']['status']['1']['errors'])->not->toBeEmpty()
        ->and($result['results']['status'])->toHaveCount(3)
        ->and(fhStatuses())->toBe([1 => 'open', 2 => 'open', 3 => 'open', 9 => 'open']);
});

// ─── The write boundary ──────────────────────────────────────────────────────

// Every record is resolved through the table's own query, so a key the table
// never showed matches nothing. Skipping that is how the same feature became an
// IDOR write in WithSortable::reorderRows().
it('never writes a record outside the table query, even when named explicitly', function () {
    $result = fhComponent(FhScopedComponent::class)->fillTableCells(fhFill('status', 'done', [1, 9]));

    expect($result['success'])->toBeFalse()
        ->and($result['results']['status']['1']['success'])->toBeTrue()
        ->and($result['results']['status']['9']['message'])->toBe(__('wire-table::messages.record_not_found'))
        ->and(FhTask::find(9)->status)->toBe('open');
});

it('caps how many rows one request may write, writing none when exceeded', function () {
    $result = fhComponent(FhCappedComponent::class)->fillTableCells(fhFill('status', 'done', [1, 2, 3]));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('at most 2 rows')
        ->and(fhStatuses())->toBe([1 => 'open', 2 => 'open', 3 => 'open', 9 => 'open']);
});

// ─── Partial failure ─────────────────────────────────────────────────────────

// A fill is deliberately not all-or-nothing: one row losing the race must not
// discard the others, or a single stale cell would silently undo the whole drag.
it('keeps the rows that landed when one loses its optimistic-lock race', function () {
    $result = fhComponent()->fillTableCells([[
        'column' => 'status',
        'value' => 'done',
        'records' => ['1' => '1', '2' => null, '3' => null],   // '1' is a stale version
    ]]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe(__('wire-table::messages.fill_partial', ['filled' => 2, 'total' => 3]))
        ->and($result['results']['status']['1']['conflict'])->toBeTrue()
        ->and($result['results']['status']['1']['currentValue'])->toBe('open');

    expect(fhStatuses())->toBe([1 => 'open', 2 => 'done', 3 => 'done', 9 => 'open']);
});

// ─── Versions across repeated fills ──────────────────────────────────────────
//
// This is the contract the client's request queue exists to satisfy. A fill
// answers with each row's new version; the next fill must send THOSE. Sending
// versions from before an earlier fill is a stale write and is refused — which
// is correct, and is exactly what happened when the browser fired two fills
// without waiting for the first to answer: the second was rejected in full and
// the user's last drag was silently rolled back.

it('hands back versions that the next fill can use', function () {
    $component = fhComponent();

    $first = $component->fillTableCells(fhFillVersioned('status', 'doing', fhVersions([1, 2, 3])));
    expect($first['success'])->toBeTrue();

    // Reuse the versions the first fill returned, exactly as the client does.
    $returned = [];
    foreach ($first['results']['status'] as $key => $result) {
        $returned[$key] = $result['version'];
    }

    $second = $component->fillTableCells(fhFillVersioned('status', 'done', $returned));

    expect($second['success'])->toBeTrue()
        ->and(fhStatuses())->toBe([1 => 'done', 2 => 'done', 3 => 'done', 9 => 'open']);
});

it('refuses a fill still carrying the versions from before an earlier one', function () {
    $component = fhComponent();
    $stale = fhVersions([1, 2, 3]);

    // Move the clock on before the first fill, so its write lands in a later
    // second than the versions above. Without this the two writes share a
    // timestamp and the stale versions still match — see the granularity test
    // below, which is why the browser only hit this intermittently.
    Carbon::setTestNow(Carbon::now()->addSeconds(2));

    $component->fillTableCells(fhFillVersioned('status', 'doing', $stale));

    // The second fill never saw the first one's answer — every row is stale now.
    $second = $component->fillTableCells(fhFillVersioned('status', 'done', $stale));

    expect($second['success'])->toBeFalse()
        ->and($second['results']['status']['1']['conflict'])->toBeTrue()
        ->and($second['results']['status']['2']['conflict'])->toBeTrue()
        ->and($second['results']['status']['3']['conflict'])->toBeTrue()
        // Nothing moved: the rows still hold what the FIRST fill wrote.
        ->and(fhStatuses())->toBe([1 => 'doing', 2 => 'doing', 3 => 'doing', 9 => 'open']);

    Carbon::setTestNow();
});

// The lock's resolution is one second, because a version is `updated_at` as a
// Unix timestamp. Two writes inside the same second are indistinguishable, so a
// stale version is NOT caught there. This is why the client-side bug showed up
// only sometimes: whether a too-early second fill was refused depended on
// whether the two requests happened to straddle a second boundary.
//
// Documented rather than fixed: sub-second stamps would change
// RecordVersion for every surface, and many schemas store second-precision
// timestamps anyway.
it('cannot tell two writes apart inside the same second', function () {
    $component = fhComponent();
    $held = fhVersions([1, 2, 3]);

    Carbon::setTestNow(Carbon::now());   // freeze: both writes share a second

    $component->fillTableCells(fhFillVersioned('status', 'doing', $held));

    // Same versions again — genuinely stale, but the stamp has not moved.
    $second = $component->fillTableCells(fhFillVersioned('status', 'done', $held));

    expect($second['success'])->toBeTrue()
        ->and(fhStatuses())->toBe([1 => 'done', 2 => 'done', 3 => 'done', 9 => 'open']);

    Carbon::setTestNow();
});

// Writing a value a row already holds leaves the model clean, so Eloquent skips
// the UPDATE and `updated_at` does not move. The version handed back must still
// be the row's real current stamp, or the next fill would be refused.
it('keeps versions usable when a fill writes a value the row already has', function () {
    $component = fhComponent();

    $first = $component->fillTableCells(fhFillVersioned('status', 'open', fhVersions([1, 2, 3])));
    expect($first['success'])->toBeTrue();

    $returned = [];
    foreach ($first['results']['status'] as $key => $result) {
        $returned[$key] = $result['version'];
    }

    expect($component->fillTableCells(fhFillVersioned('status', 'done', $returned))['success'])->toBeTrue()
        ->and(fhStatuses())->toBe([1 => 'done', 2 => 'done', 3 => 'done', 9 => 'open']);
});

it('refuses only the rows that went stale, not the whole range', function () {
    $component = fhComponent();
    $versions = fhVersions([1, 2, 3]);

    // Someone else moves row 2 on only.
    FhTask::find(2)->forceFill(['updated_at' => Carbon::now()->addMinutes(5)])->saveQuietly();

    $result = $component->fillTableCells(fhFillVersioned('status', 'done', $versions));

    expect($result['success'])->toBeFalse()
        ->and($result['results']['status']['2']['conflict'])->toBeTrue()
        ->and($result['results']['status']['1']['success'])->toBeTrue()
        ->and($result['results']['status']['3']['success'])->toBeTrue()
        ->and(fhStatuses())->toBe([1 => 'done', 2 => 'open', 3 => 'done', 9 => 'open']);
});

it('stays consistent across a run of fills that each use the last answer', function () {
    $component = fhComponent();
    $versions = fhVersions([1, 2, 3]);

    foreach (['a', 'b', 'c', 'd', 'e'] as $value) {
        $result = $component->fillTableCells(fhFillVersioned('status', $value, $versions));

        expect($result['success'])->toBeTrue("fill '{$value}' should have been accepted");

        $versions = [];
        foreach ($result['results']['status'] as $key => $row) {
            $versions[$key] = $row['version'];
        }
    }

    expect(fhStatuses())->toBe([1 => 'e', 2 => 'e', 3 => 'e', 9 => 'open']);
});

// ─── Payload shape ───────────────────────────────────────────────────────────

it('rejects a payload that is not a list of complete fill entries', function () {
    $component = fhComponent();

    $malformed = [
        [],                                                  // nothing to do
        ['column' => 'status'],                              // not a list of entries
        [['value' => 'x', 'records' => ['1' => null]]],      // no column named
        [['column' => 'status', 'records' => ['1' => null]]], // no value key
        [['column' => '', 'value' => 'x', 'records' => ['1' => null]]],
        [['column' => 'status', 'value' => 'x', 'records' => ['1' => ['nested']]]], // a version is a scalar
    ];

    foreach ($malformed as $i => $payload) {
        $result = $component->fillTableCells($payload);

        expect($result['success'])->toBeFalse("payload #{$i} should be refused")
            ->and($result['results'])->toBe([]);
    }

    expect(FhTask::find(1)->status)->toBe('open');
});

it('rejects an entry that names no records', function () {
    $result = fhComponent()->fillTableCells([['column' => 'status', 'value' => 'x', 'records' => []]]);

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('names no records');
});
