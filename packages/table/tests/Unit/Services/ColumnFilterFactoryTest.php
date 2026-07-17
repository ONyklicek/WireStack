<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\ColumnFilterFactory;

/*
 * Which Filter backs each filterAs*() — the vocabulary Column used to spell out
 * itself, importing all five concrete filter types to do it.
 */

beforeEach(function () {
    $this->factory = new ColumnFilterFactory;
});

test('each filterAs* shape builds its filter', function () {
    expect($this->factory->text('a'))->toBeInstanceOf(TextFilter::class)
        ->and($this->factory->select('a', ['x' => 'X']))->toBeInstanceOf(SelectFilter::class)
        ->and($this->factory->date('a'))->toBeInstanceOf(DateFilter::class)
        ->and($this->factory->numberRange('a'))->toBeInstanceOf(NumberRangeFilter::class)
        ->and($this->factory->boolean('a'))->toBeInstanceOf(TernaryFilter::class);
});

test('a select is searchable and carries its options and placeholder', function () {
    $filter = $this->factory->select('role', ['admin' => 'Admin'], 'Pick one');

    expect($filter->getOptions())->toBe(['admin' => 'Admin'])
        ->and($filter->getPlaceholder())->toBe('Pick one')
        ->and($filter->isMultiple())->toBeFalse();
});

test('a multiple select binds an array', function () {
    // The host seeds columnFilters.<name> to [] off this, so it has to be right.
    expect($this->factory->select('role', ['a' => 'A'], multiple: true)->isMultiple())->toBeTrue();
});

test('the legacy type vocabulary maps to the same filters', function () {
    expect($this->factory->ofType('select', 'a', ['x' => 'X']))->toBeInstanceOf(SelectFilter::class)
        ->and($this->factory->ofType('multi_select', 'a', ['x' => 'X'])->isMultiple())->toBeTrue()
        ->and($this->factory->ofType('date', 'a'))->toBeInstanceOf(DateFilter::class)
        ->and($this->factory->ofType('date_range', 'a'))->toBeInstanceOf(DateFilter::class)
        ->and($this->factory->ofType('number_range', 'a'))->toBeInstanceOf(NumberRangeFilter::class)
        ->and($this->factory->ofType('boolean', 'a'))->toBeInstanceOf(TernaryFilter::class);
});

test('an unknown type falls back to a text filter rather than throwing', function () {
    expect($this->factory->ofType('nonsense', 'a'))->toBeInstanceOf(TextFilter::class);
});

test('the filter targets the column it was built for', function () {
    expect($this->factory->text('created_at')->getName())->toBe('created_at');
});
