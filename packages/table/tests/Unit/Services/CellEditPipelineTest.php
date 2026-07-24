<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Services\CellEditPipeline;
use NyonCode\WireTable\Support\CellEditOutcome;

/*
 * The stages of one inline-cell edit, exercised on their own.
 *
 * WithTableInteractionsTest already drives these through updateTableCell(), but
 * only end to end: it can tell that a stale edit is refused, not that guard()
 * runs before dehydrate(), nor that commit() is callable against a record the
 * caller locked itself. Both matter, because the bulk fill reuses the
 * column-level stages once and commit()/settle() per record — indirect coverage
 * through the single-cell path would let that reuse drift silently.
 */

class CepUser extends Model
{
    protected $table = 'cep_users';

    protected $guarded = [];

    protected $casts = ['locked' => 'boolean'];
}

function cepPipeline(): CellEditPipeline
{
    return app(CellEditPipeline::class);
}

beforeEach(function () {
    Schema::create('cep_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('locked')->default(false);
        $table->timestamps();
    });

    CepUser::create(['id' => 1, 'name' => 'Carol', 'locked' => false]);
    CepUser::create(['id' => 2, 'name' => 'Dave', 'locked' => true]);
});

afterEach(fn () => Schema::dropIfExists('cep_users'));

// ─── guard ───────────────────────────────────────────────────────────────────

it('lets an editable, unrestricted column through', function () {
    expect(cepPipeline()->guard(TextInputColumn::make('name')))->toBeNull();
});

it('refuses a column that is not editable', function () {
    $outcome = cepPipeline()->guard(Column::make('name'));

    expect($outcome)->toBeInstanceOf(CellEditOutcome::class)
        ->and($outcome->success)->toBeFalse()
        ->and($outcome->message)->toBe(__('wire-table::messages.column_not_editable'));
});

it('refuses a column whose permission the user does not hold', function () {
    Gate::define('edit-names', fn () => false);

    $outcome = cepPipeline()->guard(TextInputColumn::make('name')->permission('edit-names'));

    expect($outcome?->message)->toBe(__('wire-table::messages.no_permission_view'));
});

// The stage boundary that lets the host run guard() first: refusing a column is
// a decision the pipeline can reach without touching the value, so an author's
// beforeSave() never fires for a column the user may not write. Folding the
// transform into guard() would break that and fail here.
it('reaches a refusal without running the write-path transform', function () {
    $calls = 0;
    $column = TextInputColumn::make('name')->beforeSave(function (mixed $value) use (&$calls): mixed {
        $calls++;

        return $value;
    });
    $column->editable(false);

    $pipeline = cepPipeline();

    expect($pipeline->guard($column))->not->toBeNull()
        ->and($calls)->toBe(0);
});

// ─── dehydrate ───────────────────────────────────────────────────────────────

it('passes a value through untouched when the column declares no transform', function () {
    expect(cepPipeline()->dehydrate(Column::make('name'), '  carol  '))->toBe('  carol  ');
});

it('applies the column write-path transform once per call', function () {
    $column = TextInputColumn::make('name')->trim()->uppercase();

    expect(cepPipeline()->dehydrate($column, '  carol  '))->toBe('CAROL');
});

it('passes the record to the transform when one is given', function () {
    $seen = null;
    $column = TextInputColumn::make('name')
        ->beforeSave(function (mixed $value, ?Model $record) use (&$seen): mixed {
            $seen = $record;

            return $value;
        });

    $record = CepUser::find(1);
    cepPipeline()->dehydrate($column, 'x', $record);

    expect($seen?->getKey())->toBe(1);
});

// ─── validateWithoutRecord ───────────────────────────────────────────────────

it('skips validation when the column declares no record-less rules', function () {
    expect(cepPipeline()->validateWithoutRecord(Column::make('name')->editable(), 'name', ''))->toBeNull();
});

it('passes a value that satisfies the record-less rules', function () {
    $column = Column::make('name')->editable()->editableRules(fn () => ['required', 'max:10']);

    expect(cepPipeline()->validateWithoutRecord($column, 'name', 'Carol'))->toBeNull();
});

it('reports the rule failure with its messages', function () {
    $column = Column::make('name')->editable()->editableRules(fn () => ['required']);

    $outcome = cepPipeline()->validateWithoutRecord($column, 'name', '');

    expect($outcome?->success)->toBeFalse()
        ->and($outcome?->errors)->not->toBeEmpty()
        ->and($outcome?->message)->toBe($outcome?->errors[0]);
});

// ─── commit ──────────────────────────────────────────────────────────────────

it('writes the value and reports the old one and the new version', function () {
    $record = CepUser::find(1);

    $outcome = cepPipeline()->commit(TextInputColumn::make('name'), 'name', $record, 'Renamed', null);

    expect($outcome->success)->toBeTrue()
        ->and($outcome->oldValue)->toBe('Carol')
        ->and($outcome->savedValue)->toBe('Renamed')
        ->and($outcome->version)->not->toBeNull()
        ->and(CepUser::find(1)->name)->toBe('Renamed');
});

it('refuses a per-record disabled cell without writing', function () {
    $column = TextInputColumn::make('name')->disabled(fn (Model $record) => (bool) $record->locked);

    $outcome = cepPipeline()->commit($column, 'name', CepUser::find(2), 'Forged', null);

    expect($outcome->success)->toBeFalse()
        ->and($outcome->message)->toBe(__('wire-table::messages.no_permission_edit'))
        ->and(CepUser::find(2)->name)->toBe('Dave');
});

it('refuses a stale write and hands back the current value to reconcile with', function () {
    $outcome = cepPipeline()->commit(TextInputColumn::make('name'), 'name', CepUser::find(1), 'Renamed', '1');

    expect($outcome->success)->toBeFalse()
        ->and($outcome->conflict)->toBeTrue()
        ->and($outcome->currentValue)->toBe('Carol')
        ->and($outcome->currentVersion)->not->toBeNull()
        ->and(CepUser::find(1)->name)->toBe('Carol');
});

it('refuses a value the column rejects with record context', function () {
    $outcome = cepPipeline()->commit(TextInputColumn::make('name')->required(), 'name', CepUser::find(1), '', null);

    expect($outcome->success)->toBeFalse()
        ->and($outcome->errors)->not->toBeEmpty()
        ->and(CepUser::find(1)->name)->toBe('Carol');
});

// The record-aware pass must transform the caller's original state. Composing
// the transform with its own output would append the suffix twice.
it('dehydrates the original state, not an already-transformed value', function () {
    $column = TextInputColumn::make('name')->beforeSave(fn (mixed $value): mixed => $value.'!');
    $pipeline = cepPipeline();

    $state = 'Carol';
    $pipeline->dehydrate($column, $state);                                     // record-less pass
    $pipeline->commit($column, 'name', CepUser::find(1), $state, null);        // record-aware pass

    expect(CepUser::find(1)->name)->toBe('Carol!');
});

// ─── settle ──────────────────────────────────────────────────────────────────

it('runs the afterStateUpdated callback and announces the update', function () {
    Event::fake([CellUpdated::class]);

    $seen = [];
    $column = TextInputColumn::make('name')
        ->afterStateUpdated(function (Model $record, mixed $value) use (&$seen): void {
            $seen = [$record->getKey(), $value];
        });

    $pipeline = cepPipeline();
    $outcome = $pipeline->commit($column, 'name', CepUser::find(1), 'Renamed', null);
    $pipeline->settle($outcome, $column, 'Host\\Component', 'name', '1');

    expect($seen)->toBe([1, 'Renamed']);

    Event::assertDispatched(CellUpdated::class, fn (CellUpdated $e) => $e->tableId === 'Host\\Component'
        && $e->column === 'name'
        && $e->recordId === '1'
        && $e->oldValue === 'Carol'
        && $e->newValue === 'Renamed');
});

it('stays silent for an outcome that never wrote anything', function () {
    Event::fake([CellUpdated::class]);

    $column = TextInputColumn::make('name');
    cepPipeline()->settle(CellEditOutcome::rejected('nope'), $column, 'Host', 'name', '1');

    Event::assertNotDispatched(CellUpdated::class);
});

// ─── the wire shape ──────────────────────────────────────────────────────────

it('answers the client with only the keys that apply', function () {
    $record = CepUser::find(1);

    expect(CellEditOutcome::saved($record, '17', 'v', 'old')->toArray())
        ->toBe(['success' => true, 'version' => '17'])
        ->and(CellEditOutcome::rejected('gone')->toArray())
        ->toBe(['success' => false, 'message' => 'gone'])
        ->and(CellEditOutcome::invalid('bad', ['bad'])->toArray())
        ->toBe(['success' => false, 'message' => 'bad', 'errors' => ['bad']])
        ->and(CellEditOutcome::conflicted('moved', null, '17')->toArray())
        ->toBe([
            'success' => false,
            'message' => 'moved',
            'conflict' => true,
            'currentValue' => '',
            'currentVersion' => '17',
        ]);
});
