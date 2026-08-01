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
->filterAsDate(string|DateTimeInterface|null $minDate = null, string|DateTimeInterface|null $maxDate = null)
->filterAsDateRange(string|DateTimeInterface|null $minDate = null, string|DateTimeInterface|null $maxDate = null)
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

Saving a cell (`updateTableCell`) **re-renders the table**, and the cell protects its own state
while that happens. Everything derived from the written value — a summary, a rollup, a badge
computed from the same column, the row's position under the current sort — is stale the moment the
edit lands, and only a render can put it right.

The cell survives the morph because its root carries `wire:ignore.self`, so Livewire leaves its
attributes and its Alpine state alone. That is also why the value the server just rendered cannot
reach the cell through that root: it is delivered on a **sync node**, a small child element the
morph *does* update, which the cell watches. One shared Alpine component (`wireEditableCell`) does
all of this — text inputs, selects and toggles use it, so they behave consistently.

- **Optimistic + rollback.** The cell shows the new value immediately, then calls the server; if
  the save fails (validation, permission, error) it rolls back to the last server-confirmed value
  and surfaces the message.
- **Optimistic locking.** Each edit carries the row's version (`updated_at`, resolved through the
  model's own timestamp column, so `const UPDATED_AT` is honoured). If the row changed since the
  page loaded, the save is rejected as a conflict: the cell loads the current value and shows the
  conflict message **inline on the cell itself** (a red state on the text/select/toggle, no toast
  or `NotificationManager` setup required) — so two people (or two quick edits that bump the row)
  can't silently clobber each other. Any re-render — a poll tick, a modal write, another session's
  change arriving — refreshes each cell's value *and* its version through the sync node, so the
  next edit is compared against what is actually in the database. Opt in to *also* raise a (more
  prominent) toast for conflicts with `Table::notifyEditConflicts()` — this one needs the
  notification system wired up (a toast container); the inline message works without it.
- **Opting out of the render.** `Table::refreshAfterEdit(false)` goes back to answering an edit
  with no HTML at all. Worth it only for a table where the query behind a render is expensive and
  nothing on screen depends on the edited value: the cell still reconciles itself from the
  response, nothing around it does.
- **Server-side authorization.** The client `disabled()` state is only cosmetic — a per-record
  `disabled()` cell (and any column permission) is enforced again on the server in
  `updateTableCell`, so a forged request can't write to a locked cell.
