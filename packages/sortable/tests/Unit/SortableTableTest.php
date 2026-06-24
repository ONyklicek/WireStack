<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
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

// ── Always Reorderable ──────────────────────────────────

it('is not always reorderable by default', function () {
    expect(SortableTable::make()->isAlwaysReorderable())->toBeFalse();
});

it('alwaysReorderable also enables reorderable (regression: state was split across macros)', function () {
    $table = SortableTable::make()->alwaysReorderable();

    expect($table->isAlwaysReorderable())->toBeTrue()
        ->and($table->isReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('sort_order');
});

it('alwaysReorderable accepts a custom order column', function () {
    $table = SortableTable::make()->alwaysReorderable('position');

    expect($table->isReorderable())->toBeTrue()
        ->and($table->getOrderColumn())->toBe('position');
});

it('returns itself for alwaysReorderable fluent chaining', function () {
    $table = SortableTable::make();

    expect($table->alwaysReorderable())->toBe($table);
});

it('returns the documented default order column without a booted app', function () {
    // Pure unit test: no container bound, so the config-driven default applies.
    expect(SortableTable::make()->getOrderColumn())->toBe('sort_order');
});

it('falls back to the configured order column when none is set (regression)', function () {
    $container = new Container;
    $container->instance('config', new Repository(['wire-sortable' => ['order_column' => 'position']]));
    Container::setInstance($container);

    try {
        expect(SortableTable::make()->getOrderColumn())->toBe('position');
    } finally {
        Container::setInstance(null);
    }
});

it('an explicit order column wins over the config default', function () {
    $container = new Container;
    $container->instance('config', new Repository(['wire-sortable' => ['order_column' => 'position']]));
    Container::setInstance($container);

    try {
        expect(SortableTable::make()->reorderable('manual_order')->getOrderColumn())->toBe('manual_order');
    } finally {
        Container::setInstance(null);
    }
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
