---
order: 21
nav: false
---

# Relation Paths & Dot Notation

Column names support deep dot-notation for relations, aggregates, pivots, and morphs. The Core Relation AST parser automatically determines JOINs, eager loads, and subqueries.

## Simple Relation

```php
TextColumn::make('author.name')       // belongsTo or hasOne
TextColumn::make('category.title')
```

## Nested Relations

```php
TextColumn::make('author.country.name')        // 3 levels deep
TextColumn::make('order.customer.company.name') // 4 levels deep
```

## Aggregates

```php
TextColumn::make('orders.count')               // withCount
TextColumn::make('items.sum.amount')           // withSum
TextColumn::make('ratings.avg.score')          // withAvg
TextColumn::make('bids.min.amount')            // withMin
TextColumn::make('bids.max.amount')            // withMax
```

## Pivot Data

```php
TextColumn::make('tags.pivot.sort_order')
TextColumn::make('roles.pivot.assigned_at')->dateTime()
```

## Morph Relations

```php
TextColumn::make('commentable.title')          // polymorphic
```

## Sorting & Filtering a Singular Relation

A singular relation — `belongsTo`, `hasOne`, or `hasOneThrough` — is resolved with
a `LEFT JOIN`, so both sorting and filtering happen in SQL against the joined
table: no `whereHas` subquery, no in-memory filtering. Nested singular chains join
segment by segment (`company.country.name` → two chained joins), and a
`hasOneThrough` expands to two joins itself (base → intermediate → far).

```php
// Sorting: mark the relation column sortable.
TextColumn::make('company.name')->sortable();

// Filtering: SelectFilter supports the dot path directly in its name.
SelectFilter::make('company.name')
    ->options(['Acme' => 'Acme', 'Globex' => 'Globex']);

// Equivalent, if you'd rather the filter's state key stay flat ('company')
// while still targeting the relation column:
SelectFilter::make('company')
    ->column('company.name')
    ->options(['Acme' => 'Acme', 'Globex' => 'Globex']);
```

Both compile to the same join:

```sql
select "users".*
from "users"
left join "companies" as "users_company" on "users"."company_id" = "users_company"."id"
where "users_company"."name" = ?          -- filter
order by "users_company"."name" asc       -- sort
```

Only singular relations (`belongsTo`, `hasOne`, `hasOneThrough`) are joined this
way. To-many relations — `hasMany`, `belongsToMany`, `hasManyThrough`, `morphMany`
— and morph targets are eager-loaded for display and are **not** sortable/filterable
through the join (a join would multiply parent rows).

### Scopes & relation constraints (incl. soft deletes)

The joined side matches what Eloquent's own relation query returns. A relation
that carries any constraint — the model's **global scopes** (`SoftDeletes`,
tenancy, a published/active flag, anything from `addGlobalScope()`) **or**
constraints declared on the relation method itself
(`belongsTo(...)->where('active', true)`) — is joined as a scoped subquery:

```sql
left join (
  select * from "companies"
  where "companies"."deleted_at" is null       -- SoftDeletes global scope
    and "companies"."active" = ?               -- ->where(...) on the relation
) as "users_company" on "users"."company_id" = "users_company"."id"
```

The `LEFT JOIN` stays a `LEFT JOIN`: a parent whose related row is scoped away
still appears, with the related value treated as absent (sorts/filters as `NULL`).
For a `hasOneThrough`, both the intermediate and far models are scoped. A relation
with no scopes or constraints keeps a plain direct-table join.

> Limits: relation-method constraints are honoured for `belongsTo`/`hasOne` but
> not for a `hasOneThrough` (only its models' global scopes apply). A constraint
> must be self-contained — one that correlates to the parent row
> (`whereColumn('companies.x', 'users.y')`) can't be expressed as a subquery.
> `morphOne` is not join-scoped (it is eager-loaded for display).

## How It Works

1. `RelationPath::parse('author.country.name')` produces `[RelationSegment('author'), RelationSegment('country'), ColumnSegment('name')]`
2. `QueryPlanner` builds a `RelationGraph` determining optimal access strategy
3. Singular `belongsTo` / `hasOne` / `hasOneThrough` relations → LEFT JOIN (enables sorting **and filtering**; `hasOneThrough` uses two joins via its intermediate table)
4. HasMany/belongsToMany/hasManyThrough/morphMany → eager load (display only)
5. Aggregates → `withCount()` / `withSum()` subqueries
6. Pivot → intermediate table JOIN
