<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;
use Workbench\App\Models\Task;

// A column header filter is now a *placement* of a canonical Filter — the same
// object type used by the filter panel — instead of a parallel per-column
// filter engine.

it('accepts a canonical Filter via Column::filter()', function () {
    $filter = SelectFilter::make('status')->options(['a' => 'A']);
    $column = TextColumn::make('status')->filter($filter);

    expect($column->isFilterable())->toBeTrue()
        ->and($column->getFilter())->toBe($filter);
});

it('injects the columnFilters state-path prefix + inline variant when resolving', function () {
    $column = TextColumn::make('status')->filterAsSelect(['a' => 'A']);
    $resolved = $column->resolveFilter();

    expect($resolved->isInline())->toBeTrue()
        ->and($resolved->getStatePathPrefix())->toBe('tableState.columnFilters')
        ->and($resolved->getStatePath())->toBe('tableState.columnFilters.status');
});

it('maps text operators to planner LIKE / comparison definitions', function () {
    $like = TextColumn::make('title')->filterable()->getFilter()->toPlannerDefinitions('x');
    expect($like[0])->toBeInstanceOf(FilterDefinition::class)
        ->and($like[0]->operator)->toBe('LIKE')
        ->and($like[0]->value)->toBe('%x%');

    $starts = TextColumn::make('title')->filterOperator('starts_with')->getFilter()->toPlannerDefinitions('x');
    expect($starts[0]->operator)->toBe('LIKE')
        ->and($starts[0]->value)->toBe('x%');

    $eq = TextColumn::make('title')->filterOperator('=')->getFilter()->toPlannerDefinitions('x');
    expect($eq[0]->operator)->toBe('=')
        ->and($eq[0]->value)->toBe('x');
});

it('maps a multi-select to a planner IN definition', function () {
    $defs = TextColumn::make('role')->filterAsMultiSelect(['a' => 'A', 'b' => 'B'])
        ->getFilter()->toPlannerDefinitions(['a', 'b']);

    expect($defs[0]->operator)->toBe('in')
        ->and($defs[0]->value)->toBe(['a', 'b']);
});

it('maps a number range to a planner BETWEEN definition', function () {
    $defs = NumberRangeFilter::make('price')->toPlannerDefinitions(['min' => '5', 'max' => '10']);

    expect($defs[0]->operator)->toBe('BETWEEN')
        ->and($defs[0]->value)->toBe([5.0, 10.0]);
});

it('bypasses the planner for date and boolean filters (whereDate / null-aware)', function () {
    expect(DateFilter::make('created_at')->toPlannerDefinitions('2026-01-01'))->toBe([])
        ->and(TernaryFilter::make('active')->toPlannerDefinitions('true'))->toBe([]);
});

it('does not plan a filter that carries a custom query callback', function () {
    $filter = TextFilter::make('title')->query(fn ($q, $value) => $q);

    expect($filter->toPlannerDefinitions('x'))->toBe([]);
});

it('applies a text column filter as a substring LIKE over the query', function () {
    $sql = TextColumn::make('title')->filterable()
        ->applyFilter(Task::query(), 'Alph')->toSql();

    expect(strtolower($sql))->toContain('like');
});
