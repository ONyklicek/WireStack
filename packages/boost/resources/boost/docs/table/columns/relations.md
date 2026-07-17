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

## How It Works

1. `RelationPath::parse('author.country.name')` produces `[RelationSegment('author'), RelationSegment('country'), ColumnSegment('name')]`
2. `QueryPlanner` builds a `RelationGraph` determining optimal access strategy
3. Simple belongsTo relations → LEFT JOIN (enables sort/filter)
4. HasMany/morphMany → eager load (display only)
5. Aggregates → `withCount()` / `withSum()` subqueries
6. Pivot → intermediate table JOIN
