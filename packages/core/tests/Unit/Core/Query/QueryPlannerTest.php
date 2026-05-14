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

it('plans simple columns on base table', function () {
    $columns = [
        TextComponent::make('name')->addCapability(Capability::Searchable),
        TextComponent::make('email'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->selectedColumns)->toContain('users.name', 'users.email')
        ->and($plan->hasJoins())->toBeFalse()
        ->and($plan->hasEagerLoads())->toBeFalse();
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

it('plans join for BelongsTo relation column', function () {
    $columns = [
        TextComponent::make('company.name'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->hasJoins())->toBeTrue()
        ->and($plan->joins)->toHaveCount(1)
        ->and($plan->joins[0]->table)->toBe('companies')
        ->and($plan->joins[0]->alias)->toBe('users_company')
        ->and($plan->joins[0]->type)->toBe('left');
});

it('plans nested relation joins', function () {
    $columns = [
        TextComponent::make('company.address.city'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->hasJoins())->toBeTrue()
        ->and($plan->joins)->toHaveCount(2)
        ->and($plan->joins[0]->alias)->toBe('users_company')
        ->and($plan->joins[1]->alias)->toBe('users_company_address');
});

it('deduplicates joins for same relation', function () {
    $columns = [
        TextComponent::make('company.name'),
        TextComponent::make('company.email'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', $columns);

    expect($plan->joins)->toHaveCount(1);
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

it('plans relation filter with join', function () {
    $filters = [
        FilterDefinition::make('company.name', '=', 'Acme'),
    ];

    $plan = $this->planner->plan('App\\Models\\User', filters: $filters);

    expect($plan->filters)->toHaveCount(1)
        ->and($plan->filters[0]->column)->toBe('name')
        ->and($plan->filters[0]->tableAlias)->toBe('users_company')
        ->and($plan->filters[0]->isRelation)->toBeTrue()
        ->and($plan->hasJoins())->toBeTrue();
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
