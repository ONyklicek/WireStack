# ADR 0019: DataSource Contract (read-path unlock)

## Status

PROPOSED

Gate for **V2.0** (`architecture/plans/v2-master-plan.md`). Builds on ADR 0013
(Unified Data UI Engine) and serves ADR 0017 invariant C (headless table engine)
and G (scope/tenancy as first-class).

## Context

The entire table **read-path is locked to `Illuminate\Database\Eloquent\Builder`**.
Verified seams (2026-07-06):

- `Table::getQuery(): Builder<Model>` — `packages/table/src/Table.php:233`; the
  `query(Builder)` setter (`Table.php:256`) and `modifyQueryCallback` also speak
  `Builder`.
- `QueryExecutor::execute(Builder $builder, QueryPlan $plan, ?string $searchTerm): Builder`
  — `packages/core/src/Core/Query/QueryExecutor.php:46`.
- `QueryPipe::handle(Builder $builder, QueryPlan $plan, Closure $next): Builder`
  — `packages/core/src/Core/Query/Contracts/QueryPipe.php`; all 8 default pipes
  (`ApplyScopes`, `ApplySoftDeletes`, `ApplyRelations`, `ApplySearch`,
  `ApplyFilters`, `ApplySorting`, `ApplyAggregates`, `ApplyEagerLoads`) are
  `Builder`-typed.
- `TableQueryService::buildQuery()` returns a modified `Builder` for the caller to
  `->get()` / `->paginate()` (`packages/table/src/Services/TableQueryService.php`).
- Record resolution for actions is `$table->getQuery()->find($recordKey)` →
  `Model` (`WithTable.php:2106`, `WithTable.php:2935`); actions consume
  `Model $record`.

This is the ceiling for clean DDD/CQRS. A bounded context cannot feed a table
from a read model, a DTO projection, an in-memory `Collection`, an external API,
or a CQRS query handler — the whole pipeline demands a live Eloquent query
builder.

`QueryPlan` (built by `QueryPlanner`, consumed by `QueryExecutor`) is already an
**immutable intermediate representation** of what the table wants: columns,
filters, sorts, search, aggregates, relations. That IR is the natural boundary —
today it is only ever interpreted by an Eloquent pipeline.

### Correction: the write-path is NOT unlocked (2026-07-14)

An earlier revision of this Context claimed:

> The **write-path is already unlocked** (`Form::using(Closure)` is a functional
> command seam); the read-path is the remaining strategic strop.

**That is true only of the form save.** Measured 2026-07-14 — `DB::transaction`
in `packages/*/src` yields four independent, unrelated transactional write paths:

| path | file |
|---|---|
| form save | `packages/forms/src/Forms/Runtime/SaveHandler.php` (+ `RelationshipSaveHandler.php`) |
| inline cell edit | `packages/table/src/Concerns/WithTable.php` |
| panel entry | `packages/core/src/Panels/Concerns/WithEditablePanel.php` |
| reorder | `packages/sortable/src/Concerns/WithSortable.php` |

`Form::using()` covers exactly one of them. Optimistic locking is re-implemented
independently in at least `Panels/*` and `WithTable` (both `updated_at` +
`lockForUpdate`), and import is a fifth path that
`architecture/plans/v2.0-datasource-implementation.md:123` explicitly leaves out
("import zůstává mimo (write-side)").

The distinction that matters: `Form::using()` is an **escape hatch** (override the
save with your own closure), not a **layer** (a single entry point writes pass
through). So the read-path is *not* "the remaining" strop — the write-path has a
symmetrical gap that this ADR does not address and should not be read as having
settled.

See `architecture/plans/v1/modularity-audit.md` §A. Whether that gap becomes a
counterpart ADR (write-path) or folds into ADR 0020's `Resource` is an open
decision, out of scope here.

### Re-verification of the IR premise (2026-07-14)

The claim above that `QueryPlan` is the natural boundary was re-measured against
the working tree and **holds**:

- `QueryPlan.php` — **0** references to `Builder` / `Model` / `Eloquent`; its only
  import is `Core\Relations\RelationGraph`, itself also **0** → the IR is clean
  *transitively*.
- `QueryPlanner.php` — 5 Eloquent references; `QueryExecutor.php` — 13. The
  Eloquent-ness is concentrated in the executor, i.e. exactly where this ADR puts
  the `EloquentDataSource` boundary.
- Filters **describe themselves into the IR** rather than mutating a builder:
  `TextFilter` / `NumberRangeFilter` / `Filter` build `FilterDefinition(column,
  operator, value, relationPath, sqlExpression)`, which carries no Eloquent type.

Practical consequence: a non-Eloquent source is an **adapter**, not a rewrite of
`packages/table`. What is *not* portable is the public surface rather than the
engine — 15 hard `Builder` typehints across 19 files in `packages/table/src`
(`Table::query()`, `Exporter`, summary), the user-facing `Filter::query(Closure)`
whose callback receives a live `Builder`, and the raw-SQL `sqlExpression` field on
`FilterDefinition` / `SortClause`. Those are backwards-compatibility problems, not
architectural ones — and "Capability degradation for non-Eloquent sources" below
is already the mechanism for declaring them unsupported per source.

## Decision

Introduce a **`DataSource` contract** that owns resolution of a `QueryPlan` (plus
table state) into results. `QueryPlan` becomes the shared IR between table state
and any data source. The Eloquent pipeline (`QueryExecutor` + `QueryPipe`s)
becomes the **internal implementation of the default `EloquentDataSource`**, not
part of the public read contract.

Introduce a **`RecordContract`** so actions, render data, and selection stop
requiring a concrete `Model`. `Model` satisfies it via a thin adapter, so
existing code is unchanged.

### Contract shape (indicative, not final)

```php
namespace NyonCode\WireCore\Core\Data;

interface DataSource
{
    /**
     * Resolve a page of records for the given plan + paging window.
     *
     * @return DataResult  // items (RecordContract[]), total, page meta
     */
    public function paginate(QueryPlan $plan, PagingRequest $paging): DataResult;

    /** Total matching count for the plan (no paging). */
    public function count(QueryPlan $plan): int;

    /** Resolve a single record by primary key (actions, inline edit). */
    public function resolveRecord(int|string $key): ?RecordContract;

    /** What this source can honor from a plan (see capability degradation). */
    public function capabilities(): DataSourceCapabilities;
}

interface RecordContract
{
    public function getKey(): int|string;
    public function get(string $path): mixed;      // dot-path attribute/relation access
    public function toArray(): array;
}
```

- **Default: `EloquentDataSource`.** Wraps the existing `Table::getQuery()`
  builder and delegates to `QueryExecutor`/`QueryPipe`s exactly as today. No
  behavior change on the Eloquent path.
- **`Model` → `RecordContract`** via `EloquentRecord` adapter (or `Model`
  implements the interface directly if BC allows). `resolveRecord()` keeps the
  `->find($key)` semantics.
- `Table::query()` gains an overload / sibling `Table::dataSource(DataSource)`.
  When only a `Builder`/model is given, an `EloquentDataSource` is constructed
  implicitly — **the common case is untouched**.

### Capability degradation for non-Eloquent sources

Not every source can honor every `QueryPlan` aspect (an API source may not
support arbitrary joins; a `Collection` source has no SQL aggregates). A source
declares `DataSourceCapabilities` (searchable / sortable / filterable /
aggregatable / relation-joinable / paginable). The engine:

- honors the plan aspects the source supports,
- for unsupported aspects either (a) falls back to an in-memory strategy when
  safe and small, or (b) throws a clear `UnsupportedQueryAspect` — **never
  silently returns wrong results.**

This mirrors the existing Capability system (ADR 0013) rather than inventing a
parallel one.

### What stays out of the contract

- The pipe pipeline stays an Eloquent implementation detail. Non-Eloquent
  sources are free to interpret `QueryPlan` however they want (or reject parts).
- `QueryPlan` / `QueryPlanner` remain the shared IR — **unchanged**. This ADR
  does not re-open plan building.

## Boundary invariants

1. **`QueryPlan` is the only thing crossing the seam** from table state to a
   source. State/columns/filters never reach into a `Builder` directly.
2. **Scope & tenancy attach to the source, not the pipeline.** A tenant scope
   (ADR 0017 invariant G, V2.4) is applied by/around the `DataSource`, so it
   holds for every source type — not just Eloquent (`table.querying` hook at
   priority `-100` composes with this).
3. **Actions depend on `RecordContract`, not `Model`.** Concrete-model
   convenience stays available on `EloquentDataSource` but is not required by the
   engine.
4. **`EloquentDataSource` is the compatibility anchor.** Any code path that does
   not opt into a custom source must behave byte-for-byte as V1.

## Migration (phased, deprecation-first)

- **V2.0.a** — introduce contracts + `EloquentDataSource` + `EloquentRecord`;
  route `TableQueryService` through `DataSource` internally while keeping
  `Table::getQuery(): Builder` working (Eloquent source exposes the builder).
  Purely additive; default behavior identical.
- **V2.0.b** — migrate record resolution (`WithTable.php:2106/2935`) and action
  render call-sites to `RecordContract`; `Model` adapter keeps callers working.
- **V2.0.c** — public `Table::dataSource()` API + docs + a reference non-Eloquent
  source (e.g. `ArrayDataSource`/`CollectionDataSource`) proving the seam.
- `Builder`-typed public signatures are **kept and `@deprecated`** through 2.x;
  removal in 3.0.

## Consequences

### Positive
- Read models / DTO projections / API sources / in-memory collections can feed a
  table — clean CQRS read-side.
- Tenancy/scope enforcement gets one canonical attachment point across all
  sources (unblocks V2.4).
- Table engine moves toward headless (ADR 0017 invariant C): source is an
  injectable adapter, not a hardcoded Eloquent assumption.
- Testability: table logic exercisable over a trivial in-memory source without a
  DB.

### Negative
- Another abstraction layer over a currently direct path; more contracts to learn.
- Non-Eloquent sources force explicit capability handling (previously implicit).
- `RecordContract` indirection touches many action/render call-sites (blast
  radius overlaps V2.1 monolith split — sequence accordingly).

### Risks
- **Aggregates/relations on non-Eloquent sources** are genuinely hard; capability
  degradation must fail loud, not silent.
- **Performance:** the Eloquent path must not regress from the extra indirection
  (benchmark before/after; `EloquentDataSource` should be a thin pass-through).
- **BC surface:** `Builder` leaks through several public signatures; each needs a
  deprecation shim, not a rename.

## Open questions

1. Does `Model` implement `RecordContract` directly, or only via an
   `EloquentRecord` wrapper? (Directly = fewer allocations; wrapper = cleaner
   boundary and no framework-class edit.)
2. Where do **metadata/capabilities** come from for a non-Eloquent source that
   has no DB schema to introspect? (Explicit declaration API vs. inference from
   sample rows — ties into ADR 0013 metadata registry and v2-deferred #2.)
3. Is pagination `LengthAware` only, or do we also model cursor/simple paging in
   `PagingRequest` from day one?
4. Do exports/imports consume `DataSource` too, or keep their own path in V2.0
   (they already stream from the filtered query)?

## References

- `architecture/plans/v2-master-plan.md` — V2.0 gate.
- `architecture/decisions/0013-unified-data-ui-engine.md` — QueryPlan/Capabilities IR.
- `architecture/decisions/0017-erp-crm-application-architecture.md` — invariants C, G.
- `architecture/plans/ddd-enterprise-roadmap.md` — V2-1/V2-2 origin of this item.
- Seams: `QueryExecutor.php:46`, `QueryPipe.php`, `Table.php:233`,
  `TableQueryService.php`, `WithTable.php:2106/2935`.
