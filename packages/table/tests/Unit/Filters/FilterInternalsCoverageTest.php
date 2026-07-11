<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use Workbench\App\Models\Task;

// Exercises the remaining internal branches of the Filter hierarchy (apply
// guards, indicator formatting, render/view resolution, setters, state shapes)
// so every filter class is fully covered.

// ─── Filter (base) ───────────────────────────────────────────────────────────

it('short-circuits empty values and runs the callback in Filter::apply', function () {
    $q = Task::query();
    expect(Filter::make('status')->apply($q, ''))->toBe($q);

    $sql = Filter::make('status')->query(fn ($q, $v) => $q->where('status', $v))
        ->apply(Task::query(), 'a')->toSql();
    expect($sql)->toContain('"status" =');
});

it('formats an array indicator value and drops an all-empty one', function () {
    expect(Filter::make('roles')->getIndicator(['admin', 'editor']))->toContain('admin, editor')
        ->and(Filter::make('roles')->getIndicator(['', null]))->toBeNull();
});

it('renders through toHtml and hides a non-viewable filter', function () {
    expect(Filter::make('status')->toHtml())->toBeString()
        ->and(Filter::make('status')->hidden()->render())->toBe('');
});

it('resolves a namespaced filter view and falls back to a bare name', function () {
    $filter = new class('status') extends Filter
    {
        public function resolve(string $view): string
        {
            return $this->resolveFilterView($view);
        }
    };

    expect($filter->resolve('tables.filters.form-field'))->toBe('wire-table::tables.filters.form-field')
        ->and($filter->resolve('no.such.view'))->toBe('no.such.view');
});

// ─── NumberRangeFilter ───────────────────────────────────────────────────────

it('sets min/max labels and guards apply', function () {
    $filter = NumberRangeFilter::make('price')->minLabel('From')->maxLabel('To');
    expect($filter->getMinLabel())->toBe('From')
        ->and($filter->getMaxLabel())->toBe('To');

    $q = Task::query();
    expect($filter->apply($q, ''))->toBe($q);

    $sql = NumberRangeFilter::make('price')->query(fn ($q, $v) => $q->where('price', '>', $v))
        ->apply(Task::query(), ['min' => '5'])->toSql();
    expect($sql)->toContain('"price" >');
});

// ─── SelectFilter ────────────────────────────────────────────────────────────

it('skips empty entries when formatting a select indicator', function () {
    expect(SelectFilter::make('role')->options(['a' => 'Admin'])->getIndicator(['a', '']))
        ->toContain('Admin');
});

// ─── TernaryFilter ───────────────────────────────────────────────────────────

it('guards apply and renders the ternary control', function () {
    $q = Task::query();
    expect(TernaryFilter::make('active')->apply($q, ''))->toBe($q);

    $sql = TernaryFilter::make('active')->query(fn ($q, $v) => $q->where('active', true))
        ->apply(Task::query(), 'true')->toSql();
    expect($sql)->toContain('"active"');

    expect(TernaryFilter::make('active')->render('true'))->toBeString()
        ->and(TernaryFilter::make('active')->hidden()->render())->toBe('');
});

// ─── DateFilter ──────────────────────────────────────────────────────────────

it('guards apply and renders the date filter', function () {
    $q = Task::query();
    expect(DateFilter::make('due_at')->apply($q, ''))->toBe($q);

    $bindings = DateFilter::make('due_at')->query(fn ($q, $v) => $q->whereDate('due_at', $v))
        ->apply(Task::query(), '2026-01-01')->getBindings();
    expect($bindings)->toContain('2026-01-01');

    expect(DateFilter::make('due_at')->render('2026-01-01'))->toBeString()
        ->and(DateFilter::make('due_at')->hidden()->render())->toBe('');
});

it('exposes range-aware state shapes and a formatted month indicator', function () {
    expect(DateFilter::make('due_at')->range()->wrapValue(['from' => 'x']))->toBe(['from' => 'x'])
        ->and(DateFilter::make('due_at')->wrapValue('x'))->toBe(['value' => 'x'])
        ->and(DateFilter::make('due_at')->range()->getQueryStringFields())->toBe(['from' => '_from', 'to' => '_to'])
        ->and(DateFilter::make('due_at')->getQueryStringFields())->toBe(['value' => ''])
        ->and(DateFilter::make('due_at')->month()->getIndicator('2026-03'))->toContain('2026');
});
