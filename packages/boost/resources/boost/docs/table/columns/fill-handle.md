---
order: 29
nav: false
---

# Fill Handle (Excel-style AutoFill)

Drag a value from one editable cell down over the rows below it, the way you
would in Excel or Google Sheets. The whole range is written in **one** request.

Opt in per table:

```php
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;

public function table(Table $table): Table
{
    return $table
        ->model(Task::class)
        ->fillHandle()                                  // [tl! focus]
        ->columns([
            TextInputColumn::make('reference')->fillable(false),   // [tl! focus]
            SelectColumn::make('status')->options([
                'open' => 'Open',
                'done' => 'Done',
            ]),
            ToggleColumn::make('is_urgent'),
        ]);
}
```

Hover a fillable cell — or focus one — and a small square appears on its
bottom-right corner; it opens into a labelled copy button as the pointer reaches
it. Drag it down and the covered rows highlight. Nothing is written, and no
request is sent, until you release. Escape abandons the drag.

Hover is deliberate: requiring a click first would mean opening an editor just to
reveal the handle. A cell that *has* focus keeps the handle, so it cannot wander
off the row you are typing in.

## API

```php
// Table
->fillHandle(bool $condition = true)   // opt in (default: off)
->isFillHandleEnabled(): bool
->fillMaxRecords(int $max)             // rows one request may write (default 500)
->getFillMaxRecords(): int

// Column
->fillable(bool $condition = true)     // default true for editable columns
->isFillable(): bool
```

`fillable()` only matters on a column that is already editable — a display
column is never fillable. Turn it off where repeating one value is meaningless
or dangerous: an invoice number, a unique code, a per-record token.

## What a fill actually does

Each record goes through the **same path as a single inline edit**: its own
`canEdit()` check, its own validation, its own optimistic-lock version. The
request is one; the writes are per record.

That is deliberate, not a missed optimisation. A single
`UPDATE … WHERE id IN (…)` would skip Eloquent events, casts and mutators, would
not touch `updated_at` — which is what the optimistic lock compares — and could
not express a column persisted through `editableUsing()`, a relation, or a pivot.
A vertical drag can only reach rendered rows, so the row count is bounded by the
page anyway.

### A fill is not all-or-nothing

One row losing its optimistic-lock race, or being disabled for this user, does
**not** discard the rows that did land. Every record gets its own result and the
client reconciles cell by cell — confirming the ones that were written and
rolling back only the ones that were not. A partial fill reports
`":filled of :total rows saved"`.

Only an infrastructure failure rolls the whole transaction back.

### Security

- The endpoint refuses outright unless the table called `fillHandle()`, so it
  cannot be driven against a table that never offered the affordance.
- Records are resolved through the table's own query, so a key outside it
  matches nothing and is reported as missing — a forged request cannot reach a
  row the table never showed.
- Column permissions (`->permission()`) and per-record `canEdit()` are enforced
  again on the server; the client-side `disabled()` state is cosmetic only.
- `fillMaxRecords()` bounds how much one request may write.

## Events

Every filled cell fires `CellUpdating` and `CellUpdated` exactly as a single
inline edit does, so an audit listener needs no change to see fills. There is no
separate bulk event.

## Scope

The first version fills **vertically only** — one column, downwards or upwards
from the source cell. Horizontal fill, rectangular selections, clipboard support
and pattern recognition (1, 2, 3 …) are not implemented; the value is duplicated,
never extrapolated.

## Notes

- Works with mouse, touch and pen through Pointer Events; dragging past the
  viewport edge auto-scrolls.
- The handle never appears on a cell whose control is disabled, nor on a
  per-record readonly cell — those render no editable input at all.
- Like a single inline edit, a fill **does not re-render the table**: the cells
  reconcile themselves so the Alpine state of every other editable cell survives.
- A table with `queryCached()` will keep serving cached rows after a fill until
  the TTL expires, exactly as it does after any other mutation.
- **Fills are queued, one request at a time.** Each fill sends the versions the
  previous one handed back. Dragging again before the last request has answered
  is fine — it waits its turn rather than going out with versions the server has
  already moved past, which it would refuse as a stale write.

## The lock's resolution

A row's version is its `updated_at` as a Unix timestamp, so **two writes inside
the same second are indistinguishable**. A stale version is not detected there.

In practice this only matters when the same rows are written repeatedly in quick
succession, which is why the client serialises its requests. If you drive
`fillTableCells()` yourself — from a test, a script, or your own front end — send
the versions the previous call returned; do not reuse the ones you started with.

## See also

- [Editing & Column-Level Filters](editing.md) — how a single inline save works
- [TextInputColumn](text-input.md) · [SelectColumn](select.md) · [ToggleColumn](toggle.md)
