---
order: 28
summary: Editing a value in the cell it is shown in, and filtering a column from its own header.
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

**The editor comes from the column type**, not from a setting: use
[TextInputColumn](text-input.md), [SelectColumn](select.md),
[ToggleColumn](toggle.md) or [CheckboxColumn](checkbox.md). An ordinary column
renders no editor.

`editable()` is the switch that turns a dedicated column's editor on and off,
and the server-side gate for writing that column:

```php
TextInputColumn::make('name')
    ->editable(fn () => auth()->user()->isAdmin())   // false renders the plain value
    ->editableRules(fn ($record) => ['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })
```

Naming an editor type — `editable(true, 'select', […])` — throws: no view has
ever read one, so it would silently do nothing. Use `SelectColumn` instead.

The `options` argument of `filterable()` / `filterAsSelect()` accepts a PHP enum
class — it expands to `value => label` exactly like the dedicated
`SelectColumn`/`SelectFilter`. See [Enum Options](select.md#enum-options).

### Inline Editing API

```php
->editable(bool $editable = true): static
->isEditable(): bool
->authorizeInline(?string $ability): static
->getInlineEditAbility(): ?string
->canInlineEdit(): bool
->fillable(bool $condition = true): static
->isFillable(): bool
->editableRules(Closure $callback): static // fn (?Model $record): array
->getEditableRules(?Model $record): array
->editableUsing(Closure $callback): static // custom persistence callback
->getEditableCallback(): ?Closure
```

`authorizeInline()` names a Laravel Gate ability checked for inline edits.
`editable(false)` disables the editor and refuses writes for that column;
`fillable(false)` separately prevents the fill handle from writing it.
`editableUsing()` replaces the default attribute write with a custom persistence
callback. The editor itself still comes from a dedicated column type.

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
- **Optimistic locking.** Each edit carries the row's version. If the row changed since the cell
  read it, the save is rejected as a conflict: the cell loads the current value and shows the
  conflict message **inline on the cell itself** (a red state on the text/select/toggle, no toast
  or `NotificationManager` setup required) — so two people can't silently clobber each other. Any
  re-render — a poll tick, a modal write, another session's change arriving — refreshes each cell's
  value *and* its version through the sync node, so the next edit is compared against what is
  actually in the database. Opt in to *also* raise a (more prominent) toast for conflicts with
  `Table::notifyEditConflicts()` — this one needs the notification system wired up (a toast
  container); the inline message works without it. What the version is made of is
  [below](#exact-versions-with-haslockversion).
- **The cell is locked while its own write is out — and nothing else is.** From the commit until
  the server answers, a text input is `readonly` and carries `aria-busy`; a select, toggle or
  checkbox puts back a change made meanwhile. Locked, never `disabled`: a disabled control drops
  the focus, so after Enter the next keystroke and the next Tab went nowhere. The rest of the table
  stays editable — tab out of a saving cell straight into the next one and keep typing.
- **One record's writes go one after another, with the version the previous one produced.**
  Livewire sends one request per component at a time and holds a later call back, with the
  arguments it was made with. A second cell of the same row committed while the first was on its
  way therefore used to leave with the version from *before* the first write — and was refused as
  somebody else's edit. Each cell now waits for the record's previous write to settle and reads
  its version only when it leaves. Your own writes never conflict with each other; a write somebody
  else made in between is still a different version, and still refused.
- **Leaving the page does not lose an edit silently.** A write that has reached the server
  finishes there whatever the browser does (the cell endpoints call `ignore_user_abort()`).
  `wire:navigate` waits for the writes still on their way, commits text that was typed and never
  committed, and then carries on by itself — it asks only when a value cannot be saved (refused,
  invalid). A reload, a closed tab or an ordinary link cannot be held, so the browser's own prompt
  is raised while anything is unsaved or still being written; the typed text is committed on the
  way, so staying saves it.
- **Opting out of the render.** `Table::refreshAfterEdit(false)` goes back to answering an edit
  with no HTML at all. Worth it only for a table where the query behind a render is expensive and
  nothing on screen depends on the edited value: the cell still reconciles itself from the
  response, nothing around it does.
- **Server-side authorization.** The client `disabled()` state is only cosmetic — a per-record
  `disabled()` cell (and any column permission) is enforced again on the server in
  `updateTableCell`, so a forged request can't write to a locked cell.
- **The table can lock the whole row.** `Table::rowInactive()` marks a record inactive — cancelled,
  voided, archived — and every editable column on that row refuses through this same gate, the fill
  handle included. It is a second, independent source of the per-record `disabled()` state above:
  either one locks the cell, whichever was declared first. See
  [Inactive Records](../inactive-records.md).

### Exact versions with `HasLockVersion`

By default a record's version is its `updated_at`, resolved through the model's own timestamp
column (so `const UPDATED_AT` is honoured). That column is stored **to the second**, which leaves
the lock a one-second hole: two writes inside one second leave the same stamp, and a client holding
the stamp from the first is not told about the second. On a grid several people fill in at once,
that second is where the work happens.

`HasLockVersion` closes it. The model gets an integer column the **database** raises on every
Eloquent update (`SET lock_version = lock_version + 1`, never `N + 1` computed in PHP, so two
processes saving the same row get two versions), and the value the write produced is read back
into the model:

```php
Schema::table('order_lines', function (Blueprint $table) {
    $table->unsignedBigInteger('lock_version')->default(0); // [tl! focus]
});
```

```php
use NyonCode\WireCore\Foundation\Support\HasLockVersion;

class OrderLine extends Model
{
    use HasLockVersion; // [tl! focus]

    // protected string $lockVersionColumn = 'revision';   // another column name
}
```

The stamp is then `{lock version}.{updated_at}` — both, because a query-builder write
(`OrderLine::query()->update()`, `increment()`) fires no model event and raises no lock version, but
still moves `updated_at`; with both in the stamp either kind of write is a conflict. Nothing else
changes: every editable column, the fill handle, editable panel entries and an action's
`optimisticLock()` read the version through the same `RecordVersion`. The audit trail leaves the
column out — it moves on every update and nobody wrote it.

## Constraining An Edit You Do Not Own

A column shipped by an installed [module](../../panels/modules.md) declares its
own validation, and an application adds a rule of its own through the
[`cell.updating` hook](../../core/plugins/hooks.md):

```php
$manager->hook(Hook::CellUpdating, function (CellUpdatingPayload $payload) {
    if ($payload->record->approved_at !== null) {                 // [tl! focus]
        $payload->refusal = 'Locked while the invoice is approved.'; // [tl! focus]
    }                                                             // [tl! focus]

    return $payload;
}, for: Invoice::class);
```

`$payload->value` is the dehydrated value about to be written; changing it changes
what lands. Setting `$payload->refusal` stops the write and reaches the browser as
the cell's own error message — the same shape a failed validation produces.

**Where it runs matters.** It is dispatched inside `CellEditPipeline::commit()`,
after the column's permission check, the optimistic-lock check and its validation
— so a callback narrows what is written and cannot widen past a guard the column
declared. That is also the one point the inline editor and the
[fill handle](fill-handle.md) share, so a rule written here cannot be escaped by
dragging across the column.
