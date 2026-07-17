---
order: 32
nav: false
---

# Relationship & Sub-Row Filters

## Filtering by Relationships

Use `->query()` with `whereHas()` to filter by related model attributes:

```php
// BelongsTo — filter by related model
SelectFilter::make('category')
    ->options(Category::orderBy('name')->pluck('name', 'id')->toArray())
    ->query(fn (Builder $query, string $value) =>
        $query->whereHas('category', fn ($q) => $q->where('id', $value))
    )

// BelongsToMany
SelectFilter::make('tags')
    ->options(Tag::orderBy('name')->pluck('name', 'id')->toArray())
    ->multiple()
    ->query(fn (Builder $query, array $values) =>
        $query->whereHas('tags', fn ($q) => $q->whereIn('id', $values))
    )

// HasMany (existence) — use TernaryFilter
TernaryFilter::make('has_comments')
    ->label('Has Comments')
    ->query(fn (Builder $q, $value) => $value === '1'
        ? $q->has('comments')
        : $q->doesntHave('comments'))
```

---

## Filtering by Sub-Row Values

When the table has [sub-rows](../sub-rows.md), mark a filter with `subRows()` to
target the **child** records instead of parent columns. One call constrains all
three places at once:

- **parents** — reduced to those having at least one matching child (`whereHas`),
- **displayed sub-rows** — only matching children render in the expanded panel,
- **rollup aggregates** — `->sums()` / `->counts()` cells (over the same
  relation as `subRows()`) and their footer grand totals count only the
  matching children,
- **sub-row grand totals** — `query`-scoped summaries on sub-row columns total
  only the matching children in the main footer.

See [Sub-Rows — Building the Diagram](../sub-rows.md#building-the-diagram) for a
complete worked example of filters + rollups + totals.

```php
$table
    ->subRows('items')
    ->filters([
        // "Month/Year" — show only invoices with items billed that month,
        // and only those items inside each expanded invoice
        DateFilter::make('billed_at')->month()->subRows(),

        // Any filter type works — this matches a child column by equality
        SelectFilter::make('status')
            ->options(['open' => 'Open', 'closed' => 'Closed'])
            ->subRows(),
    ])
```

The filter's column names refer to the **child** model. A `->query()` callback
on a sub-row scoped filter receives the child query builder. When the table has
no sub-row relation configured, `subRows()` is ignored and the filter behaves
like a regular parent filter.

With multiple sub-row scoped filters active, a parent survives only when at
least one child matches **all** of them combined, so every surviving parent has
children to display.
