---
order: 33
nav: false
---

# Column-Level Filters

In addition to dedicated filter components, any column can have an inline filter directly in its header cell. See [Columns — Column-Level Filtering](../columns/editing.md#column-level-filtering).

```php
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Admin', 'editor' => 'Editor']) // pick several → whereIn

TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)

TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()
```

Column filters use the `$columnFilters` Livewire property (separate from `$tableFilters`).
