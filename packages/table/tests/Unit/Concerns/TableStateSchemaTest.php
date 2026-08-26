<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\TableStateSchema;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/*
 * The state a table starts in.
 *
 * These rules used to live inside mountWithTable(), where the only way to see
 * them was to mount a Livewire component — and two of them are rules about a
 * *silent* failure: a filter with no state slot never reaches the server,
 * because Livewire's entangle no-ops on an undefined path, and a multi-select
 * that starts as null gets replaced on each click instead of toggling.
 * Nothing throws. The control simply does not work.
 */

class TssRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

function tssTable(): Table
{
    return Table::make()->model(TssRow::class);
}

it('starts ready, unless the table is lazy', function () {
    expect(TableStateSchema::initialFor(tssTable())['ready'])->toBeTrue()
        ->and(TableStateSchema::initialFor(tssTable()->lazy())['ready'])->toBeFalse();
});

it('carries the configured sort and page size', function () {
    $state = TableStateSchema::initialFor(
        tssTable()->defaultSort('name', 'desc')->perPage(25),
    );

    expect($state['sort'])->toBe(['column' => 'name', 'direction' => 'desc'])
        ->and($state['pagination']['perPage'])->toBe(25);
});

it('leaves the sort alone when the table configures none', function () {
    expect(TableStateSchema::initialFor(tssTable())['sort'])
        ->toBe(TableStateSchema::defaults()['sort']);
});

// ─── The entangle rules ──────────────────────────────────────────────────────

it('gives every rendered filter a slot, default or not', function () {
    // The silent failure: without a slot the path is undefined at render, and
    // $wire.entangle() no-ops, so the filter never reaches the server.
    $state = TableStateSchema::initialFor(tssTable()->filters([
        SelectFilter::make('status')->options(['a' => 'A']),
        SelectFilter::make('role')->options(['b' => 'B'])->default('b'),
    ]));

    expect($state['filters'])->toHaveKeys(['status', 'role']);
});

it('nests a dotted filter name the way the live binding writes it', function () {
    $state = TableStateSchema::initialFor(tssTable()->filters([
        SelectFilter::make('company.name')->options(['x' => 'X'])->default('x'),
    ]));

    expect(Arr::has($state['filters'], 'company.name'))->toBeTrue();
});

it('skips a hidden filter that forces nothing', function () {
    // No control renders, so there is nothing to entangle — a slot would only
    // be state nobody writes.
    $state = TableStateSchema::initialFor(tssTable()->filters([
        SelectFilter::make('secret')->options(['a' => 'A'])->visible(false),
    ]));

    expect($state['filters'])->toBe([]);
});

it('keeps a hidden filter that forces a default into the query', function () {
    $state = TableStateSchema::initialFor(tssTable()->filters([
        SelectFilter::make('tenant')->options(['t1' => 'T1'])->default('t1')->visible(false),
    ]));

    expect($state['filters'])->toHaveKey('tenant');
});

it('starts a multi-select column filter as an array, not null', function () {
    // The second silent failure: as a scalar, Livewire replaces the value on
    // each header checkbox click instead of toggling membership.
    $state = TableStateSchema::initialFor(tssTable()->columns([
        TextColumn::make('role')->filterAsMultiSelect(['a' => 'A']),
        TextColumn::make('name')->filterable(),
    ]));

    expect($state['columnFilters']['role'])->toBe([])
        ->and($state['columnFilters'])->toHaveKey('name')
        ->and($state['columnFilters']['name'])->toBeNull();
});

it('gives an unfilterable column no slot at all', function () {
    $state = TableStateSchema::initialFor(tssTable()->columns([TextColumn::make('name')]));

    expect($state['columnFilters'])->toBe([]);
});

// ─── Columns and sub-rows ────────────────────────────────────────────────────

it('hides the columns that start hidden, and only the toggleable ones', function () {
    $state = TableStateSchema::initialFor(tssTable()->columns([
        // Columns are toggleable by default, so a hidden one lands in the list
        // the toggle menu restores from.
        TextColumn::make('a')->hidden(),
        TextColumn::make('b'),
        // Explicitly not toggleable: hidden because it always is, so the user
        // has no switch to restore it with and it does not belong in the list.
        TextColumn::make('c')->toggleable(false)->hidden(),
    ]));

    expect($state['columns']['hidden'] ?? [])->toBe(['a']);
});

it('gives sub-row filter columns a slot only when the table filters sub-rows', function () {
    $columns = [TextColumn::make('total')->filterAsMultiSelect(['a' => 'A'])];

    $off = TableStateSchema::initialFor(tssTable()->subRows('items')->subRowColumns($columns));
    $on = TableStateSchema::initialFor(tssTable()->subRows('items')->subRowColumns($columns)->subRowsFilterable());

    // Off: the schema's own empty bag, with no per-column slots added.
    expect(Arr::get($off, 'rows.subRowFilters'))->toBe([])
        ->and(Arr::get($on, 'rows.subRowFilters.total'))->toBe([]);
});

it('builds on the defaults rather than replacing them', function () {
    $state = TableStateSchema::initialFor(tssTable());

    // Selection, and everything else the schema declares, must survive — the
    // container is constructed from this and nothing else fills the gaps.
    expect($state['selection'])->toBe(TableStateSchema::defaults()['selection'])
        ->and(array_keys($state))->toContain(...array_keys(TableStateSchema::defaults()));
});

it('answers the same state twice for the same table', function () {
    $table = tssTable()->defaultSort('name')->filters([SelectFilter::make('x')->options(['a' => 'A'])]);

    expect(TableStateSchema::initialFor($table))->toBe(TableStateSchema::initialFor($table));
});

it('does not need a filter instance to be viewable to be asked', function () {
    // canView() runs during mount, before any request context exists.
    expect(fn () => TableStateSchema::initialFor(tssTable()->filters([Filter::make('plain')])))
        ->not->toThrow(Throwable::class);
});
