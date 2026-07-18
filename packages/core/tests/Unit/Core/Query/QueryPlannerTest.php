<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Components\TextComponent;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\ModelMetadata;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Query\JoinRegistry;
use NyonCode\WireCore\Core\Query\QueryPlanner;
use NyonCode\WireCore\Core\Query\SortDefinition;

beforeEach(function () {
    $this->registry = new MetadataRegistry;
    $this->joinRegistry = new JoinRegistry;

    // Register User model
    $this->registry->registerModelMetadata('App\\Models\\User', new ModelMetadata(
        modelClass: 'App\\Models\\User',
        table: 'users',
        primaryKey: 'id',
        primaryKeyType: 'int',
        incrementing: true,
        usesTimestamps: true,
        usesSoftDeletes: false,
        casts: [],
        fillable: ['name', 'email', 'company_id'],
        guarded: [],
        relations: ['company'],
        appends: [],
    ));

    // Register Company model
    $this->registry->registerModelMetadata('App\\Models\\Company', new ModelMetadata(
        modelClass: 'App\\Models\\Company',
        table: 'companies',
        primaryKey: 'id',
        primaryKeyType: 'int',
        incrementing: true,
        usesTimestamps: true,
        usesSoftDeletes: false,
        casts: [],
        fillable: ['name'],
        guarded: [],
        relations: ['address'],
        appends: [],
    ));

    // Register Address model
    $this->registry->registerModelMetadata('App\\Models\\Address', new ModelMetadata(
        modelClass: 'App\\Models\\Address',
        table: 'addresses',
        primaryKey: 'id',
        primaryKeyType: 'int',
        incrementing: true,
        usesTimestamps: true,
        usesSoftDeletes: false,
        casts: [],
        fillable: ['city', 'street'],
        guarded: [],
        relations: [],
        appends: [],
    ));

    // Register relations
    $this->registry->registerRelation('App\\Models\\User', new RelationMetadata(
        name: 'company',
        type: 'BelongsTo',
        parentModel: 'App\\Models\\User',
        relatedModel: 'App\\Models\\Company',
        foreignKey: 'company_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: false,
    ));

    $this->registry->registerRelation('App\\Models\\Company', new RelationMetadata(
        name: 'address',
        type: 'HasOne',
        parentModel: 'App\\Models\\Company',
        relatedModel: 'App\\Models\\Address',
        foreignKey: 'company_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: false,
    ));

    // Register morph relation
    $this->registry->registerRelation('App\\Models\\User', new RelationMetadata(
        name: 'comments',
        type: 'MorphMany',
        parentModel: 'App\\Models\\User',
        relatedModel: 'App\\Models\\Comment',
        foreignKey: 'commentable_id',
        localKey: 'id',
        morphType: 'commentable_type',
        pivotTable: null,
        isMorph: true,
        isToMany: true,
    ));

    $this->planner = new QueryPlanner($this->registry, $this->joinRegistry);
});

// ── Simple columns ───────────────────────────────────────────

it('plans base columns without joins or eager loads', function () {
    $columns = [
        TextComponent::make('name')->addCapability(Capability::Searchable),
        TextComponent::make('email'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    // Base columns need neither a join nor an eager load — the default select
    // covers them (no column projection is planned any more).
    expect($plan->hasJoins())->toBeFalse()
        ->and($plan->hasEagerLoads())->toBeFalse()
        ->and($plan->isEmpty())->toBeTrue();
});

// ── Search planning ──────────────────────────────────────────

it('plans search clauses for searchable columns', function () {
    $columns = [
        TextComponent::make('name')->addCapability(Capability::Searchable),
        TextComponent::make('email')->addCapability(Capability::Searchable),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns, search: 'john');

    expect($plan->searchClauses)->toHaveCount(2)
        ->and($plan->searchClauses[0]->column)->toBe('name')
        ->and($plan->searchClauses[1]->column)->toBe('email');
});

it('does not plan search clauses for non-searchable columns', function () {
    $columns = [
        TextComponent::make('name'), // no Searchable capability
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns, search: 'john');

    expect($plan->searchClauses)->toHaveCount(0);
});

it('does not plan search clauses when no search term', function () {
    $columns = [
        TextComponent::make('name')->addCapability(Capability::Searchable),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->searchClauses)->toHaveCount(0);
});

it('plans search with sql expression', function () {
    $column = TextComponent::make('full_name')
        ->addCapability(Capability::Searchable)
        ->columnMetadata(ColumnMetadata::forAccessor('full_name', "CONCAT(first_name, ' ', last_name)"));

    $plan = $this->planner->plan('App\\Models\\User', [$column], search: 'john');

    expect($plan->searchClauses)->toHaveCount(1)
        ->and($plan->searchClauses[0]->sqlExpression)->toBe("CONCAT(first_name, ' ', last_name)");
});

// ── Relation column planning ─────────────────────────────────

it('eager loads a BelongsTo relation column for display, without a join', function () {
    $columns = [
        TextComponent::make('company.name'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    // A display-only relation column is read via data_get on the eager-loaded
    // relation; it no longer registers a JOIN (that is only for sort/search).
    expect($plan->hasJoins())->toBeFalse()
        ->and($plan->eagerLoads)->toContain('company');
});

it('eager loads a nested relation column for display, without joins', function () {
    $columns = [
        TextComponent::make('company.address.city'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->hasJoins())->toBeFalse()
        ->and($plan->eagerLoads)->toContain('company.address');
});

it('deduplicates eager loads for the same relation', function () {
    $columns = [
        TextComponent::make('company.name'),
        TextComponent::make('company.email'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    // Two columns on the same relation eager-load it once, and never join.
    expect($plan->hasJoins())->toBeFalse()
        ->and(array_filter($plan->eagerLoads, fn ($p) => $p === 'company'))->toHaveCount(1);
});

it('plans search through relation join', function () {
    $columns = [
        TextComponent::make('company.name')->addCapability(Capability::Searchable),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns, search: 'acme');

    expect($plan->searchClauses)->toHaveCount(1)
        ->and($plan->searchClauses[0]->isRelation)->toBeTrue()
        ->and($plan->searchClauses[0]->tableAlias)->toBe('users_company');
});

it('does not join-search a to-many relation column, eager loading it instead', function () {
    // A searchable column on a non-morph to-many relation cannot be join-searched
    // (a join would multiply rows); it is eager-loaded for display and emits no
    // search clause or join.
    $this->registry->registerRelation('App\\Models\\User', new RelationMetadata(
        name: 'posts',
        type: 'HasMany',
        parentModel: 'App\\Models\\User',
        relatedModel: 'App\\Models\\Company',
        foreignKey: 'user_id',
        localKey: 'id',
        morphType: null,
        pivotTable: null,
        isMorph: false,
        isToMany: true,
    ));

    $columns = [
        TextComponent::make('posts.title')->addCapability(Capability::Searchable),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns, search: 'hello');

    expect($plan->hasJoins())->toBeFalse()
        ->and($plan->searchClauses)->toHaveCount(0)
        ->and($plan->eagerLoads)->toContain('posts');
});

// ── Morph relation planning ──────────────────────────────────

it('uses eager loading for morph relations instead of joins', function () {
    $columns = [
        TextComponent::make('comments.body'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->hasJoins())->toBeFalse()
        ->and($plan->eagerLoads)->toContain('comments');
});

// ── Aggregate planning ───────────────────────────────────────

it('plans aggregate columns', function () {
    $columns = [
        TextComponent::make('comments->count()'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->aggregates)->toHaveCount(1)
        ->and($plan->aggregates[0]->relation)->toBe('comments')
        ->and($plan->aggregates[0]->function)->toBe('count')
        ->and($plan->aggregates[0]->strategy)->toBe('subquery');
});

it('plans aggregate with column', function () {
    $columns = [
        TextComponent::make('comments->sum(likes)'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->aggregates)->toHaveCount(1)
        ->and($plan->aggregates[0]->function)->toBe('sum')
        ->and($plan->aggregates[0]->column)->toBe('likes');
});

// ── Filter planning ──────────────────────────────────────────

it('plans simple filter', function () {
    $filters = [
        FilterDefinition::make('status', '=', 'active'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', filters: $filters);

    expect($plan->filters)->toHaveCount(1)
        ->and($plan->filters[0]->column)->toBe('status')
        ->and($plan->filters[0]->operator)->toBe('=')
        ->and($plan->filters[0]->value)->toBe('active')
        ->and($plan->filters[0]->tableAlias)->toBe('users');
});

it('plans relation filter natively without a join', function () {
    $filters = [
        FilterDefinition::make('company.name', '=', 'Acme'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', filters: $filters);

    // Relation filters are applied via whereHas() in ApplyFilters, not a JOIN:
    // no alias, no join registered, just the relation path + terminal column.
    expect($plan->filters)->toHaveCount(1)
        ->and($plan->filters[0]->column)->toBe('name')
        ->and($plan->filters[0]->tableAlias)->toBeNull()
        ->and($plan->filters[0]->isRelation)->toBeTrue()
        ->and($plan->filters[0]->relationPath)->toBe('company')
        ->and($plan->hasJoins())->toBeFalse();
});

it('plans a morph relation filter as an eager load, still applied via whereHas', function () {
    $filters = [
        FilterDefinition::make('comments.body', 'LIKE', '%hi%'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', filters: $filters);

    // Morph filters emit a relation clause (whereHas at apply time) and add the
    // relation as a display eager-load; they never register a join.
    expect($plan->filters)->toHaveCount(1)
        ->and($plan->filters[0]->column)->toBe('body')
        ->and($plan->filters[0]->isRelation)->toBeTrue()
        ->and($plan->filters[0]->relationPath)->toBe('comments')
        ->and($plan->hasJoins())->toBeFalse()
        ->and($plan->eagerLoads)->toContain('comments');
});

it('plans a to-many relation filter (whereHas can express what a join cannot)', function () {
    $filters = [
        FilterDefinition::make('posts.title', 'LIKE', '%hello%'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', filters: $filters);

    // A join-based planner silently dropped to-many filters; whereHas keeps them.
    expect($plan->filters)->toHaveCount(1)
        ->and($plan->filters[0]->column)->toBe('title')
        ->and($plan->filters[0]->isRelation)->toBeTrue()
        ->and($plan->filters[0]->relationPath)->toBe('posts')
        ->and($plan->hasJoins())->toBeFalse();
});

it('plans an aggregate filter as an aggregate clause (whereHas, no join/HAVING)', function () {
    $filters = [
        FilterDefinition::make('orders->count()', '>', 5),
    ];

    $plan = $this->planner->plan('App\\Models\\User', filters: $filters);

    expect($plan->filters)->toHaveCount(1)
        ->and($plan->filters[0]->isAggregate)->toBeTrue()
        ->and($plan->filters[0]->aggregateRelation)->toBe('orders')
        ->and($plan->filters[0]->aggregateFunction)->toBe('count')
        ->and($plan->filters[0]->operator)->toBe('>')
        ->and($plan->filters[0]->value)->toBe(5)
        ->and($plan->filters[0]->isRelation)->toBeFalse()
        ->and($plan->hasJoins())->toBeFalse();
});

// ── Sort planning ────────────────────────────────────────────

it('plans simple sort', function () {
    $sorts = [
        SortDefinition::make('name', 'asc'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', sorts: $sorts);

    expect($plan->sortClauses)->toHaveCount(1)
        ->and($plan->sortClauses[0]->column)->toBe('name')
        ->and($plan->sortClauses[0]->direction)->toBe('asc')
        ->and($plan->sortClauses[0]->tableAlias)->toBe('users');
});

it('plans relation sort with join', function () {
    $sorts = [
        SortDefinition::make('company.name', 'desc'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', sorts: $sorts);

    expect($plan->sortClauses)->toHaveCount(1)
        ->and($plan->sortClauses[0]->column)->toBe('name')
        ->and($plan->sortClauses[0]->tableAlias)->toBe('users_company')
        ->and($plan->sortClauses[0]->isRelation)->toBeTrue()
        ->and($plan->hasJoins())->toBeTrue();
});

it('skips morph relation sorting', function () {
    $sorts = [
        SortDefinition::make('comments.body', 'asc'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', sorts: $sorts);

    expect($plan->sortClauses)->toHaveCount(0);
});

// ── Scopes & soft deletes ────────────────────────────────────

it('passes scopes to query plan', function () {
    $plan = $this->planner->plan('App\\Models\\User', scopes: ['active', 'verified']);

    expect($plan->scopes)->toBe(['active', 'verified']);
});

it('passes soft deletes flag to query plan', function () {
    $plan = $this->planner->plan('App\\Models\\User', withSoftDeletes: true);

    expect($plan->withSoftDeletes)->toBeTrue();
});

// ── Combined planning ────────────────────────────────────────

it('shares joins between columns, filters, and sorts on same relation', function () {
    $columns = [
        TextComponent::make('company.name'),
    ];
    $filters = [
        FilterDefinition::make('company.name', 'LIKE', '%acme%'),
    ];
    $sorts = [
        SortDefinition::make('company.name', 'asc'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns, $filters, $sorts);

    // Should reuse the same join (deduplicated)
    expect($plan->joins)->toHaveCount(1)
        ->and($plan->joins[0]->alias)->toBe('users_company');
});

it('plans empty query plan when no inputs', function () {
    $plan = $this->planner->plan('App\\Models\\User');

    expect($plan->isEmpty())->toBeTrue();
});

it('builds relation graph from columns', function () {
    $columns = [
        TextComponent::make('company.name'),
        TextComponent::make('company.address.city'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->relationGraph)->not->toBeNull()
        ->and($plan->relationGraph->hasRelation('company'))->toBeTrue();
});
