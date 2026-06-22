<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\AggregateClause;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\SortClause;

// ── SearchClause ─────────────────────────────────────────────

it('creates a simple search clause', function () {
    $clause = new SearchClause('name');

    expect($clause->column)->toBe('name')
        ->and($clause->tableAlias)->toBeNull()
        ->and($clause->sqlExpression)->toBeNull()
        ->and($clause->isRelation)->toBeFalse();
});

it('returns qualified column with table alias', function () {
    $clause = new SearchClause('name', tableAlias: 'users');

    expect($clause->getQualifiedColumn())->toBe('users.name');
});

it('returns sql expression as qualified column when set', function () {
    $clause = new SearchClause('full_name', sqlExpression: "CONCAT(first_name, ' ', last_name)");

    expect($clause->getQualifiedColumn())->toBe("CONCAT(first_name, ' ', last_name)");
});

it('marks search clause as relation', function () {
    $clause = new SearchClause('name', tableAlias: 'users_company', isRelation: true, relationPath: 'company');

    expect($clause->isRelation)->toBeTrue()
        ->and($clause->relationPath)->toBe('company');
});

// ── FilterClause ─────────────────────────────────────────────

it('creates a simple filter clause', function () {
    $clause = new FilterClause('status', '=', 'active');

    expect($clause->column)->toBe('status')
        ->and($clause->operator)->toBe('=')
        ->and($clause->value)->toBe('active')
        ->and($clause->boolean)->toBe('and');
});

it('returns qualified filter column with alias', function () {
    $clause = new FilterClause('name', '=', 'John', tableAlias: 'users_company');

    expect($clause->getQualifiedColumn())->toBe('users_company.name');
});

it('prefers sql expression over table alias', function () {
    $clause = new FilterClause('total', '>=', 100, tableAlias: 'orders', sqlExpression: 'SUM(amount)');

    expect($clause->getQualifiedColumn())->toBe('SUM(amount)');
});

it('detects null check operators', function () {
    expect((new FilterClause('name', 'IS NULL'))->isNullCheck())->toBeTrue()
        ->and((new FilterClause('name', 'IS NOT NULL'))->isNullCheck())->toBeTrue()
        ->and((new FilterClause('name', '=', null))->isNullCheck())->toBeFalse();
});

it('supports or boolean connector', function () {
    $clause = new FilterClause('status', '=', 'active', boolean: 'or');

    expect($clause->boolean)->toBe('or');
});

// ── SortClause ───────────────────────────────────────────────

it('creates a simple sort clause', function () {
    $clause = new SortClause('name', 'asc');

    expect($clause->column)->toBe('name')
        ->and($clause->direction)->toBe('asc')
        ->and($clause->isRelation)->toBeFalse();
});

it('returns qualified sort column', function () {
    $clause = new SortClause('name', 'desc', tableAlias: 'users_company');

    expect($clause->getQualifiedColumn())->toBe('users_company.name');
});

it('normalises nulls position to a bare keyword', function () {
    // Both the bare keyword and the full "NULLS LAST" form are accepted; the bare
    // keyword is stored (ApplySorting supplies the "NULLS" prefix).
    expect((new SortClause('name', 'asc', nullsPosition: 'NULLS LAST'))->nullsPosition)->toBe('LAST')
        ->and((new SortClause('name', 'asc', nullsPosition: 'first'))->nullsPosition)->toBe('FIRST')
        ->and((new SortClause('name', 'asc', nullsPosition: 'garbage'))->nullsPosition)->toBeNull()
        ->and((new SortClause('name', 'asc'))->nullsPosition)->toBeNull();
});

it('normalises sort direction to a safe keyword', function () {
    expect((new SortClause('name', 'DESC'))->direction)->toBe('desc')
        ->and((new SortClause('name', 'asc'))->direction)->toBe('asc')
        ->and((new SortClause('name', 'asc; DROP TABLE users'))->direction)->toBe('asc');
});

// ── AggregateClause ──────────────────────────────────────────

it('creates a count aggregate', function () {
    $clause = new AggregateClause('orders', 'count');

    expect($clause->relation)->toBe('orders')
        ->and($clause->function)->toBe('count')
        ->and($clause->column)->toBeNull()
        ->and($clause->strategy)->toBe('subquery');
});

it('creates a sum aggregate with column', function () {
    $clause = new AggregateClause('orders', 'sum', 'total');

    expect($clause->column)->toBe('total')
        ->and($clause->getAlias())->toBe('orders_sum_total');
});

it('generates alias from relation and function', function () {
    expect((new AggregateClause('orders', 'count'))->getAlias())->toBe('orders_count')
        ->and((new AggregateClause('orders', 'avg', 'total'))->getAlias())->toBe('orders_avg_total');
});

it('uses custom alias when provided', function () {
    $clause = new AggregateClause('orders', 'count', alias: 'order_count');

    expect($clause->getAlias())->toBe('order_count');
});

it('rejects invalid aggregate functions', function () {
    new AggregateClause('orders', 'invalid_function');
})->throws(InvalidArgumentException::class);

it('rejects sum without column', function () {
    new AggregateClause('orders', 'sum');
})->throws(InvalidArgumentException::class);

it('rejects avg without column', function () {
    new AggregateClause('orders', 'avg');
})->throws(InvalidArgumentException::class);

it('rejects invalid strategy', function () {
    new AggregateClause('orders', 'count', strategy: 'invalid');
})->throws(InvalidArgumentException::class);

it('resolves strategy for exists', function () {
    expect(AggregateClause::resolveStrategy('exists', true))->toBe('exists');
});

it('resolves strategy for count as subquery', function () {
    expect(AggregateClause::resolveStrategy('count', true))->toBe('subquery');
});
