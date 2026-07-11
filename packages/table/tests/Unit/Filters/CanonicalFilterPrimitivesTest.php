<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;
use Workbench\App\Models\InvoiceItem;
use Workbench\App\Models\Task;

// Direct coverage of the canonical Filter primitives introduced by the column
// filter unification (apply overrides, planner mapping, relation wrapping,
// form fields). Behaviour under the table is covered elsewhere; this locks the
// building blocks in isolation.

// ─── TextFilter ──────────────────────────────────────────────────────────────

it('short-circuits an empty value in TextFilter::apply', function () {
    $q = Task::query();

    expect(TextFilter::make('title')->apply($q, ''))->toBe($q)
        ->and(TextFilter::make('title')->apply($q, []))->toBe($q);
});

it('runs a TextFilter query callback ahead of the operator match', function () {
    $sql = TextFilter::make('title')
        ->query(fn ($q, $v) => $q->where('title', $v))
        ->apply(Task::query(), 'x')->toSql();

    expect($sql)->toContain('"title" =');
});

it('maps every TextFilter operator to a planner definition', function () {
    expect(TextFilter::make('t')->operator('ends_with')->toPlannerDefinitions('x')[0]->value)->toBe('%x')
        ->and(TextFilter::make('t')->operator('!=')->toPlannerDefinitions('x')[0]->operator)->toBe('!=')
        ->and(TextFilter::make('t')->operator('>')->toPlannerDefinitions('5')[0]->operator)->toBe('>')
        ->and(TextFilter::make('t')->toPlannerDefinitions('')[0] ?? null)->toBeNull();
});

it('builds a TextInput form field for a text filter', function () {
    $fields = TextFilter::make('title')->getFormFields();

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(TextInput::class);
});

// ─── Filter (base) apply / planner ───────────────────────────────────────────

it('applies a base multiple filter array value as whereIn', function () {
    $sql = Filter::make('status')->multiple()->apply(Task::query(), ['a', 'b'])->toSql();

    expect($sql)->toContain('in (');
});

it('applies a base relation filter as whereHas with whereIn / where', function () {
    $array = Filter::make('invoice.number')->apply(InvoiceItem::query(), ['a', 'b'])->toSql();
    expect($array)->toContain('exists')->toContain('in (');

    $scalar = Filter::make('invoice.number')->apply(InvoiceItem::query(), 'INV-1')->toSql();
    expect($scalar)->toContain('exists');
});

it('returns no planner definition for empty, bypassing or array-shaped base filters', function () {
    expect(Filter::make('x')->toPlannerDefinitions(''))->toBe([])
        ->and(Filter::make('x')->toPlannerDefinitions(null))->toBe([])
        ->and(Filter::make('x')->query(fn ($q) => $q)->toPlannerDefinitions('v'))->toBe([])
        ->and(Filter::make('x')->toPlannerDefinitions(['from' => '1']))->toBe([]);
});

// ─── SelectFilter apply ──────────────────────────────────────────────────────

it('short-circuits and delegates callbacks in SelectFilter::apply', function () {
    $q = Task::query();
    expect(SelectFilter::make('status')->apply($q, ''))->toBe($q);

    $sql = SelectFilter::make('status')
        ->query(fn ($q, $v) => $q->where('status', $v))
        ->apply(Task::query(), 'a')->toSql();
    expect($sql)->toContain('"status" =');
});

it('applies a SelectFilter array value as whereIn, incl. through a relation', function () {
    $plain = SelectFilter::make('status')->apply(Task::query(), ['a', 'b'])->toSql();
    expect($plain)->toContain('in (');

    $relation = SelectFilter::make('invoice.number')->apply(InvoiceItem::query(), ['a', 'b'])->toSql();
    expect($relation)->toContain('exists')->toContain('in (');
});

it('builds a Select form field and hides a non-viewable select filter', function () {
    $fields = SelectFilter::make('status')->options(['a' => 'A'])->getFormFields();

    expect($fields[0])->toBeInstanceOf(Select::class)
        ->and(SelectFilter::make('status')->options(['a' => 'A'])->hidden()->render())->toBe('');
});

// ─── NumberRangeFilter / TernaryFilter planner + fields ──────────────────────

it('returns no planner definition for an unset or non-array number range', function () {
    expect(NumberRangeFilter::make('price')->toPlannerDefinitions('scalar'))->toBe([])
        ->and(NumberRangeFilter::make('price')->toPlannerDefinitions(['min' => '', 'max' => '']))->toBe([]);
});

it('degrades a one-sided number range to a single BETWEEN bound', function () {
    $defs = NumberRangeFilter::make('price')->toPlannerDefinitions(['min' => '5', 'max' => '']);

    expect($defs[0]->operator)->toBe('BETWEEN')
        ->and($defs[0]->value)->toBe([5.0, null]);
});

it('builds a Select form field for a ternary filter', function () {
    expect(TernaryFilter::make('active')->getFormFields()[0])->toBeInstanceOf(Select::class);
});

// ─── inline view + select-like resolution ────────────────────────────────────

it('resolves the inline header view + select-like flag per filter type', function () {
    expect(TextFilter::make('t')->inlineView())->toBe('tables.columns.partials.filter-text')
        ->and(TextFilter::make('t')->isSelectLike())->toBeFalse()
        ->and(SelectFilter::make('s')->inlineView())->toBe('tables.columns.partials.filter-select')
        ->and(SelectFilter::make('s')->multiple()->inlineView())->toBe('tables.columns.partials.filter-multi-select')
        ->and(SelectFilter::make('s')->isSelectLike())->toBeTrue()
        ->and(TernaryFilter::make('b')->inlineView())->toBe('tables.columns.partials.filter-boolean')
        ->and(TernaryFilter::make('b')->isSelectLike())->toBeTrue()
        ->and(NumberRangeFilter::make('n')->inlineView())->toBe('tables.columns.partials.filter-number-range')
        ->and(DateFilter::make('d')->inlineView())->toBe('tables.columns.partials.filter-date')
        ->and(DateFilter::make('d')->range()->inlineView())->toBe('tables.columns.partials.filter-date-range');
});
