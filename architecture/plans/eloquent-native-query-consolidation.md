# Eloquent-Native Query Consolidation

Status: ALL steps done. Filter → whereHas, display → with(), sort keeps JOIN
(benchmark), dead scaffolding removed, aggregate filter → whereHas count (NOT
HAVING — proven cross-engine broken).
Owner: table/core query layer
Supported Laravel range: L10–L13 (floor = L10, `illuminate/database ^10|^11|^12|^13`, PHP ^8.2)

## Goal

Stop maintaining a parallel join/metadata engine. Delegate relation querying to
Eloquent's native APIs, using only what exists in **Laravel 10** (so it works
unchanged through L13). The dotted-path contract (`RelationPath`) and the
plan/pipe/plugin seam stay; only the *execution strategy* changes.

Guiding principle from the maintainer: **don't rebuild the wheel — fully use
Eloquent.**

## Why

- The join builder re-derives relation keys/scopes/constraints that Eloquent's
  `Relation` objects already own (`RelationMetadata::fromRelation`,
  `QueryPlanner::registerJoinForRelation`, `JoinScope`, `ApplyRelations::joinSub`).
- The replication is provably incomplete (documented gaps: through-method
  constraints, correlated `whereColumn`, `morphOne`).
- It couples to Eloquent internals across versions — e.g. the L10-vs-L11
  `HasOneThrough`/`HasManyThrough` inheritance special-case in
  `RelationMetadata.php` and the CI fix "Laravel 10 HasOneThrough classification".
- To-many relation filters are silently dropped today (`registerRelationJoins`
  returns null for non-joinable relations → no clause emitted).

## The four paths

### 1. FILTER → `whereHas` / `whereRelation` / `whereHasMorph` / `wherePivot`
Native, no join, no `JoinScope`. Eloquent supplies keys + global scopes + method
constraints. Nested dot paths supported natively by `whereHas`. **No perf
regression** (correlated EXISTS, no row multiplication). Fixes the to-many drop.

### 2. SORT → **keep the LEFT JOIN** (benchmark decision, step 3)
Original hypothesis was to move sort to correlated-subquery ordering. The
benchmark (`packages/table/tests/Benchmarks/RelationSortBenchmarkTest.php`, 50k
users / 2k companies, fresh stats) killed that for the cross-engine default:

| engine | subquery vs JOIN (median) |
|---|---|
| MariaDB/MySQL | 0.75–0.87× — subquery slightly faster, no temp table |
| **PostgreSQL 16** | **~2.0× slower** — correlated `SubPlan` evaluated per row |

Postgres does not flatten a scalar correlated subquery in `ORDER BY`; it runs it
once per scanned row (seq scan cost ~415k vs a hash left join ~600). The JOIN is
competitive on MySQL and clearly better on Postgres, so **sort stays on the JOIN**
(with its scoped-subquery machinery). No sort rewrite. This is exactly the
"measure before committing" gate: it prevented a 2× Postgres regression.

### 3. DISPLAY → `with()` eager loading
Already the mechanism. Once sort/filter stop joining, unify all relation display
onto `with()` from `RelationPath`; shrink/retire `RelationGraph*`.

### 4. AGGREGATE → `withCount`/`withSum`/`withMax`/`withExists`
Already native (`AggregateSubqueries`). Aggregate filters add
`withCount(...)->having(...)`.

## Deletion ledger (REVISED after step 3)

The benchmark keeps the JOIN for sort, so the join engine cannot be deleted — its
surface just shrinks to **sort + relation search** (filter and display no longer
use it). Revised targets:

Cannot delete (still needed for sort/search): `JoinRegistry`, `AliasGenerator`,
`JoinClause`, `JoinScope`, `Pipes/ApplyRelations`, `planSort`,
`registerRelationJoins`, `RelationMetadata`'s key/scope extraction (so the L10/L11
through branch stays too).
Still deletable: `Strategies/MorphRelationStrategy` for the *filter* path (filter
is now `whereHas`); the filter join branch in `QueryPlanner` (already gone).
Shrink: `QueryPlan::selectedColumns` (dead — never applied; safe to drop);
`FilterClause` `tableAlias`/`isRelation` no longer set by the filter path.
Keep untouched: `RelationPath` + segments (dotted contract), plugin hooks,
`getLastPlan()`/`debugQueryPlan()`, DB-driver search strategies, sub-row filters,
custom callbacks.

Net: far smaller deletion than first estimated — the value landed in steps 1–2
(filter + display) and in the benchmark evidence, not in ripping out the joiner.

## Sequence

1. ✅ **Filter** → native `whereHas`. Non-morph single + nested; morph-safe guard;
   to-many now works.
2. ✅ **Display** → `with()` eager load, decoupled from join select (N+1 fixed).
3. ✅ **Sort** → benchmarked; **keep the JOIN** (Postgres 2× regression on
   subquery). Harness committed for CI re-runs.
4. ✅ **Cleanup (narrowed)** → dropped dead `QueryPlan::selectedColumns` (never
   applied; was the root-cause confusion behind the step-2 N+1) and the dead
   `&$joins` params threaded through `plan`/`planSimpleColumn`/`planRelationColumn`/
   `planFilter`/`planSort`. Join engine stays for sort.
5. ✅ **Aggregate filter** → `whereHas` count comparison, **NOT** `HAVING`
   (empirically cross-engine broken; see outcome below).

After each step: `composer test:table` + `test:core`, then `Integration`, then
the MySQL/Postgres matrix for dotted sort/filter, then the coverage gate.

## Risks

- **Sort perf** — measured in step 3; resolved by keeping the JOIN (the
  Eloquent-native subquery would have regressed Postgres 2×).
- **BC** — `filterAs*`, `->relationship()`, `debugQueryPlan()`, plugin hooks must
  stay. `QueryPlan` is internal but `getLastPlan()` is observed → keep its shape.
- **Search across relations** — `orWhereHas` inside one scoped `where(fn)` so
  `orWhere` cannot escape the base authorization scope.

## Step 1 design (filter)

- `QueryPlanner::planFilter`: base filter unchanged; relation filter emits a
  relation `FilterClause` (terminal column + dot `relationPath`, no join, no
  alias). Stop calling `registerRelationJoins` here. Morph paths still add the
  display eager-load for parity.
- `ApplyFilters`: branch on `isRelation` → apply the operator logic inside
  `whereHas`/`orWhereHas($relationPath, ...)`. Guard resolves the relation chain
  from the live model and skips any path containing `MorphTo` (preserves prior
  non-support instead of throwing). Operator logic (=, IN, BETWEEN, IS NULL, …)
  is shared between base and relation via one `applyComparison()` helper.
- Column/sort relation joins are untouched in this step (handled in steps 2–3),
  so mixed column+filter+sort tests keep one deduplicated join from the
  column/sort side.

### Step 1 outcome (done)

- `QueryPlanner::planFilter` no longer calls `registerRelationJoins`; relation
  filters emit an alias-less relation `FilterClause`. `ApplyFilters` applies them
  via `whereHas`/`orWhereHas` with a `MorphTo`-safe guard resolved from the live
  model. `applyComparison()` now shared by base + relation filters.
- **Bug fixed as a side effect:** to-many relation filters (hasMany /
  belongsToMany) were silently dropped by the old join path; whereHas keeps them.
- Several existing tests asserted `left join` for relation *filtering* but were
  passing off a display column's join — rewritten to assert the native `EXISTS`
  (whereHas) mechanism and to isolate the filter from any display/sort join.
- hasOneThrough filtering now goes through a nested `EXISTS` (whereHas handles
  through relations) instead of two chained joins.
- Verified: full `core` (1568) + `table` (1233) + `Integration` (20) suites,
  Pint, PHPStan all green. Changed production lines covered (ApplyFilters 100%,
  planFilter morph branch covered). **Still to validate on the MySQL/Postgres
  matrix (CI `database-tests.yml`)** — `EXISTS` is standard SQL, lower risk than
  the alias/join path it replaces.
- Deferred to later steps: deleting the now-unused join engine
  (`JoinRegistry`/`AliasGenerator`/`JoinScope`/`ApplyRelations`), `MorphTo`
  filtering via `whereHasMorph`, and pivot-column filtering (neither was
  supported before, so no regression).

### Step 2 outcome (done)

- Discovery: `QueryPlan::selectedColumns` is **never applied** to the builder
  (only exposed as debug), and `ApplyRelations` forces `select(base.*)`, so a
  joined belongsTo/hasOne display column's value was resolved by `data_get()` →
  an **N+1 lazy load**. Joinable relations were not eager-loaded.
- `QueryPlanner::planRelationColumn` now eager-loads **every** non-morph relation
  display column (fixing the N+1) and registers a JOIN only when the column is
  *searched*. Sorting still registers its own join in `planSort` (step 3). The
  dead `selectedColumns[]` line for relations was removed.
- Rewrote the column-join white-box planner tests (display columns assert eager
  loads, not joins) and `RelationJoinScopesTest`'s through-filter test (now a
  nested `whereHas` `EXISTS`; the intermediate soft-delete scope is honoured
  natively — result unchanged, proving parity with the old `joinSub` scoping).
- New behavioural test asserts `relationLoaded('company')` (eager-loaded, no
  join) for a display column.
- Verified: full `core` (1574) + `table` (1234) + `Integration` (20), Pint,
  PHPStan green; changed lines covered (ApplyFilters 100%, planRelationColumn
  branches covered incl. searchable-to-many fallback).

### Step 3 outcome (measured → no code change)

- Built `packages/table/tests/Benchmarks/RelationSortBenchmarkTest.php` — a
  JOIN-vs-correlated-subquery relation-sort harness. Guarded by `BENCH=1` so the
  normal suite skips it; connection is env-driven (reuses the CI matrix
  `TestCase`), runs `ANALYZE`, times median/min over 15 runs at shallow + deep
  offset, and dumps `EXPLAIN`. `tests/Pest.php` now binds `tests/Benchmarks` to
  the table `TestCase` so the harness has a booted app.
- Ran it on local MariaDB 12.2 and PostgreSQL 16 (50k users / 2k companies):
  subquery is 0.75–0.87× on MariaDB but **~2.0× slower on Postgres** (correlated
  `SubPlan` per row). Decision: **keep the JOIN for sort.** No production sort
  change.
- To re-run (e.g. against MySQL 8 in CI):
  `BENCH=1 DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=wire_bench
  DB_USERNAME=… vendor/bin/pest …/RelationSortBenchmarkTest.php`. Local MySQL
  PDO needs `DB_HOST=localhost` (socket) when only a socket grant exists.

### Step 4 outcome (done)

- Removed dead `QueryPlan::selectedColumns` end to end: the field, its planner
  population (`planSimpleColumn`'s `{base}.{col}` push), the three `withX`
  copies, and the `debugQueryPlan` `selected_columns` line. It was never applied
  to a builder (`ApplyRelations` forces `select(base.*)`), and its misleading
  presence was what masked the belongsTo display N+1 found in step 2.
- Removed the dead `&$joins` reference params carried through `plan()`,
  `planSimpleColumn`, `planRelationColumn`, `planFilter`, `planSort` (the real
  joins come from `JoinRegistry`), clearing the long-standing unused-symbol noise.
- Kept (not dead): `FilterClause::isRelation`/`tableAlias` (used by ApplyFilters /
  base filters), `SortClause` alias fields (sort join), and `planFilter`'s morph
  eager-load (display parity for a morph relation referenced only by a filter).
- Verified: `core` (1574) + `table` (1234), Pint, PHPStan green; QueryPlan 100%
  covered; join methods still covered by the table service + join-scope tests.

### Step 5 outcome (done)

- **Empirical cross-engine test killed HAVING.** On PostgreSQL 16: `HAVING
  orders_count > 2` (alias) → "column does not exist"; `HAVING (subquery) > 2`
  (no GROUP BY) → "must appear in GROUP BY clause". Only `WHERE (subquery) > 2`
  works everywhere. So aggregate filtering is a WHERE over the aggregate
  subquery, never HAVING.
- For `count`/`exists` that WHERE is exactly Eloquent's native
  `whereHas($rel, null, $operator, $count)` / `whereDoesntHave` — keys and the
  relation's scopes handled automatically.
- `QueryPlanner::planFilter` detects an aggregate `RelationPath`
  (`orders->count()`) and emits a `FilterClause` carrying
  `isAggregate`/`aggregateRelation`/`aggregateFunction`. `ApplyFilters` applies
  count via the whereHas count comparison and exists via whereHas/whereDoesntHave
  (MorphTo-safe guard reused). `sum`/`avg`/`min`/`max` have no native primitive
  and are skipped rather than mis-applied (documented; a correlated aggregate
  subquery is the future path — still not HAVING).
- Verified on SQLite **and** local MariaDB 12.2 **and** PostgreSQL 16 (count +
  exists return identical correct rows, no HAVING in the SQL). Full `core` (1579)
  + `table` (1236), Pint, PHPStan green; ApplyFilters + FilterClause 100% covered.

## Net result

Filter and display are Eloquent-native (whereHas / with()), the sort JOIN is kept
on benchmark evidence, aggregate filtering is cross-engine-correct via whereHas,
and the dead plan scaffolding is gone. The join engine remains — deliberately —
as the measured-best sort strategy. Value landed in correctness (to-many filters,
N+1, cross-engine aggregate) and evidence, not in a wholesale rewrite.
