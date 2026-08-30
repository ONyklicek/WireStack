<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Data\ArrayRecord;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Query\AggregateClause;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\JoinClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Exceptions\UnsupportedQueryAspectException;
use NyonCode\WireTable\Data\CollectionDataSource;
use NyonCode\WireTable\Table;

function cdsRows(): array
{
    return [
        ['id' => 1, 'name' => 'Ada', 'score' => 90, 'team' => ['name' => 'blue']],
        ['id' => 2, 'name' => 'Grace', 'score' => 70, 'team' => ['name' => 'red']],
        ['id' => 3, 'name' => 'Alan', 'score' => 80, 'team' => ['name' => 'blue']],
    ];
}

function cds(): CollectionDataSource
{
    return new CollectionDataSource(cdsRows());
}

// ─── What it can do ──────────────────────────────────────────────────────────

it('counts and reads rows with no plan at all', function () {
    expect(cds()->count(new QueryPlan))->toBe(3)
        ->and(cds()->get(new QueryPlan))->toHaveCount(3);
});

it('filters on the declarative clauses a plan carries', function () {
    $plan = new QueryPlan(filters: [new FilterClause('score', '>=', 80)]);

    expect(cds()->count($plan))->toBe(2)
        ->and(cds()->get($plan)->pluck('name')->all())->toBe(['Ada', 'Alan']);
});

it('supports the operators a table actually emits', function () {
    $of = fn (FilterClause $c): array => cds()->get(new QueryPlan(filters: [$c]))->pluck('name')->all();

    expect($of(new FilterClause('name', '=', 'Ada')))->toBe(['Ada'])
        ->and($of(new FilterClause('name', '!=', 'Ada')))->toBe(['Grace', 'Alan'])
        ->and($of(new FilterClause('score', '<', 80)))->toBe(['Grace'])
        ->and($of(new FilterClause('name', 'like', '%la%')))->toBe(['Alan'])
        ->and($of(new FilterClause('id', 'in', [1, 3])))->toBe(['Ada', 'Alan'])
        // The comparison arms a numeric column filter emits, each one its own
        // branch: a wrong operator here silently returns the wrong rows.
        ->and($of(new FilterClause('score', '>', 80)))->toBe(['Ada'])
        ->and($of(new FilterClause('score', '>=', 80)))->toBe(['Ada', 'Alan'])
        ->and($of(new FilterClause('score', '<=', 80)))->toBe(['Grace', 'Alan'])
        ->and($of(new FilterClause('name', '<>', 'Ada')))->toBe(['Grace', 'Alan']);
});

it('refuses a plan carrying a join', function () {
    // A collection has nothing to join to, and the capability is not declared —
    // so this must say so rather than quietly ignoring the clause and handing
    // back rows that look right.
    $plan = new QueryPlan(joins: [new JoinClause('teams', 't', 'rows.team_id', '=', 't.id')]);

    cds()->get($plan);
})->throws(UnsupportedQueryAspectException::class, 'joinable');

it('sorts, and sorts by the most significant clause first', function () {
    $plan = new QueryPlan(sortClauses: [
        new SortClause('team.name'),   // not a relation — a plain nested key
        new SortClause('score', 'desc'),
    ]);

    // team.name is absent as a flat key, so every row sorts equal on it and the
    // score clause decides — which is the point: the first clause wins.
    expect(cds()->get($plan)->pluck('score')->all())->toBe([90, 80, 70]);
});

it('pages length-aware, and knows its total', function () {
    $page = cds()->paginate(new QueryPlan, PagingRequest::lengthAware(2, 1));

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(3)
        ->and($page->count())->toBe(2)
        ->and($page->lastPage())->toBe(2);
});

it('pages simple without claiming a total', function () {
    $page = cds()->paginate(new QueryPlan, PagingRequest::simple(2, 1));

    expect($page)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->hasMorePages())->toBeTrue()
        ->and(cds()->paginate(new QueryPlan, PagingRequest::simple(2, 2))->hasMorePages())->toBeFalse();
});

it('resolves records, as contracts that unwrap to the array', function () {
    $record = cds()->resolveRecord(2);

    expect($record)->toBeInstanceOf(ArrayRecord::class)
        ->and($record->getKey())->toBe(2)
        ->and($record->get('name'))->toBe('Grace')
        ->and($record->get('team.name'))->toBe('red')
        ->and($record->unwrap())->toBeArray()
        // toArray() is the contract's own accessor, distinct from unwrap():
        // RecordContract promises it for every source, so an exporter or an
        // audit entry can read a row without knowing what backs it.
        ->and($record->toArray())->toBe([
            'id' => 2, 'name' => 'Grace', 'score' => 70, 'team' => ['name' => 'red'],
        ])
        ->and(cds()->resolveRecord(99))->toBeNull()
        ->and(cds()->resolveRecords([1, 3])->map(fn (RecordContract $r) => $r->getKey())->all())->toBe([1, 3]);
});

// ─── What it refuses, and that it refuses loudly ─────────────────────────────

it('declares only what an in-memory list can answer', function () {
    $caps = cds()->capabilities();

    expect($caps->has(Capability::Filterable))->toBeTrue()
        ->and($caps->has(Capability::Sortable))->toBeTrue()
        ->and($caps->has(Capability::SqlExpression))->toBeFalse()
        ->and($caps->has(Capability::Joinable))->toBeFalse()
        ->and($caps->has(Capability::Aggregateable))->toBeFalse()
        ->and($caps->has(Capability::ChangeToken))->toBeFalse();
});

it('refuses a raw SQL expression rather than ignoring it', function () {
    $plan = new QueryPlan(filters: [new FilterClause('x', '=', 1, sqlExpression: 'LOWER(name)')]);

    expect(fn () => cds()->get($plan))
        ->toThrow(UnsupportedQueryAspectException::class, '[sql_expression]');
});

it('refuses a clause that reaches through a relation', function () {
    $plan = new QueryPlan(sortClauses: [new SortClause('name', 'asc', isRelation: true)]);

    expect(fn () => cds()->get($plan))
        ->toThrow(UnsupportedQueryAspectException::class, '[joinable]');
});

it('refuses subquery aggregates', function () {
    $plan = new QueryPlan(aggregates: [new AggregateClause('items', 'sum', 'total')]);

    expect(fn () => cds()->count($plan))
        ->toThrow(UnsupportedQueryAspectException::class, '[aggregateable]');
});

it('refuses cursor paging instead of faking a cursor over offsets', function () {
    expect(fn () => cds()->paginate(new QueryPlan, PagingRequest::cursor(2)))
        ->toThrow(UnsupportedQueryAspectException::class, '[cursor paging]');
});

it('refuses an operator it has no meaning for', function () {
    $plan = new QueryPlan(filters: [new FilterClause('name', 'ilike-ish', 'x')]);

    expect(fn () => cds()->get($plan))
        ->toThrow(UnsupportedQueryAspectException::class, 'filter operator');
});

it('says it has no change token rather than inventing one', function () {
    expect(cds()->changeToken(new QueryPlan))->toBeNull();
});

// ─── The whole point: a table with no model ──────────────────────────────────

it('drives a table that has no model and no builder', function () {
    // The V2.0 exit criterion. Before the contract this table could not exist:
    // Table::getQuery() would throw, because the only two answers were a model
    // class and an Eloquent builder.
    $table = Table::make()->dataSource(cds());

    expect($table->hasCustomDataSource())->toBeTrue()
        ->and($table->getDataSource()->count(new QueryPlan))->toBe(3)
        ->and($table->getDataSource()->resolveRecord(1)?->get('name'))->toBe('Ada');
});

// ─── Streaming ───────────────────────────────────────────────────────────────

it('streams in batches instead of materialising everything', function () {
    $batches = [];

    cds()->chunk(new QueryPlan, 2, function ($rows) use (&$batches): void {
        $batches[] = $rows->pluck('name')->all();
    });

    expect($batches)->toBe([['Ada', 'Grace'], ['Alan']]);
});

it('stops streaming when the callback says so', function () {
    $seen = 0;

    cds()->chunk(new QueryPlan, 1, function () use (&$seen): bool {
        $seen++;

        return false;
    });

    expect($seen)->toBe(1);
});

it('streams only what the plan matched', function () {
    $names = [];

    cds()->chunk(new QueryPlan(filters: [new FilterClause('score', '>=', 80)]), 10, function ($rows) use (&$names): void {
        $names = $rows->pluck('name')->all();
    });

    expect($names)->toBe(['Ada', 'Alan']);
});
