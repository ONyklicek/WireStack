<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Query\SortDefinition;

// ── FilterDefinition ─────────────────────────────────────────

it('creates a simple filter definition', function () {
    $filter = FilterDefinition::make('status', '=', 'active');

    expect($filter->column)->toBe('status')
        ->and($filter->operator)->toBe('=')
        ->and($filter->value)->toBe('active')
        ->and($filter->relationPath)->toBeNull();
});

it('parses relation path from column name', function () {
    $filter = FilterDefinition::make('company.name', '=', 'Acme');

    expect($filter->column)->toBe('name')
        ->and($filter->relationPath)->not->toBeNull()
        ->and($filter->relationPath->getRelationPath())->toBe('company');
});

it('parses nested relation path', function () {
    $filter = FilterDefinition::make('company.address.city', '=', 'Prague');

    expect($filter->column)->toBe('city')
        ->and($filter->relationPath->getRelationPath())->toBe('company.address');
});

it('preserves sql expression', function () {
    $filter = FilterDefinition::make('total', '>=', 100, sqlExpression: 'SUM(amount)');

    expect($filter->sqlExpression)->toBe('SUM(amount)');
});

// ── SortDefinition ───────────────────────────────────────────

it('creates a simple sort definition', function () {
    $sort = SortDefinition::make('name', 'asc');

    expect($sort->column)->toBe('name')
        ->and($sort->direction)->toBe('asc')
        ->and($sort->relationPath)->toBeNull();
});

it('parses relation path from sort column', function () {
    $sort = SortDefinition::make('company.name', 'desc');

    expect($sort->column)->toBe('name')
        ->and($sort->direction)->toBe('desc')
        ->and($sort->relationPath)->not->toBeNull()
        ->and($sort->relationPath->getRelationPath())->toBe('company');
});

it('preserves sql expression for sort', function () {
    $sort = SortDefinition::make('total', 'desc', sqlExpression: 'COALESCE(total, 0)');

    expect($sort->sqlExpression)->toBe('COALESCE(total, 0)');
});
