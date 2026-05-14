<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Query\AggregateClause;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\JoinClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Core\Relations\RelationGraph;

it('creates an empty query plan', function () {
    $plan = new QueryPlan;

    expect($plan->isEmpty())->toBeTrue()
        ->and($plan->hasJoins())->toBeFalse()
        ->and($plan->hasEagerLoads())->toBeFalse()
        ->and($plan->hasAggregates())->toBeFalse()
        ->and($plan->hasFilters())->toBeFalse()
        ->and($plan->hasSearch())->toBeFalse()
        ->and($plan->hasSorting())->toBeFalse()
        ->and($plan->hasScopes())->toBeFalse();
});

it('reports non-empty when it has joins', function () {
    $plan = new QueryPlan(
        joins: [new JoinClause('companies', 'users_company', 'users.company_id', '=', 'users_company.id')],
    );

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasJoins())->toBeTrue();
});

it('reports non-empty when it has eager loads', function () {
    $plan = new QueryPlan(eagerLoads: ['comments']);

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasEagerLoads())->toBeTrue();
});

it('reports non-empty when it has aggregates', function () {
    $plan = new QueryPlan(
        aggregates: [new AggregateClause('orders', 'count')],
    );

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasAggregates())->toBeTrue();
});

it('reports non-empty when it has filters', function () {
    $plan = new QueryPlan(
        filters: [new FilterClause('status', '=', 'active')],
    );

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasFilters())->toBeTrue();
});

it('reports non-empty when it has search clauses', function () {
    $plan = new QueryPlan(
        searchClauses: [new SearchClause('name')],
    );

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasSearch())->toBeTrue();
});

it('reports non-empty when it has sort clauses', function () {
    $plan = new QueryPlan(
        sortClauses: [new SortClause('name', 'asc')],
    );

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasSorting())->toBeTrue();
});

it('reports non-empty when it has scopes', function () {
    $plan = new QueryPlan(scopes: ['active']);

    expect($plan->isEmpty())->toBeFalse()
        ->and($plan->hasScopes())->toBeTrue();
});

it('merges joins via withJoins', function () {
    $plan = new QueryPlan(
        joins: [new JoinClause('companies', 'c1', 'a', '=', 'b')],
    );

    $newPlan = $plan->withJoins([
        new JoinClause('addresses', 'a1', 'x', '=', 'y'),
    ]);

    expect($newPlan->joins)->toHaveCount(2)
        ->and($plan->joins)->toHaveCount(1); // original unchanged
});

it('merges eager loads via withEagerLoads deduplicating', function () {
    $plan = new QueryPlan(eagerLoads: ['comments']);
    $newPlan = $plan->withEagerLoads(['comments', 'tags']);

    expect($newPlan->eagerLoads)->toHaveCount(2)
        ->and($newPlan->eagerLoads)->toContain('comments', 'tags');
});

it('merges aggregates via withAggregates', function () {
    $plan = new QueryPlan(
        aggregates: [new AggregateClause('orders', 'count')],
    );

    $newPlan = $plan->withAggregates([
        new AggregateClause('orders', 'sum', 'total'),
    ]);

    expect($newPlan->aggregates)->toHaveCount(2);
});

it('preserves relation graph', function () {
    $graph = new RelationGraph;
    $plan = new QueryPlan(relationGraph: $graph);

    expect($plan->relationGraph)->toBe($graph);
});

it('preserves withSoftDeletes flag', function () {
    $plan = new QueryPlan(withSoftDeletes: true);

    expect($plan->withSoftDeletes)->toBeTrue();
});
