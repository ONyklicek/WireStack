<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\TableQueryService;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Tests\TestCase;

uses(TestCase::class);

// ─── Test Models ─────────────────────────────────────────────────────────────

class TqsUser extends Model
{
    protected $table = 'tqs_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(TqsCompany::class, 'company_id');
    }
}

class TqsCompany extends Model
{
    protected $table = 'tqs_companies';

    protected $guarded = [];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('tqs_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('tqs_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->integer('age')->default(0);
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });

    TqsCompany::create(['id' => 1, 'name' => 'Acme Corp']);
    TqsCompany::create(['id' => 2, 'name' => 'Evil Corp']);

    TqsUser::create(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30, 'company_id' => 1]);
    TqsUser::create(['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25, 'company_id' => 2]);
    TqsUser::create(['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35, 'company_id' => 1]);
});

afterEach(function () {
    Schema::dropIfExists('tqs_users');
    Schema::dropIfExists('tqs_companies');
});

// ─── Basic Query Building ────────────────────────────────────────────────────

it('builds a basic query without search/filter/sort', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
            Column::make('email'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
    );

    $results = $query->get();
    expect($results)->toHaveCount(3);
});

it('produces a QueryPlan after building', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable(),
            Column::make('email'),
        ]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'Alice',
    );

    $plan = $service->getLastPlan();
    expect($plan)->not->toBeNull()
        ->and($plan->hasSearch())->toBeTrue()
        ->and($plan->searchClauses)->toHaveCount(1);
});

// ─── Search ──────────────────────────────────────────────────────────────────

it('applies search to searchable columns', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable(),
            Column::make('email')->searchable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'alice',
    );

    $results = $query->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Alice');
});

it('handles custom search callbacks', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable(query: function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            }),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'Bob',
    );

    $results = $query->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Bob');
});

// ─── Sorting ─────────────────────────────────────────────────────────────────

it('applies sorting', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->sortable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'desc',
    );

    $results = $query->get();
    expect($results->first()->name)->toBe('Charlie')
        ->and($results->last()->name)->toBe('Alice');
});

it('handles custom sort callbacks', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('age')->sortable(query: function ($query, $direction) {
                return $query->orderBy('age', $direction);
            }),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'age',
        sortDirection: 'asc',
    );

    $results = $query->get();
    expect($results->first()->age)->toBe(25)
        ->and($results->last()->age)->toBe(35);
});

// ─── Filters ─────────────────────────────────────────────────────────────────

it('applies basic filter', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
        ])
        ->filters([
            SelectFilter::make('company_id')
                ->options(['1' => 'Acme', '2' => 'Evil']),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company_id' => 1],
    );

    $results = $query->get();
    expect($results)->toHaveCount(2); // Alice and Charlie
});

it('handles custom filter query callbacks', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
        ])
        ->filters([
            Filter::make('age_min')
                ->query(fn ($query, $value) => $query->where('age', '>=', $value)),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['age_min' => 30],
    );

    $results = $query->get();
    expect($results)->toHaveCount(2); // Alice (30) and Charlie (35)
});

// ─── Relation Columns ────────────────────────────────────────────────────────

it('handles relation columns with eager loading', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
            Column::make('company.name'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
    );

    $plan = $service->getLastPlan();
    expect($plan)->not->toBeNull();

    // Should have either joins or eager loads for the company relation
    $hasJoinsOrEagerLoads = $plan->hasJoins() || $plan->hasEagerLoads();
    expect($hasJoinsOrEagerLoads)->toBeTrue();
});

// ─── Combined Operations ─────────────────────────────────────────────────────

it('handles search + filter + sort together', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable()->sortable(),
            Column::make('email')->searchable(),
        ])
        ->filters([
            Filter::make('company_id'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'example.com',
        filterValues: ['company_id' => 1],
        sortColumn: 'name',
        sortDirection: 'asc',
    );

    $results = $query->get();
    // Both Alice and Charlie are in company 1 and have example.com emails
    expect($results)->toHaveCount(2)
        ->and($results->first()->name)->toBe('Alice')
        ->and($results->last()->name)->toBe('Charlie');
});

// ─── Empty Filters Ignored ───────────────────────────────────────────────────

it('ignores empty filter values', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')])
        ->filters([Filter::make('company_id')]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company_id' => null],
    );

    $results = $query->get();
    expect($results)->toHaveCount(3); // All users
});

it('ignores sort for non-sortable columns', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'desc',
    );

    // Should still work, just no ordering
    $results = $query->get();
    expect($results)->toHaveCount(3);
});
