<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Support\ColumnSet;
use NyonCode\WireTable\Table;

/*
 * The columns as one thing. The questions here were spelled out wherever they
 * were needed — a scan for a name, a filter for the sortable ones — and the
 * lookup now comes off a map built once.
 */

it('is empty until it is given columns', function () {
    $set = new ColumnSet;

    expect($set->isEmpty())->toBeTrue()
        ->and($set)->toHaveCount(0)
        ->and($set->all())->toBe([])
        ->and($set->names())->toBe([])
        ->and($set->find('anything'))->toBeNull()
        ->and($set->has('anything'))->toBeFalse();
});

it('finds a column by name', function () {
    $name = Column::make('name');
    $set = ColumnSet::make([$name, Column::make('email')]);

    expect($set->find('name'))->toBe($name)
        ->and($set->has('email'))->toBeTrue()
        ->and($set->find('missing'))->toBeNull();
});

it('answers repeated lookups off one map', function () {
    // The second call must not rebuild anything, and must not disagree.
    $set = ColumnSet::make([Column::make('a'), Column::make('b')]);

    expect($set->find('b'))->toBe($set->find('b'))
        ->and($set->find('nope'))->toBeNull()
        ->and($set->find('a')->getName())->toBe('a');
});

it('keeps the first declaration when a name is duplicated', function () {
    // Two columns with one name is a configuration mistake, but the scan this
    // replaces answered with the first — a map built the obvious way would have
    // silently started answering with the last.
    $first = Column::make('status')->label('First');
    $second = Column::make('status')->label('Second');

    expect(ColumnSet::make([$first, $second])->find('status'))->toBe($first);
});

it('lists names in declaration order', function () {
    $set = ColumnSet::make([Column::make('id'), Column::make('name'), Column::make('email')]);

    expect($set->names())->toBe(['id', 'name', 'email']);
});

it('separates the searchable and sortable columns, reindexed', function () {
    $set = ColumnSet::make([
        Column::make('id'),
        Column::make('name')->searchable()->sortable(),
        Column::make('email')->searchable(),
    ]);

    expect($set->names())->toHaveCount(3)
        ->and(array_map(fn ($c) => $c->getName(), $set->searchable()))->toBe(['name', 'email'])
        ->and(array_map(fn ($c) => $c->getName(), $set->sortable()))->toBe(['name'])
        ->and(array_keys($set->searchable()))->toBe([0, 1]);
});

it('can be iterated and counted like the array it replaces', function () {
    $set = ColumnSet::make([Column::make('a'), Column::make('b')]);

    $seen = [];
    foreach ($set as $column) {
        $seen[] = $column->getName();
    }

    expect($seen)->toBe(['a', 'b'])
        ->and(count($set))->toBe(2)
        ->and($set->isEmpty())->toBeFalse();
});

it('hands back exactly the array it was given', function () {
    // Table::getColumns() is public API and its consumers index into the result,
    // so the set must not renumber or reorder what came in.
    $columns = [Column::make('a'), Column::make('b')];

    expect(ColumnSet::make($columns)->all())->toBe($columns);
});

it('backs the table without changing what the table answers', function () {
    $table = Table::make()->columns([
        Column::make('id'),
        Column::make('name')->searchable()->sortable(),
    ]);

    expect($table->getColumns())->toHaveCount(2)
        ->and($table->getColumnNames())->toBe(['id', 'name'])
        ->and($table->findColumn('name')?->getName())->toBe('name')
        ->and($table->findColumn('gone'))->toBeNull()
        ->and($table->getSearchableColumns())->toHaveCount(1)
        ->and($table->getSortableColumns())->toHaveCount(1);
});

it('answers for a table that never declared any columns', function () {
    $table = Table::make();

    expect($table->getColumns())->toBe([])
        ->and($table->getColumnNames())->toBe([])
        ->and($table->findColumn('name'))->toBeNull()
        ->and($table->getSearchableColumns())->toBe([])
        ->and($table->getSortableColumns())->toBe([]);
});

it('starts over when the columns are replaced', function () {
    // The memoized map has to go with them.
    $table = Table::make()->columns([Column::make('old')]);
    expect($table->findColumn('old'))->not->toBeNull();

    $table->columns([Column::make('new')]);

    expect($table->findColumn('old'))->toBeNull()
        ->and($table->findColumn('new'))->not->toBeNull();
});
