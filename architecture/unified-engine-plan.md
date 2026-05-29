# Unified Data UI Engine — Implementation Plan

## Target Directory Structure

```
packages/core/src/
├── Foundation/          (exists — keep)
├── Actions/             (exists — extend)
├── Modals/              (exists — generalize)
├── Notifications/       (exists — keep)
├── Core/                (NEW — shared infrastructure)
│   ├── Metadata/
│   │   ├── ModelMetadata.php
│   │   ├── RelationMetadata.php
│   │   ├── ColumnMetadata.php
│   │   ├── AccessorMetadata.php
│   │   ├── CapabilityMetadata.php
│   │   ├── ValidationMetadata.php
│   │   ├── MetadataRegistry.php
│   │   └── MetadataCache.php
│   ├── Relations/
│   │   ├── RelationPath.php
│   │   ├── RelationSegment.php
│   │   ├── ColumnSegment.php
│   │   ├── AggregateSegment.php
│   │   ├── PivotSegment.php
│   │   ├── MorphSegment.php
│   │   ├── RelationGraphBuilder.php
│   │   └── RelationGraph.php
│   ├── Query/
│   │   ├── QueryPlanner.php
│   │   ├── QueryPlan.php
│   │   ├── QueryExecutor.php
│   │   ├── JoinRegistry.php
│   │   ├── AliasGenerator.php
│   │   ├── Contracts/
│   │   │   ├── QueryPipe.php
│   │   │   └── SearchStrategy.php
│   │   ├── Pipes/
│   │   │   ├── ApplySearch.php
│   │   │   ├── ApplyFilters.php
│   │   │   ├── ApplySorting.php
│   │   │   ├── ApplyRelations.php
│   │   │   ├── ApplyScopes.php
│   │   │   ├── ApplySoftDeletes.php
│   │   │   ├── ApplyAggregates.php
│   │   │   └── ApplyEagerLoads.php
│   │   └── Strategies/
│   │       ├── MorphRelationStrategy.php
│   │       ├── MySqlSearchStrategy.php
│   │       ├── PostgresSearchStrategy.php
│   │       └── SqliteSearchStrategy.php
│   ├── Capabilities/
│   │   ├── Capability.php            (enum)
│   │   ├── CapabilityResolver.php
│   │   └── CapabilitySet.php
│   ├── State/
│   │   ├── StateContainer.php
│   │   ├── StateHydrator.php
│   │   ├── StateSerializer.php
│   │   ├── DirtyStateTracker.php
│   │   └── StatePathResolver.php
│   ├── Hydration/
│   │   ├── Hydrator.php
│   │   ├── Dehydrator.php
│   │   ├── ValueTransformer.php
│   │   ├── CastResolver.php
│   │   └── MutationPipeline.php
│   ├── Validation/
│   │   ├── ValidationPipeline.php
│   │   ├── ValidationResult.php
│   │   └── Contracts/Validatable.php
│   ├── Actions/
│   │   ├── ActionPipeline.php
│   │   ├── ActionContext.php
│   │   ├── ActionResult.php
│   │   ├── ActionRegistry.php
│   │   └── Stages/
│   │       ├── BeforeCallbacksStage.php
│   │       ├── ActionExecutionStage.php
│   │       ├── AfterCallbacksStage.php
│   │       ├── NotificationStage.php
│   │       └── RedirectStage.php
│   ├── Components/
│   │   ├── DataComponent.php         (base)
│   │   ├── TextComponent.php
│   │   ├── SelectComponent.php
│   │   ├── BooleanComponent.php
│   │   ├── DateComponent.php
│   │   └── RelationComponent.php
│   ├── Events/
│   │   ├── TableSearching.php
│   │   ├── TableSearched.php
│   │   ├── TableFiltering.php
│   │   ├── TableFiltered.php
│   │   ├── ActionExecuting.php
│   │   ├── ActionExecuted.php
│   │   ├── CellUpdating.php
│   │   ├── CellUpdated.php
│   │   └── TableRefreshed.php
│   └── Support/
│       ├── SqlSafety.php
│       └── DriverDetector.php
```

---

## Phase 0 — Foundation

No UI changes. Pure infrastructure.

### 0.1 Metadata System

**Goal:** Immutable, serializable, cacheable metadata objects.

Classes:
- `Core\Metadata\ModelMetadata` — model class, table name, primary key, casts, fillable, relations list
- `Core\Metadata\RelationMetadata` — relation type, related model, foreign keys, morph type
- `Core\Metadata\ColumnMetadata` — name, db column?, accessor?, sql expression?, type
- `Core\Metadata\AccessorMetadata` — accessor name, sql compatible?, expression, runtime only?
- `Core\Metadata\MetadataRegistry` — central registry, resolve metadata per model
- `Core\Metadata\MetadataCache` — cache wrapper (Redis/file/memory), invalidation by schema hash

**Key decisions:**
- Metadata is NEVER parsed from PHP source code (no `file()` + regex)
- AccessorMetadata has explicit `sqlExpression` property — either user sets it or accessor is runtime-only
- MetadataCache uses `cache()->rememberForever()` with keys: `wire_meta:{model}:{hash}`

**Tests:** Unit tests for every metadata object + registry + cache.

### 0.2 Capability System

**Goal:** Shared capabilities across tables/forms/infolists.

Classes:
- `Core\Capabilities\Capability` — enum: searchable, sortable, filterable, editable, dehydrated, hydrated, runtimeOnly, requiresHydration, aggregateable, sqlExpression
- `Core\Capabilities\CapabilityResolver` — resolve capabilities from metadata + column config
- `Core\Capabilities\CapabilitySet` — immutable set of capabilities per component

**Tests:** Unit tests for resolver and set.

### 0.3 Relation AST Parser

**Goal:** Replace `explode('.', $path)` with structured AST.

Classes:
- `Core\Relations\RelationPath` — parsed path, factory `RelationPath::parse('posts.comments.author.email')`
- `Core\Relations\RelationSegment` — segment = relation name + metadata
- `Core\Relations\ColumnSegment` — terminal segment = column/attribute
- `Core\Relations\AggregateSegment` — ->count(), ->sum('total')
- `Core\Relations\PivotSegment` — pivot.created_at
- `Core\Relations\MorphSegment` — morph-specific segment

**Tests:** Extensive tests for various path formats, edge cases.

### 0.4 Relation Graph Builder

**Goal:** Graph of relations for optimal query planning.

Classes:
- `Core\Relations\RelationGraphBuilder` — builder from collection of RelationPaths
- `Core\Relations\RelationGraph` — immutable graph, merge duplicate paths

**Tests:** Unit tests for graph building, merge, traversal.

### 0.5 Join Registry

**Goal:** Central join registry — prevent duplicates, deterministic aliases.

Classes:
- `Core\Query\JoinRegistry` — join registration, deduplication, alias management
- `Core\Query\AliasGenerator` — deterministic aliases (`users_company`, `users_manager_company`)

**Rules:**
- Default LEFT JOIN
- Inner join only explicit: `->joinType('inner')`
- Aliases: `{parent_table}_{relation_name}` for unambiguity

**Tests:** Tests for deduplication, aliasing, nested relations.

### 0.6 Shared DataComponent Base

**Goal:** Shared base class for Columns and Fields.

Classes:
- `Core\Components\DataComponent` — name, label, capabilities, metadata, state resolution
- `Core\Components\TextComponent` — text-specific shared behavior
- `Core\Components\SelectComponent` — options, multiple
- `Core\Components\BooleanComponent` — true/false
- `Core\Components\DateComponent` — format, timezone
- `Core\Components\RelationComponent` — relation path

**Important:** Column extends TextComponent (display), TextInput extends TextComponent (input) — share metadata and capabilities, differ in rendering.

**Tests:** Unit tests for each component.

---

## Phase 1 — Query Planning (No SQL Execution)

### 1.1 QueryPlan DTO

Immutable object representing a query plan:

```php
QueryPlan {
    joins: array,
    eagerLoads: array,
    aggregates: array,
    filters: array,
    searchClauses: array,
    sortClauses: array,
    selectedColumns: array,
    relationGraphs: array,
}
```

### 1.2 QueryPlanner

`Core\Query\QueryPlanner` — analyze(columns, filters, sort, search, scopes) → QueryPlan

Uses MetadataRegistry, CapabilityResolver, RelationGraphBuilder, JoinRegistry.

**Key:** Planner does NOT generate SQL. Planner only analyzes and plans. Result is immutable QueryPlan.

### 1.3 Aggregate Planning

withCount, withSum, withAvg, subquery planning. Decision: subquery vs join vs EXISTS.

### 1.4 Morph Planning

`Core\Query\Strategies\MorphRelationStrategy` — dedicated morph handling. Eager load planning for morph relations. NOT a standard join.

**Tests:** Unit tests for planner with various column/filter/sort combinations.

---

## Phase 2 — Query Execution

### 2.1 Query Pipeline

Replace monolithic query building with pipeline architecture:

- `Core\Query\Contracts\QueryPipe` — interface: `handle(Builder, QueryPlan, Closure)`
- `Core\Query\Pipes\ApplySearch` — search clauses from QueryPlan
- `Core\Query\Pipes\ApplyFilters` — filter clauses
- `Core\Query\Pipes\ApplySorting` — sort clauses
- `Core\Query\Pipes\ApplyRelations` — joins from JoinRegistry
- `Core\Query\Pipes\ApplyScopes` — model scopes
- `Core\Query\Pipes\ApplySoftDeletes` — soft delete handling
- `Core\Query\Pipes\ApplyAggregates` — aggregate subqueries
- `Core\Query\Pipes\ApplyEagerLoads` — eager loading

### 2.2 QueryExecutor

`Core\Query\QueryExecutor` — execute(Builder, QueryPlan) → Builder (modified). Uses pipeline pipes. Immutable context.

### 2.3 Database-Aware Strategies

- `MySqlSearchStrategy` — LIKE, FULLTEXT
- `PostgresSearchStrategy` — ILIKE, tsvector
- `SqliteSearchStrategy` — LIKE (case-insensitive default)
- `DriverDetector` — resolve DB driver

### 2.4 SQL Safety

`Core\Support\SqlSafety` — validate identifiers, prevent injection edge cases. Validation against metadata registries.

**Tests:** Integration tests with SQLite, unit tests for every pipe.

---

## Phase 3 — Shared Core Runtime

### 3.1 State Engine

- `StateContainer` — central state container (replaces 30+ Livewire properties)
- `StateHydrator` — hydrate state from Livewire request
- `StateSerializer` — serialize/deserialize for Livewire
- `DirtyStateTracker` — track changes for optimal updates
- `StatePathResolver` — dot notation state paths

### 3.2 Hydration System

- `Hydrator` — model → state
- `Dehydrator` — state → model
- `ValueTransformer` — value transformations (casts, mutations)
- `CastResolver` — resolve Laravel casts
- `MutationPipeline` — before/after mutations

### 3.3 Validation System

- `ValidationPipeline` — reusable validation across forms/tables/inline
- `ValidationResult` — immutable result object
- `Contracts\Validatable` — interface

### 3.4 Action Pipeline (refactor)

- `ActionPipeline` — pipeline for action execution
- `ActionContext` — context object (record, bulk records, form data)
- `ActionResult` — result object (success, redirect, notification)
- `ActionRegistry` — action registry
- `Stages\*` — 5 pipeline stages

### 3.5 Event System

- `Core\Events\*` — 9 event classes
- Integration with Laravel event dispatcher
- Support for telemetry, audit, plugins

**Tests:** Unit tests for every subsystem, WITHOUT Livewire lifecycle.

---

## Phase 4 — UI Layers (Refactor Existing Classes)

### 4.1 Table Refactor

- `Table.php` — simplify, delegate to QueryPlanner + QueryExecutor
- `WithTable.php` — thin trait, orchestration only, remove reflection/query building
- `Column.php` — refactor to extend DataComponent, remove query logic
- Preserve public API: `->searchable()`, `->sortable()`, `->filterable()`

### 4.2 Form Refactor

- `Field.php` — refactor to extend DataComponent
- `StateManager` → delegate to shared StateContainer
- `SaveHandler` → delegate to shared Hydrator/Dehydrator
- `FormValidationResolver` → delegate to shared ValidationPipeline

### 4.3 Shared Component Wiring

- TextColumn extends TextComponent (display mode)
- TextInput extends TextComponent (input mode)
- Shared metadata, capabilities, validation

### 4.4 Inline Editing Refactor

- Use shared ValidationPipeline
- Use shared StateContainer
- Use shared Hydrator

### 4.5 Debug Tooling

- `$table->debugQueryPlan()` — expose QueryPlan, SQL, relation graph, aliases
- Dev-only, disabled in production

**Tests:** Integration tests, backward compatibility tests.

---

## Phase 5 — Extensions & Polish

### 5.1 Plugin Architecture

Plugin interface, registration, hooks. Preparation for: exports, charts, grouping, AI filters.

### 5.2 Performance

Query caching, cursor pagination support, chunking support, lazy eager loading.

### 5.3 Backward Compatibility Layer

Deprecation warnings for old API patterns. Compatibility shims where needed. Migration guide.

### 5.4 Documentation

Architecture docs, API reference, migration guide from current version, ADR for every key decision.

---

## Phase Dependencies

```
Phase 0.1 (Metadata) ─────┐
Phase 0.2 (Capabilities) ──┤
Phase 0.3 (Relation AST) ──┼── Phase 1 (Query Planning)
Phase 0.4 (Relation Graph) ┤        │
Phase 0.5 (Join Registry) ─┘        │
Phase 0.6 (DataComponent) ──────────┼── Phase 4 (UI Layers)
                                    │
                            Phase 2 (Query Execution)
                                    │
                            Phase 3 (Shared Runtime) ── Phase 4 (UI Layers)
                                                              │
                                                        Phase 5 (Extensions)
```

## Implementation Rules

1. **Every phase MUST end with green CI** (tests + PHPStan)
2. **No breaking changes in public API** — deprecation first
3. **Every new class has unit tests** — min 80% coverage
4. **Immutable DTOs** — QueryPlan, MetadataRegistry, CapabilitySet, ActionResult
5. **Strict types everywhere**
6. **No PHP source code parsing** — explicit SQL expressions instead of reflection
7. **LEFT JOIN default** — inner join only explicit
8. **Database-agnostic** — strategies for MySQL/Postgres/SQLite
