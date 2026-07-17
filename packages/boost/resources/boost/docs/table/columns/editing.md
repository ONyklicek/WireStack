---
order: 28
nav: false
---

# Editing & Column-Level Filters

## Column-Level Filtering

Beyond the dedicated Filter classes, any column can have an inline filter in its header.

```php
// Select filter in column header (pick one)
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

// Multi-select filter (pick several → matches any, whereIn). Renders the same
// searchable combobox as the wire-forms Select — search is on by default.
BadgeColumn::make('role')
    ->filterAsMultiSelect([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ], 'Any role')
    ->filterSearchable(false)               // opt out of the search box for a short list

// Boolean filter
BooleanColumn::make('is_active')
    ->filterable()
    ->filterAsBoolean()

// Date range filter
TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()

// Number range filter
TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)        // min, max, optional step

// Custom filter logic
TextColumn::make('name')
    ->filterable()
    ->filterUsing(fn (Builder $query, mixed $value) => $query->where('name', 'like', "%{$value}%"))
    ->filterDebounce(500)

// Filter with operator
TextColumn::make('age')
    ->filterable()
    ->filterOperator('>=')
```

### Column-Level Filter API

A column header filter is a placement of a canonical `Filter` — the `filterAs*()`
helpers are thin factories over `TextFilter` / `SelectFilter` / `DateFilter` /
`NumberRangeFilter` / `TernaryFilter`, or pass a ready one with `->filter()`. See
[Column-Level Filters](../filters/column-level.md) for the shared engine, chips
and query-string persistence.

```php
->filterable(bool $filterable = true, string $type = 'text', array|string $options = [])
->isFilterable(): bool
->filter(Filter $filter)                                                   // attach a ready-made canonical filter
->getFilter(): ?Filter
->filterAsSelect(array|string $options, ?string $placeholder = null)       // single value; searchable combobox
->filterAsMultiSelect(array|string $options, ?string $placeholder = null)  // several values (whereIn); searchable combobox
->filterSearchable(bool $condition = true)                                 // toggle the in-panel search (on by default)
->filterAsDate(?string $minDate = null, ?string $maxDate = null)
->filterAsDateRange(?string $minDate = null, ?string $maxDate = null)
->filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null)
->filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null)
->filterOperator(string $operator)     // '=', '!=', '>', '<', '>=', '<=', 'like' (default, partial match), 'starts_with', 'ends_with'
->filterDebounce(int $ms)
->filterPlaceholder(?string $placeholder)
->filterUsing(Closure $fn)             // fn(Builder $query, mixed $value)
```

---

## Inline Editing

Columns can also use the generic `editable()` API (in addition to dedicated TextInputColumn/SelectColumn/ToggleColumn):

```php
TextColumn::make('name')
    ->editable()                              // type defaults to 'text'
    ->editableRules(fn ($record) => ['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })

TextColumn::make('category')
    // editable(enabled, type, options) — 'text' | 'select' | 'toggle'
    ->editable(true, 'select', ['a' => 'Category A', 'b' => 'Category B'])
    ->editableRules(fn ($record) => ['required', 'in:a,b'])
```

The `options` argument of both `editable(type: 'select', …)` and `filterable()` /
`filterAsSelect()` accepts a PHP enum class as well — it expands to `value => label` exactly
like the dedicated `SelectColumn`/`SelectFilter`. See [Enum Options](select.md#enum-options).

### How inline saves work

Saving a cell (`updateTableCell`) deliberately **does not re-render the table** — a DOM morph
would reset the Alpine state of every editable cell. Instead each cell updates its own appearance
**optimistically** and reconciles with the server, via one shared Alpine component
(`wireEditableCell`): text inputs, selects and toggles all use it, so they behave consistently.

- **Optimistic + rollback.** The cell shows the new value immediately, then calls the server; if
  the save fails (validation, permission, error) it rolls back to the last server-confirmed value
  and surfaces the message.
- **Optimistic locking.** Each edit carries the row's version (`updated_at`). If the row changed
  since the page loaded, the save is rejected as a conflict: the cell loads the current value and
  shows the conflict message **inline on the cell itself** (a red state on the text/select/toggle,
  no toast or `NotificationManager` setup required) — so two people (or two quick edits that bump
  the row) can't silently clobber each other. Polling refreshes each cell's version on the next
  cycle. Opt in to *also* raise a (more prominent) toast for conflicts with
  `Table::notifyEditConflicts()` — this one needs the notification system wired up (a toast
  container); the inline message works without it.
- **Server-side authorization.** The client `disabled()` state is only cosmetic — a per-record
  `disabled()` cell (and any column permission) is enforced again on the server in
  `updateTableCell`, so a forged request can't write to a locked cell.
