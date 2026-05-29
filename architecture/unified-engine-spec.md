# Unified Data UI Engine — Enterprise Architecture Refactor Specification

## Mission

Refactor the current WireTable architecture into a fully modular, metadata-driven, enterprise-grade UI data framework deeply integrated with Laravel Eloquent and Livewire.

This is NOT a simple trait refactor. This is a full architectural redesign.

The final architecture must support:

- Tables
- Forms
- Infolists
- Actions
- Modals
- Inline Editing
- Future UI systems

through a SHARED CORE FOUNDATION.

The framework should feel comparable to Filament internals, Laravel Nova internals, enterprise admin frameworks, and modern metadata-driven UI engines.

The final result must become:

- metadata-driven
- query-plan oriented
- SQL-first
- plugin-ready
- cache-aware
- contributor-friendly
- production-scalable
- future-proof

---

## Primary Architectural Goal

The framework MUST stop treating Tables, Forms, Actions, and Infolists as isolated systems.

Instead implement a **Unified Data UI Engine** where all UI layers share:

- metadata
- relations
- validation
- state
- actions
- hydration
- authorization
- events
- capabilities

Rendering layers remain separated. Business/data infrastructure becomes shared.

---

## Critical Design Principles

### 1. Eloquent Compatibility First

The framework MUST extend Laravel Eloquent behavior. Reuse Builder, Relations, eager loading, aggregates, scopes, casts, Attribute accessors, model metadata.

DO NOT create custom ORM, bypass Eloquent internals, replace Builder, or introduce custom query DSL.

Supported relations: HasOne, HasMany, BelongsTo, BelongsToMany, MorphTo, MorphMany, MorphToMany, HasOneThrough, HasManyThrough.

### 2. Unified Core Architecture

```
src/
├── Core/
│   ├── Metadata/
│   ├── Relations/
│   ├── Query/
│   ├── Planning/
│   ├── State/
│   ├── Validation/
│   ├── Actions/
│   ├── Authorization/
│   ├── Hydration/
│   ├── Events/
│   ├── Capabilities/
│   └── Support/
├── Tables/
├── Forms/
├── Infolists/
├── Widgets/
├── Modals/
├── Shared/
└── Contracts/
```

Core infrastructure must be reusable across ALL UI systems.

### 3. Thin Traits Only

Traits MUST become orchestration-only. Allowed: public API exposure, Livewire integration, delegating to services. MUST NOT contain: reflection parsing, query building, metadata analysis, validation engines, join logic, relation parsing, modal state management, action execution, SQL generation.

### 4. Shared Component Foundation

DO NOT treat Columns and Fields as completely separate systems. Implement shared component abstractions:

```
DataComponent
├── TextComponent
├── SelectComponent
├── BooleanComponent
├── DateComponent
└── RelationComponent
```

Specialized UI implementations (TextColumn, TextInput, TextEntry) must inherit shared metadata/capability behavior.

### 5. Capability System

Shared capability architecture working across tables, forms, infolists, inline editing, exports, realtime systems.

Capabilities: searchable, sortable, filterable, editable, dehydrated, hydrated, runtimeOnly, requiresHydration, aggregateable, sqlExpression.

Implement CapabilityResolver and CapabilityMetadata. The capability system must NOT be table-specific.

### 6. Modern Laravel Attribute Support

Support modern Laravel accessors. The engine MUST distinguish:

| Type | SQL Searchable | SQL Sortable | Runtime Only |
|------|---------------|-------------|-------------|
| Native column accessor | yes | yes | no |
| SQL expression accessor | yes | yes | no |
| Virtual/runtime accessor | no | no | yes |

DO NOT automatically guess SQL expressions in production.

### 7. Accessor SQL Expressions

Support explicit SQL-compatible accessors:

```php
Column::make('full_name')
    ->expression("CONCAT(first_name, ' ', last_name)")
```

Virtual runtime accessors without SQL support must NEVER become searchable/sortable automatically. STRICT MODE MUST THROW EXCEPTIONS.

### 8. Dot Notation Engine

Fully support Eloquent-style paths with searching, sorting, filtering, eager loading, aggregates, morphs, pivot columns, scopes — WITHOUT manual joins, alias management, SQL duplication:

```php
Column::make('company.address.country.name')
Column::make('posts.comments.author.email')
Column::make('roles.pivot.created_at')
```

### 9. Relation AST Parser (Never String-Only)

DO NOT implement relations using `explode('.', $path)`. Instead implement AST-like structures:

```
RelationPath
├── RelationSegment
├── ColumnSegment
├── AggregateSegment
├── PivotSegment
└── MorphSegment
```

### 10. Relation Graph Engine

Implement RelationGraphBuilder. Example: `company.address.country.name` becomes a traversable graph. This graph becomes the central reusable metadata structure.

### 11. Query Planning Layer

Joins MUST NOT be generated directly inside sorting/searching/filtering. Implement QueryPlanner → immutable QueryPlan objects. ONLY AFTER planning: QueryExecutor may generate SQL.

```
QueryPlan
├── joins
├── eagerLoads
├── aggregates
├── filters
├── searchClauses
├── sortClauses
├── selectedColumns
└── relationGraphs
```

### 12. Join Registry

Centralized JoinRegistry: prevent duplicate joins, deterministic aliases, collision prevention, join reuse. Multiple columns referencing same relation MUST produce ONE JOIN.

### 13. Left Join Default

All relation joins for sorting/searching/filtering default to LEFT JOIN. Inner joins opt-in only: `->joinType('inner')`.

### 14. Deterministic Aliasing

Nested relations MUST support deterministic aliases. Example:
- `users.company.name` → `companies AS users_company`
- `users.manager.company.name` → `companies AS users_manager_company`

### 15. Morph Relation Strategy

Dedicated MorphRelationStrategy. Must support morph map resolution, eager loading, searching, sorting, aggregates. DO NOT treat MorphTo like BelongsTo.

### 16. Aggregate Support

Support `Column::make('orders.total')->sum()` using subqueries, withCount, withSum, EXISTS, database-aware strategies.

### 17. Query Pipeline Architecture

Replace large query methods with pipelines. Pipes: ApplySearch, ApplyFilters, ApplySorting, ApplyRelations, ApplyScopes, ApplySoftDeletes, ApplyAggregates, ApplyEagerLoads. Requirements: immutable context, independently testable, middleware architecture, plugin support.

### 18. Shared State Engine

Reusable state infrastructure: StateContainer, StateHydrator, StateSerializer, DirtyStateTracker, StatePathResolver. Must support tables, forms, inline editing, actions, modals.

### 19. Shared Hydration System

Centralized hydration/dehydration: Hydrator, Dehydrator, ValueTransformer, CastResolver, MutationPipeline. Support forms, tables, exports, realtime updates, inline editing.

### 20. Shared Validation System

Reusable across forms, inline editing, modal forms, bulk editing. Implement ValidationMetadata, ValidationPipeline, ValidationResult.

### 21. Universal Action Engine

Actions work consistently across forms, tables, bulk, row, modal actions. Implement ActionPipeline, ActionContext, ActionResult, ActionRegistry. Pipeline stages: BeforeCallbacks, ActionExecution, AfterCallbacks, Notification, Redirect.

### 22. Shared Modal / Overlay Runtime

Reusable overlay infrastructure: ModalManager, OverlayManager, DialogManager, SlideOverManager. The modal system must NOT be table-specific.

### 23. SQL Safety

NEVER trust dynamic columns directly. All searchable/sortable/filterable columns must be validated against metadata registries.

### 24. Metadata System

Implement: ModelMetadata, RelationMetadata, ColumnMetadata, AccessorMetadata, CapabilityMetadata, ValidationMetadata. Metadata must be immutable, serializable, cacheable, reusable.

### 25. Metadata Cache

Cache layer with `cache()->rememberForever(...)`. Cache keys include model class, schema hash, app version hash, column hash. Support Redis, file cache, Octane, Swoole, Horizon workers.

### 26. Event System

Internal events: TableSearching, TableSearched, TableFiltering, TableFiltered, ActionExecuting, ActionExecuted, CellUpdating, CellUpdated, TableRefreshed. Goals: telemetry, analytics, audit logs, plugins, extension ecosystem.

### 27. Performance Requirements

Reuse joins, avoid N+1, support chunking, cursor pagination, lazy eager loading, query caching, indexes.

### 28. Database Driver Support

MySQL, MariaDB, PostgreSQL, SQLite, SQL Server. Database-aware: PostgreSQL ILIKE, FULLTEXT, JSON operators, COLLATE support.

### 29. Debugging Support

`$table->debugQueryPlan()` — expose generated SQL, relation graph, eager load graph, alias map, query plan visualization.

### 30. Testability Requirements

Every subsystem must support isolated testing without requiring full Livewire lifecycle.

### 31. Plugin Architecture

Support future plugins: exports, charts, grouping, realtime collaboration, audit history, AI filters, virtual scrolling, advanced filters, column presets.

### 32. Coding Standards

Strict types everywhere, immutable DTOs, constructor property promotion, typed collections, SOLID, PSR-12, Laravel conventions, Livewire lifecycle safety, minimal trait responsibility, no hidden runtime magic.

### 33. Backward Compatibility

Existing API must continue working: `->searchable()`, `->sortable()`, `->filterable()`, `->editable()`, `->action()`. Deprecate gradually. No breaking API changes without compatibility layers.

---

## Important Prohibitions

DO NOT:

- perform superficial trait splitting only
- use collection-side filtering
- parse accessor PHP bodies
- rebuild metadata every request
- generate joins ad-hoc
- tightly couple services to Livewire
- introduce hidden SQL magic
- use string-only relation parsing
