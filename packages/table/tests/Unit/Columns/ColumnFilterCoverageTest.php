<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\TextColumn;
use Workbench\App\Models\InvoiceItem;
use Workbench\App\Models\Task;

// ─── Filter configuration & getters ─────────────────────────────

it('configures a select filter', function () {
    $column = TextColumn::make('status')->filterAsSelect(['a' => 'A', 'b' => 'B'], 'Pick');

    expect($column->isFilterable())->toBeTrue()
        ->and($column->getFilterType())->toBe('select')
        ->and($column->getFilterOptions())->toBe(['a' => 'A', 'b' => 'B'])
        ->and($column->getFilterPlaceholder())->toBe('Pick');
});

it('configures a multi-select filter', function () {
    $column = TextColumn::make('role')->filterAsMultiSelect(['a' => 'A', 'b' => 'B'], 'Any');

    expect($column->isFilterable())->toBeTrue()
        ->and($column->getFilterType())->toBe('multi_select')
        ->and($column->getFilterOptions())->toBe(['a' => 'A', 'b' => 'B'])
        ->and($column->getFilterPlaceholder())->toBe('Any');
});

it('makes select filters searchable by default and can opt out', function () {
    expect(TextColumn::make('role')->filterAsSelect(['a' => 'A'])->isFilterSearchable())->toBeTrue()
        ->and(TextColumn::make('role')->filterAsMultiSelect(['a' => 'A'])->isFilterSearchable())->toBeTrue()
        ->and(TextColumn::make('role')->filterAsSelect(['a' => 'A'])->filterSearchable(false)->isFilterSearchable())->toBeFalse();

    // Searchable select renders the in-panel search input; disabling drops it.
    expect(TextColumn::make('role')->filterAsSelect(['a' => 'A'])->renderFilter())->toContain('x-ref="searchInput"')
        ->and(TextColumn::make('role')->filterAsSelect(['a' => 'A'])->filterSearchable(false)->renderFilter())->not->toContain('x-ref="searchInput"');
});

it('configures date, date range and number range filters', function () {
    $date = TextColumn::make('d')->filterAsDate('2026-01-01', '2026-12-31');
    expect($date->getFilterType())->toBe('date')
        ->and($date->getFilterMinDate())->toBe('2026-01-01')
        ->and($date->getFilterMaxDate())->toBe('2026-12-31');

    $range = TextColumn::make('d')->filterAsDateRange('2026-01-01', '2026-12-31');
    expect($range->getFilterType())->toBe('date_range');

    $num = TextColumn::make('n')->filterAsNumberRange(1.0, 100.0, 0.5);
    expect($num->getFilterType())->toBe('number_range')
        ->and($num->getFilterMinValue())->toBe(1.0)
        ->and($num->getFilterMaxValue())->toBe(100.0)
        ->and($num->getFilterStep())->toBe(0.5);
});

it('configures a boolean filter with default and custom labels', function () {
    $default = TextColumn::make('active')->filterAsBoolean();
    expect($default->getFilterType())->toBe('boolean')
        ->and($default->getFilterTrueLabel())->not->toBeEmpty()
        ->and($default->getFilterFalseLabel())->not->toBeEmpty();

    $custom = TextColumn::make('active')->filterAsBoolean('On', 'Off');
    expect($custom->getFilterTrueLabel())->toBe('On')
        ->and($custom->getFilterFalseLabel())->toBe('Off');
});

it('covers filterable toggling, operator, debounce and placeholder', function () {
    expect(TextColumn::make('a')->filterable()->isFilterable())->toBeTrue()
        ->and(TextColumn::make('a')->filterable(false)->isFilterable())->toBeFalse()
        ->and(TextColumn::make('a')->getFilterOperator())->toBe('like')
        ->and(TextColumn::make('a')->filterOperator('=')->getFilterOperator())->toBe('=')
        ->and(TextColumn::make('a')->getFilterDebounce())->toBeInt()
        ->and(TextColumn::make('a')->filterDebounce(300)->getFilterDebounce())->toBe(300)
        ->and(TextColumn::make('a')->filterPlaceholder('search')->getFilterPlaceholder())->toBe('search');
});

// ─── applyFilter dispatch ───────────────────────────────────────

it('short-circuits on empty filter values', function () {
    $q = Task::query();

    expect(TextColumn::make('title')->applyFilter($q, null))->toBe($q)
        ->and(TextColumn::make('title')->applyFilter($q, ''))->toBe($q)
        ->and(TextColumn::make('title')->applyFilter($q, []))->toBe($q);
});

it('uses a custom filter callback when provided', function () {
    $column = TextColumn::make('title')->filterUsing(fn ($query, $value) => $query->where('title', $value));

    expect($column->getFilterQueryCallback())->toBeInstanceOf(Closure::class);

    $sql = $column->applyFilter(Task::query(), 'x')->toSql();
    expect($sql)->toContain('"title" =');
});

it('applies a relation filter through whereHas', function () {
    $column = TextColumn::make('invoice.number');

    $query = $column->applyFilter(InvoiceItem::query(), 'INV-1');

    expect($column->getRelationshipAttribute())->toBe('number')
        ->and($query->toSql())->toContain('exists');
});

// ─── applyFilterCondition branches ──────────────────────────────

it('applies select filters for scalar and array values', function () {
    $scalar = TextColumn::make('status')->filterAsSelect(['a' => 'A'])->applyFilter(Task::query(), 'a');
    expect($scalar->toSql())->toContain('"status" =');

    $array = TextColumn::make('status')->filterAsSelect(['a' => 'A'])->applyFilter(Task::query(), ['a', 'b']);
    expect($array->toSql())->toContain('in (');
});

it('applies a multi-select filter as whereIn and skips an empty selection', function () {
    $picked = TextColumn::make('role')
        ->filterAsMultiSelect(['a' => 'A', 'b' => 'B'])
        ->applyFilter(Task::query(), ['a', 'b']);
    expect($picked->toSql())->toContain('in (');

    // An empty array (nothing checked) must not constrain the query.
    $empty = TextColumn::make('role')
        ->filterAsMultiSelect(['a' => 'A'])
        ->applyFilter(Task::query(), []);
    expect($empty->toSql())->toBe(Task::query()->toSql());
});

it('applies date and date-range filters', function () {
    $date = TextColumn::make('due_at')->filterAsDate()->applyFilter(Task::query(), '2026-01-01');
    expect($date->getBindings())->toContain('2026-01-01');

    $range = TextColumn::make('due_at')->filterAsDateRange()
        ->applyFilter(Task::query(), ['from' => '2026-01-01', 'to' => '2026-12-31']);
    expect($range->getBindings())->toContain('2026-01-01', '2026-12-31');

    // Non-array value falls back to a single whereDate.
    $single = TextColumn::make('due_at')->filterAsDateRange()->applyFilter(Task::query(), '2026-06-01');
    expect($single->getBindings())->toContain('2026-06-01');
});

it('applies number-range filters', function () {
    $range = TextColumn::make('priority')->filterAsNumberRange()
        ->applyFilter(Task::query(), ['min' => '5', 'max' => '10']);
    expect($range->getBindings())->toContain(5.0, 10.0);

    $single = TextColumn::make('priority')->filterAsNumberRange()->applyFilter(Task::query(), 7);
    expect($single->getBindings())->toContain(7);
});

it('applies boolean filters for true, false and unknown values', function () {
    $true = TextColumn::make('completed')->filterAsBoolean()->applyFilter(Task::query(), 'true');
    expect($true->getBindings())->toContain(true);

    $false = TextColumn::make('completed')->filterAsBoolean()->applyFilter(Task::query(), '0');
    expect(strtolower($false->toSql()))->toContain('null');

    // Unknown value leaves the query untouched.
    $q = Task::query();
    expect(TextColumn::make('completed')->filterAsBoolean()->applyFilter($q, 'maybe')->toSql())
        ->toBe($q->toSql());
});

// ─── applyTextFilter operators ──────────────────────────────────

it('applies every text-filter operator', function () {
    $cases = [
        'equals' => '"title" =',
        '=' => '"title" =',
        'starts_with' => 'like',
        'ends_with' => 'like',
        '>' => '"title" >',
        '>=' => '"title" >=',
        '<' => '"title" <',
        '<=' => '"title" <=',
        '!=' => '"title" !=',
        'like' => 'like', // default branch
    ];

    foreach ($cases as $operator => $fragment) {
        $sql = TextColumn::make('title')->filterOperator($operator)->applyFilter(Task::query(), 'x')->toSql();
        expect($sql)->toContain($fragment);
    }
});

it('ignores non-scalar text filter values', function () {
    $q = Task::query();

    expect(TextColumn::make('title')->applyFilter($q, ['unexpected' => 'array'])->toSql())
        ->toBe($q->toSql());
});

// ─── renderFilter ───────────────────────────────────────────────

it('renders nothing for a non-filterable column', function () {
    expect(TextColumn::make('a')->renderFilter())->toBe('');
});

it('renders a filter partial for each filter type', function () {
    // select + multi-select delegate to the canonical searchable combobox,
    // bound to the column's columnFilters state path.
    expect(TextColumn::make('a')->filterAsSelect(['x' => 'X'])->renderFilter())->toContain('tableState.columnFilters.a')
        ->and(TextColumn::make('a')->filterAsMultiSelect(['x' => 'X', 'y' => 'Y'])->renderFilter())->toContain('tableState.columnFilters.a')
        ->and(TextColumn::make('a')->filterAsDate()->renderFilter())->toBeString()
        ->and(TextColumn::make('a')->filterAsDateRange()->renderFilter())->toBeString()
        ->and(TextColumn::make('a')->filterAsNumberRange()->renderFilter())->toBeString()
        ->and(TextColumn::make('a')->filterAsBoolean()->renderFilter())->toBeString()
        ->and(TextColumn::make('a')->filterable()->renderFilter('current'))->toBeString();
});
