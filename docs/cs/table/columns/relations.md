---
order: 21
nav: false
---

# Cesty relací a tečková notace

Názvy sloupců podporují hlubokou tečkovou notaci pro relace, agregáty, pivoty a morphy. Core parser Relation AST automaticky určuje JOINy, eager loady a podotázky.

## Jednoduchá relace

```php
TextColumn::make('author.name')       // belongsTo nebo hasOne
TextColumn::make('category.title')
```

## Vnořené relace

```php
TextColumn::make('author.country.name')        // 3 úrovně hluboko
TextColumn::make('order.customer.company.name') // 4 úrovně hluboko
```

## Agregáty

```php
TextColumn::make('orders.count')               // withCount
TextColumn::make('items.sum.amount')           // withSum
TextColumn::make('ratings.avg.score')          // withAvg
TextColumn::make('bids.min.amount')            // withMin
TextColumn::make('bids.max.amount')            // withMax
```

## Pivot data

```php
TextColumn::make('tags.pivot.sort_order')
TextColumn::make('roles.pivot.assigned_at')->dateTime()
```

## Morph relace

```php
TextColumn::make('commentable.title')          // polymorfní
```

## Jak to funguje

1. `RelationPath::parse('author.country.name')` vyprodukuje `[RelationSegment('author'), RelationSegment('country'), ColumnSegment('name')]`
2. `QueryPlanner` postaví `RelationGraph` určující optimální strategii přístupu
3. Jednoduché belongsTo relace → LEFT JOIN (umožňuje řazení/filtrování)
4. HasMany/morphMany → eager load (jen zobrazení)
5. Agregáty → `withCount()` / `withSum()` podotázky
6. Pivot → JOIN mezitabulky
```
