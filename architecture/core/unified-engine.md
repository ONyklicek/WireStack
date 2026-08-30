# Unified Data UI Engine

The Unified Engine (`Core/` namespace) is shared infrastructure powering both wire-table and wire-forms. It provides metadata analysis, query planning/execution, state management, hydration, validation, action pipelines, and events.

See [ADR 0013](../decisions/0013-unified-data-ui-engine.md) for the decision record.

---

## Table of Contents

1. [Metadata System](#metadata-system)
2. [Capability System](#capability-system)
3. [Relation AST](#relation-ast)
4. [Query Planning](#query-planning)
5. [Query Execution](#query-execution)
6. [Search Strategies](#search-strategies)
7. [State Engine](#state-engine)
8. [Hydration System](#hydration-system)
9. [Validation System](#validation-system)
10. [Action Pipeline](#action-pipeline)
11. [Events](#events)
12. [DataComponent](#datacomponent)
13. [Support Utilities](#support-utilities)

---

## Metadata System

Analyzes Eloquent models at runtime and caches the results.

### Classes

| Class | Description |
|-------|-------------|
| `ModelMetadata` | Table name, primary key, fillable, casts, soft deletes |
| `RelationMetadata` | Relation type, foreign key, related model, morph info |
| `ColumnMetadata` | DB column type, nullable, default value |
| `AccessorMetadata` | Eloquent accessor return type, pure/computed flag |
| `MetadataRegistry` | Central registry: model → metadata mappings |
| `MetadataCache` | In-memory cache layer |

### Usage

```php
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\ModelMetadata;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;

$registry = new MetadataRegistry();

// Register model metadata
$registry->registerModelMetadata(User::class, new ModelMetadata(
    table: 'users',
    primaryKey: 'id',
    fillable: ['name', 'email', 'role'],
    casts: ['email_verified_at' => 'datetime'],
    softDeletes: false,
));

// Register relation
$registry->registerRelation(
    User::class,
    new RelationMetadata(
        name: 'posts',
        type: 'hasMany',
        relatedModel: Post::class,
        foreignKey: 'user_id',
    ),
);

// Query
$model = $registry->getModelMetadata(User::class);
$relation = $registry->getRelation(User::class, 'posts');
$registry->hasModel(User::class); // true

// Column metadata
$column = $registry->getColumn(User::class, 'email');
$columns = $registry->getColumns(User::class);

// Accessor metadata
$accessor = $registry->getAccessor(User::class, 'full_name');
$accessors = $registry->getAccessors(User::class);

// Flush cache
$registry->flush();
```

### Auto-Registration

`TableQueryService` automatically registers metadata by introspecting the Eloquent model and its relations based on the table's column configuration.

---

## Capability System

Resolves what a component (column/field) can do based on its metadata.

### Capability Enum

```php
use NyonCode\WireCore\Core\Capabilities\Capability;

Capability::Searchable      // can be searched
Capability::Sortable        // can be sorted
Capability::Filterable      // can be filtered
Capability::Editable        // can be inline-edited
Capability::Dehydrated      // included in dehydration
Capability::Hydrated        // included in hydration
Capability::RuntimeOnly     // computed at runtime, not in DB
Capability::RequiresHydration
Capability::Aggregateable   // supports aggregates (count, sum, ...)
Capability::SqlExpression   // resolves to a SQL expression
```

### CapabilitySet

Immutable set with convenience methods:

```php
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;

$caps = new CapabilitySet(Capability::Searchable, Capability::Sortable);

$caps->has(Capability::Searchable);   // true
$caps->isSearchable();                // true
$caps->isSortable();                  // true
$caps->isFilterable();                // false
$caps->isEditable();                  // false
$caps->isRuntimeOnly();              // false
$caps->hasSqlExpression();           // false
$caps->count();                       // 2
$caps->isEmpty();                     // false

// Immutable operations — return new CapabilitySet
$caps2 = $caps->add(Capability::Filterable);
$caps3 = $caps->remove(Capability::Sortable);
```

### CapabilityResolver

Automatically resolves capabilities from metadata:

```php
use NyonCode\WireCore\Core\Capabilities\CapabilityResolver;

$capabilities = CapabilityResolver::resolve(
    columnMetadata: $columnMeta,    // nullable
    accessorMetadata: $accessorMeta, // nullable
    hints: ['searchable' => true],   // from component config
);
// Returns CapabilitySet
```

Resolution logic:
- DB columns → Searchable, Sortable, Filterable, Aggregateable
- Accessors → RuntimeOnly (non-SQL), Hydrated
- String/text columns → Searchable
- Numeric columns → Sortable, Aggregateable
- Component hints override metadata-based inference

### DataComponent Integration

```php
$column = TextColumn::make('name')
    ->addCapability(Capability::Searchable, Capability::Sortable)
    ->removeCapability(Capability::Editable);

$column->hasCapability(Capability::Searchable); // true
$column->getCapabilities();                     // CapabilitySet
```

---

## Relation AST

Parses dot-notation relation paths into structured Abstract Syntax Trees.

### Segment Types

| Class | Pattern | Example |
|-------|---------|---------|
| `RelationSegment` | Eloquent relation name | `author` in `author.name` |
| `ColumnSegment` | Terminal DB column | `name` in `author.name` |
| `AggregateSegment` | Aggregate function | `sum` + `total` in `orders.sum.total` |
| `PivotSegment` | Pivot table access | `pivot` in `roles.pivot.assigned_at` |
| `MorphSegment` | Morph relation | `commentable` in `commentable.title` |

All implement the `Segment` interface: `getName(): string`, `isTerminal(): bool`.

### RelationPath

```php
use NyonCode\WireCore\Core\Relations\RelationPath;

// Simple column (no relation)
$path = RelationPath::parse('name');
$path->hasRelation();     // false
$path->isSimple();        // true
$path->getColumnName();   // 'name'

// Relation column
$path = RelationPath::parse('author.name');
$path->hasRelation();         // true
$path->getRelationPath();     // 'author'
$path->getColumnName();       // 'name'
$path->getRelationSegments(); // [RelationSegment('author')]
$path->depth();               // 2

// Nested relation
$path = RelationPath::parse('author.country.name');
$path->getRelationPath();     // 'author.country'
$path->getColumnName();       // 'name'
$path->depth();               // 3

// Aggregate
$path = RelationPath::parse('orders.sum.total');
$path->isAggregate();         // true
$path->getTerminal();         // AggregateSegment('sum', 'total')

// Pivot
$path = RelationPath::parse('roles.pivot.assigned_at');
$path->isPivot();             // true

// Morph
$path = RelationPath::parse('commentable.title');
// → [MorphSegment('commentable'), ColumnSegment('title')]

// Reconstruct
$path->toString();            // 'author.country.name'
```

### Relation Graph

`RelationGraphBuilder` builds a graph from multiple RelationPaths, merging shared segments and tracking which columns/aggregates are needed per relation node. Used by the QueryPlanner to determine optimal JOINs vs eager loads.

```php
use NyonCode\WireCore\Core\Relations\RelationGraphBuilder;

$builder = new RelationGraphBuilder();
$builder->addPath(RelationPath::parse('author.name'));
$builder->addPath(RelationPath::parse('author.country.name'));
$builder->addPath(RelationPath::parse('tags.name'));

$graph = $builder->build();
// Graph has nodes: author → country, tags
// Each node tracks: columns needed, aggregate functions, pivot access
```

---

## Query Planning

The `QueryPlanner` analyzes columns, filters, sorts, and search configuration to produce an immutable `QueryPlan`. It does **not** generate SQL.

### QueryPlanner

```php
use NyonCode\WireCore\Core\Query\QueryPlanner;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Query\SortDefinition;

$planner = new QueryPlanner($metadataRegistry, $joinRegistry);

$plan = $planner->plan(
    modelClass: User::class,
    columns: $dataComponents,           // DataComponent[]
    filters: [                          // FilterDefinition[]
        new FilterDefinition(
            column: 'role',
            operator: '=',
            value: 'admin',
        ),
    ],
    sorts: [                            // SortDefinition[]
        new SortDefinition(
            column: 'created_at',
            direction: 'desc',
        ),
    ],
    search: 'john',                     // global search term
    scopes: ['active'],                 // model scopes
    withSoftDeletes: false,
);
```

### QueryPlan (immutable DTO)

```php
use NyonCode\WireCore\Core\Query\QueryPlan;

// QueryPlan is readonly
$plan->joins;           // JoinClause[]       — required table joins
$plan->eagerLoads;      // string[]           — relations to eager load
$plan->aggregates;      // AggregateClause[]  — withCount/withSum/...
$plan->filters;         // FilterClause[]     — WHERE conditions
$plan->searchClauses;   // SearchClause[]     — search conditions
$plan->sortClauses;     // SortClause[]       — ORDER BY
$plan->selectedColumns; // string[]           — SELECT columns
$plan->scopes;          // string[]           — model scopes
$plan->relationGraph;   // RelationGraph      — full relation tree
$plan->withSoftDeletes; // bool               — include soft-deleted

// Introspection
$plan->hasJoins();
$plan->hasSearch();
$plan->hasSorting();
$plan->hasFilters();
$plan->hasAggregates();
$plan->hasEagerLoads();
$plan->hasScopes();
$plan->isEmpty();

// Immutable modification — returns new QueryPlan
$plan2 = $plan->withJoins($additionalJoins);
$plan3 = $plan->withEagerLoads(['comments']);
$plan4 = $plan->withAggregates($additionalAggregates);
```

### Clause DTOs

| Class | Properties |
|-------|------------|
| `JoinClause` | `table`, `first`, `operator`, `second`, `type` (inner/left) |
| `FilterClause` | `column`, `operator`, `value`, `boolean` (and/or), `isRelation` |
| `SearchClause` | `columns` (string[]), `term`, `strategy` |
| `SortClause` | `column`, `direction`, `isRelation`, `qualifiedColumn` |
| `AggregateClause` | `relation`, `function` (count/sum/avg/min/max), `column` |
| `FilterDefinition` | Input DTO: `column`, `operator`, `value` |
| `SortDefinition` | Input DTO: `column`, `direction` |

### JoinRegistry + AliasGenerator

```php
use NyonCode\WireCore\Core\Query\JoinRegistry;
use NyonCode\WireCore\Core\Query\AliasGenerator;

$joinRegistry = new JoinRegistry();

// Prevents duplicate joins and generates unique table aliases
$alias = $joinRegistry->register('posts', 'users.id', 'posts.user_id');
// → 'posts' (first time) or 'posts_1' (if already joined)

$joinRegistry->isRegistered('posts'); // true
$joinRegistry->reset();               // clear for next query
```

---

## Query Execution

The `QueryExecutor` takes a `QueryPlan` and applies it to an Eloquent Builder via a pipeline of 8 pipes.

### QueryExecutor

```php
use NyonCode\WireCore\Core\Query\QueryExecutor;

$executor = new QueryExecutor();
$builder = $executor->execute($baseQuery, $plan);
// → Builder ready for ->get(), ->paginate(), ->simplePaginate(), ->cursorPaginate()
```

### Pipe Pipeline (execution order)

| # | Pipe | Responsibility |
|---|------|----------------|
| 1 | `ApplyScopes` | Apply named model scopes |
| 2 | `ApplySoftDeletes` | `withTrashed()` if needed |
| 3 | `ApplyRelations` | Add JOINs from plan |
| 4 | `ApplySearch` | Global search (DB-aware strategy) |
| 5 | `ApplyFilters` | WHERE conditions |
| 6 | `ApplyAggregates` | `withCount()`, `withSum()`, etc. |
| 7 | `ApplySorting` | ORDER BY |
| 8 | `ApplyEagerLoads` | Eager load relations |

Each pipe implements `QueryPipe`:

```php
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;

interface QueryPipe
{
    public function handle(Builder $query, QueryPlan $plan, Closure $next): Builder;
}
```

### Custom Query Pipes

Write a custom pipe:

```php
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;

class TenantScopePipe implements QueryPipe
{
    public function handle(Builder $query, QueryPlan $plan, Closure $next): Builder
    {
        $query->where('tenant_id', auth()->user()->tenant_id);
        return $next($query);
    }
}
```

Register via plugin system (see [Plugin Development](plugins.md)):

```php
$manager->addQueryPipe('tenant-scope', new TenantScopePipe());
```

---

## Search Strategies

Database-aware full-text search:

| Strategy | DB | Approach |
|----------|-----|---------|
| `MySqlSearchStrategy` | MySQL/MariaDB | `MATCH ... AGAINST` (fulltext) |
| `PostgresSearchStrategy` | PostgreSQL | `to_tsvector` / `ts_query` |
| `SqliteSearchStrategy` | SQLite | `LIKE '%term%'` fallback |

Strategy selection is automatic via `DriverDetector`:

```php
use NyonCode\WireCore\Core\Support\DriverDetector;

$driver = DriverDetector::fromBuilder($query);  // 'mysql', 'pgsql', 'sqlite'

DriverDetector::isMysql($driver);    // true/false
DriverDetector::isPostgres($driver);
DriverDetector::isSqlite($driver);
```

The `SearchStrategy` interface:

```php
interface SearchStrategy
{
    public function apply(Builder $query, array $columns, string $term): Builder;
}
```

---

## State Engine

Central state container replacing scattered Livewire properties.

### StateContainer

```php
use NyonCode\WireCore\Core\State\StateContainer;

$state = new StateContainer(['user' => ['name' => 'John', 'email' => 'john@example.com']]);

// Read
$state->get('user.name');             // 'John'
$state->get('user.phone', 'N/A');     // 'N/A' (default)
$state->has('user.email');             // true

// Write (auto-tracks dirty)
$state->set('user.email', 'new@example.com');

// Bulk operations
$state->merge(['user' => ['role' => 'admin']]);
$state->replace(['fresh' => 'state']);

// Remove
$state->forget('user.email');

// All state
$state->all();                        // full state array
```

### Dirty Tracking

```php
$tracker = $state->getDirtyTracker();

$state->set('user.name', 'Jane');

$tracker->isDirty('user.name');        // true
$tracker->getDirty();                  // ['user.name']
$tracker->getOriginal('user.name');    // 'John'

$tracker->reset();                     // clear dirty state
```

### StatePathResolver

Static utility for dot-notation array access:

```php
use NyonCode\WireCore\Core\State\StatePathResolver;

$array = ['user' => ['profile' => ['name' => 'John']]];

StatePathResolver::resolve($array, 'user.profile.name'); // 'John'
StatePathResolver::has($array, 'user.profile.name');      // true
StatePathResolver::set($array, 'user.profile.age', 25);   // modifies in-place
```

### StateHydrator & StateSerializer

- `StateHydrator` — fills a StateContainer from Eloquent model attributes
- `StateSerializer` — serializes/deserializes StateContainer for Livewire snapshots

---

## Hydration System

Converts between Eloquent models and flat state arrays.

### Model → State — there is no `Hydrator` class

There was one — `Core\Hydration\Hydrator`, the read-direction mirror of
`Dehydrator` — and this page documented it with a worked example, but **nothing
ever called it**: verified 2026-08-30 across
`packages/*/src`, `workbench/` and `tests/`, where only its own unit test
referenced it. `Dehydrator` is genuinely used by `SaveHandler::persist()`; its
mirror was built and never attached, so it was removed rather than left here
looking like the engine's behaviour.

The path that does run is the forms runtime's, in three named steps:

| Step | Owner | Does |
|---|---|---|
| 1 | `FormRuntime::fill()` → `Core\State\StateHydrator::hydrate($data, $definitions)` | casts each value to the type its component declares (`getStateType()`) |
| 2 | `FormRuntime::hydrateFields()` → `$component->hydrateState($value, $record)` | lets each field shape the cast value into its own state |
| 3 | `StateManager::fill()` → `EnumResolver::scalarDeep()` | collapses enum instances a model filled to their scalar backing |

Its write-direction counterpart is `SaveHandler::dehydrateFields()` →
`$field->dehydrateState()` → `persist()` → `Dehydrator` (ADR 0021). Note the
asymmetry that matters: the per-field conversion runs **after** validation on the
write side, because validation rules describe user input rather than the
persisted type.

### Dehydrator (State → Model)

```php
use NyonCode\WireCore\Core\Hydration\Dehydrator;

$dehydrator = new Dehydrator($valueTransformer, $castResolver);
$dehydrator->dehydrate($state, $model);
// Applies mutations to $model attributes
```

### Support Classes

| Class | Description |
|-------|-------------|
| `ValueTransformer` | Converts values between PHP types and storage formats |
| `CastResolver` | Resolves Eloquent cast definitions to transformation logic |
| `MutationPipeline` | Chains multiple transformations (e.g., trim → cast → encrypt). **No callers yet** — built ahead of [`v2-deferred-items.md`](../plans/v2-deferred-items.md) §3.2, which runs it inside `dehydrate()` and wraps `mutateDataBeforeSave()` as a before-hook. Kept deliberately |

---

## Validation System

Reusable validation pipeline across forms, tables, and inline editing.

### ValidationPipeline

```php
use NyonCode\WireCore\Core\Validation\ValidationPipeline;

$pipeline = new ValidationPipeline();

// Validate raw data
$result = $pipeline->validate(
    data: ['name' => '', 'email' => 'invalid'],
    rules: ['name' => 'required', 'email' => 'email'],
    messages: ['name.required' => 'Name is mandatory'],
    attributes: ['name' => 'Full Name'],
);

// Validate a Validatable component
$result = $pipeline->validateComponent($component, $data);

// Validate multiple components at once
$result = $pipeline->validateMany($components, $data);
```

### ValidationResult (immutable)

```php
use NyonCode\WireCore\Core\Validation\ValidationResult;

$result->passed();          // bool
$result->failed();          // bool
$result->errors();          // ['name' => ['Name is mandatory'], ...]
$result->hasError('name');  // true
$result->getError('name');  // ['Name is mandatory']
$result->validatedData();   // data that passed validation

// Factories
$ok = ValidationResult::success(['name' => 'John']);
$fail = ValidationResult::failure(['name' => ['Required']]);

// Merge results
$combined = $result1->merge($result2);
```

### Validatable Contract

Components can implement this interface to participate in validation:

```php
use NyonCode\WireCore\Core\Validation\Contracts\Validatable;

interface Validatable
{
    public function getValidationRules(): array;
    public function getValidationMessages(): array;
    public function getValidationAttributes(): array;
}
```

---

## Action Pipeline

Pipeline-based action execution with stages.

### ActionContext (mutable container)

```php
use NyonCode\WireCore\Core\Actions\ActionContext;

$context = new ActionContext(
    record: $model,              // single record (null for bulk)
    records: $collection,        // collection (null for single)
    formData: ['name' => 'John'],
    meta: ['source' => 'table'],
    tableId: 'users',
);

$context->isBulk();              // true if records collection set
$context->getRecord();           // ?Model
$context->getRecords();          // Collection
$context->getFormData();         // array
$context->get('source');         // 'table' (from meta)
$context->set('processed', true);
```

### ActionResult (immutable)

```php
use NyonCode\WireCore\Core\Actions\ActionResult;

// Factories
$ok     = ActionResult::success('Record saved', ['id' => 1]);
$fail   = ActionResult::failure('Validation failed');
$redir  = ActionResult::redirect('/users', 'Created');
$halted = ActionResult::halt();

// Introspection
$ok->isSuccess();        // true
$ok->shouldRedirect();   // false
$ok->shouldHalt();       // false
$redir->shouldRedirect(); // true
```

### ActionPipeline

```php
use NyonCode\WireCore\Core\Actions\ActionPipeline;

// The action callback itself runs at the pipeline's terminal; the default
// stages only wrap it. Construct with no args to get the default stages.
$pipeline = new ActionPipeline;

$result = $pipeline->execute($context, function (ActionContext $ctx) {
    // core action logic
    $ctx->getRecord()->update($ctx->getFormData());
    return ActionResult::success('Saved');
});
```

### Stages

The action callback passed to `execute()` runs at the **terminal** (innermost)
of the pipeline. The default stages wrap around it — `BeforeCallbacksStage` runs
*before* the action (and can halt ahead of it), while the remaining stages
post-process the returned `ActionResult` as it bubbles back out:

| Stage | Responsibility |
|-------|----------------|
| `BeforeCallbacksStage` | Execute `before()` hooks; halt before the action if one returns false |
| `AfterCallbacksStage` | Execute `after()` hooks with the result (an `after()` may still halt) |
| `NotificationStage` | Lift a result notification into the context for delivery |
| `RedirectStage` | Lift a result redirect into the context |

> There is no separate `ActionExecutionStage`: the action already runs at the
> terminal, so a dedicated execution stage would run it twice. Passing custom
> stages to the constructor replaces the defaults but the action still runs at
> the terminal.

### ActionRegistry

```php
use NyonCode\WireCore\Core\Actions\ActionRegistry;

$registry = new ActionRegistry();
$registry->register('export', $exportAction);
$registry->get('export');     // ?Action
$registry->has('export');     // true
$registry->all();             // ['export' => Action]
```

---

## Events

Wire dispatches standard Laravel events at key lifecycle points.

| Event | Properties | When |
|-------|------------|------|
| `ActionExecuting` | `tableId`, `actionName`, `recordIds` | Before action runs |
| `ActionExecuted` | `tableId`, `actionName`, `recordIds`, `success` | After action completes |
| `CellUpdating` | `tableId`, `column`, `recordId`, `oldValue`, `newValue` | Before inline edit |
| `CellUpdated` | `tableId`, `column`, `recordId`, `oldValue`, `newValue` | After inline edit |
| `TableFiltering` | `tableId`, `filters` | Before filters apply |
| `TableFiltered` | `tableId`, `filters`, `resultsCount` | After filters apply |
| `TableSearching` | `tableId`, `term` | Before search |
| `TableSearched` | `tableId`, `term`, `resultsCount` | After search |
| `TableRefreshed` | `tableId` | After table refresh |

### Listening

```php
use NyonCode\WireCore\Core\Events\ActionExecuted;
use NyonCode\WireCore\Core\Events\CellUpdated;

// In EventServiceProvider or anywhere
Event::listen(ActionExecuted::class, function (ActionExecuted $event) {
    Log::info("Action {$event->actionName} on table {$event->tableId}", [
        'records' => $event->recordIds,
        'success' => $event->success,
    ]);
});

Event::listen(CellUpdated::class, function (CellUpdated $event) {
    AuditLog::create([
        'table' => $event->tableId,
        'column' => $event->column,
        'record_id' => $event->recordId,
        'old_value' => $event->oldValue,
        'new_value' => $event->newValue,
    ]);
});
```

All events are `readonly` value objects — immutable and safe to serialize.

---

## DataComponent

Shared base class for data-aware UI components. Extended by both `Column` (wire-table) and `Field` (wire-forms).

```php
use NyonCode\WireCore\Core\Components\DataComponent;

$component = DataComponent::make('author.name');

$component->getName();           // 'author.name'
$component->getLabel();          // 'Author name' (auto-generated)
$component->getRelationPath();   // RelationPath instance
$component->hasRelation();       // true
$component->getRelationName();   // 'author'
$component->getColumnName();     // 'name'
$component->isSqlCompatible();   // depends on capabilities
```

### Specialized DataComponents

| Class | Use Case |
|-------|----------|
| `TextComponent` | Text values (string, numeric) |
| `SelectComponent` | Option-based values |
| `BooleanComponent` | True/false values |
| `DateComponent` | Date/time values |
| `RelationComponent` | Relation-backed values |

---

## Support Utilities

### Deprecation

```php
use NyonCode\WireCore\Core\Support\Deprecation;

// Trigger warnings (deduplicated)
Deprecation::method('polling', 'poll', '2.0');
Deprecation::classRenamed('OldClass', 'NewClass', '2.0');
Deprecation::property('Table', 'pollInterval', 'poll()', '2.0');
Deprecation::warn('Custom deprecation message');

// For tests
Deprecation::disable();
Deprecation::enable();
Deprecation::reset();
```

### DriverDetector

```php
use NyonCode\WireCore\Core\Support\DriverDetector;

$driver = DriverDetector::fromBuilder($query);      // 'mysql'
$driver = DriverDetector::fromConnection($conn);     // 'pgsql'

DriverDetector::isMysql($driver);    // true
DriverDetector::isPostgres($driver); // false
DriverDetector::isSqlite($driver);   // false
```

Constants: `MYSQL = 'mysql'`, `POSTGRES = 'pgsql'`, `SQLITE = 'sqlite'`, `SQLSERVER = 'sqlsrv'`.

### SqlSafety

Prevents SQL injection edge cases by validating identifiers:

```php
use NyonCode\WireCore\Core\Support\SqlSafety;

SqlSafety::assertValidIdentifier('users');           // ok
SqlSafety::assertValidIdentifier('users; DROP');     // throws RuntimeException

SqlSafety::isValidIdentifier('users.name');          // true
SqlSafety::isValidIdentifier('1invalid');            // false

SqlSafety::assertValidDirection('asc');              // ok
SqlSafety::assertValidDirection('DESC');             // ok
SqlSafety::assertValidDirection('sideways');         // throws

SqlSafety::assertValidOperator('=');                 // ok
SqlSafety::assertValidOperator('LIKE');              // ok

SqlSafety::assertValidQualifiedColumn('users.name'); // ok
```

Pattern: `/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/`

Also validates against a list of SQL reserved words.
