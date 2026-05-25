<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\Column;

// ─── Aggregate: counts ─────────────────────────────────────────────

test('counts sets aggregate function and relation', function () {
    $column = Column::make('orders_count')->counts('orders');

    expect($column->isAggregate())->toBeTrue()
        ->and($column->getAggregateFunction())->toBe('count')
        ->and($column->getAggregateRelation())->toBe('orders')
        ->and($column->getAggregateColumn())->toBeNull();
});

test('counts aggregate attribute name', function () {
    $column = Column::make('orders_count')->counts('orders');

    expect($column->getAggregateAttribute())->toBe('orders_count');
});

// ─── Aggregate: sums ───────────────────────────────────────────────

test('sums sets aggregate function, relation and column', function () {
    $column = Column::make('orders_total')->sums('orders', 'total');

    expect($column->isAggregate())->toBeTrue()
        ->and($column->getAggregateFunction())->toBe('sum')
        ->and($column->getAggregateRelation())->toBe('orders')
        ->and($column->getAggregateColumn())->toBe('total');
});

test('sums aggregate attribute name', function () {
    $column = Column::make('orders_total')->sums('orders', 'total');

    expect($column->getAggregateAttribute())->toBe('orders_sum_total');
});

// ─── Aggregate: averages ───────────────────────────────────────────

test('averages sets aggregate correctly', function () {
    $column = Column::make('avg_rating')->averages('reviews', 'rating');

    expect($column->isAggregate())->toBeTrue()
        ->and($column->getAggregateFunction())->toBe('avg')
        ->and($column->getAggregateAttribute())->toBe('reviews_avg_rating');
});

// ─── Aggregate: mins ───────────────────────────────────────────────

test('mins sets aggregate correctly', function () {
    $column = Column::make('lowest_price')->mins('products', 'price');

    expect($column->isAggregate())->toBeTrue()
        ->and($column->getAggregateFunction())->toBe('min')
        ->and($column->getAggregateAttribute())->toBe('products_min_price');
});

// ─── Aggregate: maxes ──────────────────────────────────────────────

test('maxes sets aggregate correctly', function () {
    $column = Column::make('highest_price')->maxes('products', 'price');

    expect($column->isAggregate())->toBeTrue()
        ->and($column->getAggregateFunction())->toBe('max')
        ->and($column->getAggregateAttribute())->toBe('products_max_price');
});

// ─── Non-aggregate column ──────────────────────────────────────────

test('regular column is not aggregate', function () {
    $column = Column::make('name');

    expect($column->isAggregate())->toBeFalse()
        ->and($column->getAggregateFunction())->toBeNull()
        ->and($column->getAggregateRelation())->toBeNull()
        ->and($column->getAggregateAttribute())->toBeNull();
});

test('aggregate column has null column for counts', function () {
    $column = Column::make('orders_count')->counts('orders');

    expect($column->getAggregateColumn())->toBeNull();
});

test('sums aggregate column is set', function () {
    $column = Column::make('total')->sums('orders', 'amount');

    expect($column->getAggregateColumn())->toBe('amount');
});

test('aggregate methods are chainable', function () {
    $column = Column::make('avg_rating')
        ->averages('reviews', 'rating')
        ->label('Average Rating');

    expect($column->isAggregate())->toBeTrue()
        ->and($column->getLabel())->toBe('Average Rating');
});

test('regular column getAggregateColumn returns null', function () {
    $column = Column::make('name');

    expect($column->getAggregateColumn())->toBeNull();
});
