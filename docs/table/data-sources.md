---
order: 70
---

# Data Sources

A table reads its rows through a **data source**. By default that source wraps an
Eloquent builder and you never see it — `->model()` and `->query()` work exactly
as they always have. Declare one yourself when the rows are not in a database:
a read model, a DTO list, an API response.

## The mechanism

Reading a table is three questions, and the source answers all three: *which
rows match* (`count`), *this page of them* (`paginate`), and *the row behind
this key* (`resolveRecord`). What the table hands the source is a
`QueryPlan` — the search, filters, sorting and joins it resolved from state —
and a `PagingRequest` for the slice.

The plan is declarative. A filter is a column, an operator and a value, never a
closure, which is what makes a non-database source possible at all: it can read
the plan and answer it with `Collection` methods rather than SQL.

What a source cannot answer, it **declares it cannot** — and the engine then
raises instead of returning rows that quietly ignored half the query:

```php
public function capabilities(): CapabilitySet
{
    return new CapabilitySet(
        Capability::Filterable,
        Capability::Sortable,
        Capability::Paginable,
    );   // no SqlExpression, no Joinable — asking for those throws
}
```

That is the whole safety property. A table backed by an API that cannot sort
must say so, because a table that sorts by nothing and looks sorted is worse
than one that errors.

## A table over rows in memory

```php
use NyonCode\WireTable\Data\CollectionDataSource;

public function table(Table $table): Table
{
    return $table
        ->dataSource(new CollectionDataSource([                       // [tl! focus]
            ['id' => 1, 'name' => 'Ada',   'score' => 90],            // [tl! focus]
            ['id' => 2, 'name' => 'Grace', 'score' => 70],            // [tl! focus]
        ]))                                                           // [tl! focus]
        ->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('score')->sortable(),
        ]);
}
```

No `->model()`, no `->query()`. Search, filters, sorting and pagination all work;
the source answers them over the array.

## What a collection-backed table cannot do

`CollectionDataSource` declares four capabilities and refuses the rest. Each
refusal raises `UnsupportedQueryAspectException` with the aspect named:

| Asking for | Where it comes from | Result |
|------------|--------------------|--------|
| A raw SQL expression | `Column::sortUsing()` with SQL, `->sqlExpression()` | throws `[sql_expression]` |
| A relation path | `TextColumn::make('company.name')` | throws `[joinable]` |
| Subquery aggregates | `->sums()`, `->counts()` rollups | throws `[aggregateable]` |
| Cursor pagination | `->paginationMode('cursor')` | throws `[cursor paging]` |
| Change detection for polling | `->poll()` | returns `null`; polling compares rows instead |

None of these are limitations to work around silently. A table over an in-memory
list is a **restricted table**, and the restriction is visible the first time you
ask for something outside it.

## Writing your own source

Implement `NyonCode\WireCore\Core\Data\DataSource`. Every method receives the
plan, so the source decides how much of it to honour:

```php
use NyonCode\WireCore\Core\Data\DataSource;

final class ReportingApiSource implements DataSource
{
    public function paginate(QueryPlan $plan, PagingRequest $paging): LengthAwarePaginator|Paginator|CursorPaginator;
    public function get(QueryPlan $plan): Collection;
    public function chunk(QueryPlan $plan, int $size, callable $callback): void;   // [tl! focus]
    public function count(QueryPlan $plan): int;
    public function resolveRecord(int|string $key): ?RecordContract;
    public function resolveRecords(array $keys): Collection;
    public function capabilities(): CapabilitySet;
    public function changeToken(QueryPlan $plan): ?string;   // [tl! focus]
}
```

Two of those are worth explaining, because their shape is not obvious:

**`chunk()` exists separately from `get()`** so an export of a hundred thousand
rows stays bounded in memory. `get()` materialises; `chunk()` streams and stops
early when the callback returns `false`.

**`changeToken()` may return `null`**, and null is a real answer rather than a
failure: it means the source has no cheap way to know whether the data moved, so
polling compares rows itself instead of short-circuiting on a token.

## Records

The source hands rows back as `RecordContract` — `getKey()`, `get('dot.path')`,
`toArray()`, `unwrap()`. Two implementations ship: `EloquentRecord` wraps a
model, `ArrayRecord` wraps an array.

**Your own code still receives models.** The framework unwraps at the boundary,
so an action closure keeps its familiar signature:

```php
Action::make('archive')
    ->action(fn (Model $record) => $record->archive());
```

The consequence is worth stating plainly: over a source with no model to unwrap,
an action written that way is not available. That is the same degradation the
query side applies, one level up.

## What stays Eloquent-only

Two paths deliberately keep their builder rather than going through the contract,
and both are cases the contract cannot express:

- **Selection rollups** replay aggregate subqueries onto the keyed query.
- **The fill handle's writes** take a pessimistic row lock (`SELECT … FOR UPDATE`).

A custom source does not get either.

## See Also

- [Exports](exports.md) — streams through the source's `chunk()`
- [Summaries](summaries.md) — the aggregates a non-SQL source declines
- [Selection](selection.md) — bulk actions and `resolveRecords()`
