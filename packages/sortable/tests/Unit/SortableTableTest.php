<?php

declare(strict_types=1);

use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Table;

it('extends the base Table class', function () {
    expect(SortableTable::make())->toBeInstanceOf(Table::class);
});

// ── Row Reorder ─────────────────────────────────────────

it('is not reorderable by default', function () {
    expect(SortableTable::make()->isReorderable())->toBeFalse();
});

it('enables reorderable with default order column', function () {
    $table = SortableTable::make()->reorderable();

    expect($table->isReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('sort_order');
});

it('enables reorderable with custom order column', function () {
    $table = SortableTable::make()->reorderable('position');

    expect($table->isReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('position');
});

it('conditionally disables reorderable', function () {
    $table = SortableTable::make()->reorderable('position', false);

    expect($table->isReorderable())->toBeFalse();
});

it('returns itself for fluent chaining', function () {
    $table = SortableTable::make();

    expect($table->reorderable())->toBe($table);
});

// ── Paginated While Reordering ──────────────────────────

it('disables pagination while reordering by default', function () {
    expect(SortableTable::make()->isPaginatedWhileReordering())->toBeFalse();
});

it('enables pagination while reordering', function () {
    $table = SortableTable::make()->paginatedWhileReordering();

    expect($table->isPaginatedWhileReordering())->toBeTrue();
});

it('returns itself for paginatedWhileReordering fluent chaining', function () {
    $table = SortableTable::make();

    expect($table->paginatedWhileReordering())->toBe($table);
});

// ── Column Reorder ──────────────────────────────────────

it('is not column reorderable by default', function () {
    expect(SortableTable::make()->isColumnReorderable())->toBeFalse();
});

it('enables column reorderable', function () {
    expect(SortableTable::make()->columnReorderable()->isColumnReorderable())->toBeTrue();
});

it('disables column reorderable explicitly', function () {
    $table = SortableTable::make()
        ->columnReorderable()
        ->columnReorderable(false);

    expect($table->isColumnReorderable())->toBeFalse();
});

it('returns itself for columnReorderable fluent chaining', function () {
    $table = SortableTable::make();

    expect($table->columnReorderable())->toBe($table);
});

// ── Combined ────────────────────────────────────────────

it('combines row and column reorderable', function () {
    $table = SortableTable::make()
        ->reorderable('position')
        ->columnReorderable();

    expect($table->isReorderable())->toBeTrue()
        ->and($table->isColumnReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('position');
});

// ── View Name ───────────────────────────────────────────

it('returns sortable view name when row reorderable', function () {
    $table = SortableTable::make()->reorderable();

    expect($table->getViewName())->toBe('wire-sortable::tables.index');
});

it('returns sortable view name when column reorderable', function () {
    $table = SortableTable::make()->columnReorderable();

    expect($table->getViewName())->toBe('wire-sortable::tables.index');
});

it('returns default view name when not reorderable', function () {
    $table = SortableTable::make();

    expect($table->getViewName())->toBe('wire-table::tables.index');
});
