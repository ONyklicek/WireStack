<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Tests\Unit;

use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use ReflectionMethod;

// Minimal stub to test the trait in isolation
class WithSortableTestStub
{
    use WithSortable;

    private SortableTable $table;

    public mixed $cachedRecords = null;

    protected string $wireTableClass = Table::class;

    protected ?Table $tableInstance = null;

    public function __construct(SortableTable $table)
    {
        $this->table = $table;
    }

    public function getTable(): Table
    {
        return $this->table;
    }
}

// ── Column Reorder ──────────────────────────────────────

it('returns columns in default order when no custom order set', function () {
    $table = SortableTable::make()
        ->columnReorderable()
        ->columns([
            TextColumn::make('name'),
            TextColumn::make('email'),
            TextColumn::make('age'),
        ]);

    $stub = new WithSortableTestStub($table);

    $names = array_map(fn ($c) => $c->getName(), $stub->getReorderableColumns());

    expect($names)->toBe(['name', 'email', 'age']);
});

it('returns columns in custom order', function () {
    $table = SortableTable::make()
        ->columnReorderable()
        ->columns([
            TextColumn::make('name'),
            TextColumn::make('email'),
            TextColumn::make('age'),
        ]);

    $stub = new WithSortableTestStub($table);
    $stub->reorderableColumnOrder = ['age', 'name', 'email'];

    $names = array_map(fn ($c) => $c->getName(), $stub->getReorderableColumns());

    expect($names)->toBe(['age', 'name', 'email']);
});

it('appends new columns not in saved order', function () {
    $table = SortableTable::make()
        ->columnReorderable()
        ->columns([
            TextColumn::make('name'),
            TextColumn::make('email'),
            TextColumn::make('age'),
            TextColumn::make('phone'),
        ]);

    $stub = new WithSortableTestStub($table);
    $stub->reorderableColumnOrder = ['email', 'name'];

    $names = array_map(fn ($c) => $c->getName(), $stub->getReorderableColumns());

    expect($names)->toBe(['email', 'name', 'age', 'phone']);
});

it('ignores removed columns in saved order', function () {
    $table = SortableTable::make()
        ->columnReorderable()
        ->columns([
            TextColumn::make('name'),
            TextColumn::make('email'),
        ]);

    $stub = new WithSortableTestStub($table);
    $stub->reorderableColumnOrder = ['deleted_column', 'email', 'name'];

    $names = array_map(fn ($c) => $c->getName(), $stub->getReorderableColumns());

    expect($names)->toBe(['email', 'name']);
});

// ── Row Reorder Toggle ──────────────────────────────────

it('is not reordering by default', function () {
    $table = SortableTable::make()->reorderable();

    $stub = new WithSortableTestStub($table);

    expect($stub->isTableReordering())->toBeFalse();
});

it('toggles reorder mode', function () {
    $table = SortableTable::make()->reorderable();

    $stub = new WithSortableTestStub($table);

    $stub->toggleReordering();
    expect($stub->isTableReordering())->toBeTrue();

    $stub->toggleReordering();
    expect($stub->isTableReordering())->toBeFalse();
});

it('does not toggle when table is not reorderable', function () {
    $table = SortableTable::make(); // not reorderable

    $stub = new WithSortableTestStub($table);

    $stub->toggleReordering();
    expect($stub->isTableReordering())->toBeFalse();
});

// ── Hooks ────────────────────────────────────────

it('interceptTableRecords returns null when not in reorder mode', function () {
    $table = SortableTable::make()->reorderable();
    $stub = new WithSortableTestStub($table);

    $reflection = new ReflectionMethod($stub, 'interceptTableRecords');
    $result = $reflection->invoke($stub);

    expect($result)->toBeNull();
});

it('has interceptTableRecords method for WithTable hook', function () {
    $stub = new WithSortableTestStub(SortableTable::make());

    expect(method_exists($stub, 'interceptTableRecords'))->toBeTrue();
});
