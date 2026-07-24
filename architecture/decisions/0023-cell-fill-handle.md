# ADR 0023: Excel-style Cell Fill Handle

## Status
Accepted

## Context

Editable table cells could only be changed one at a time. Setting the same value
on twenty rows meant twenty edits and twenty round-trips.

The obvious shape — an Excel fill handle — raises three questions the existing
inline-edit design does not answer:

1. What does "one bulk update" mean when `CellValueWriter` has five persistence
   strategies (save callback, `editableUsing()`, pivot, relation, plain attribute)
   and the optimistic lock compares `updated_at`?
2. Where does the handle live, given `TextInputColumn` renders its cell once into
   a skeleton and splices three per-record tokens into it?
3. How do the filled cells learn their new values, when `updateTableCell()`
   deliberately `skipRender()`s so a DOM morph cannot reset every cell's Alpine
   state?

## Decision

### One request, one transaction, per-record writes

The fill sends exactly one request and opens one transaction, but writes each
record individually through the same pipeline as a single inline edit
(`CellEditPipeline`).

A single `UPDATE … WHERE key IN (…)` was rejected: it bypasses Eloquent events,
casts and mutators, does not touch `updated_at` — which would silently disable
the optimistic lock — and cannot express four of the five persistence branches.
A vertical drag can only reach rendered rows, so the record count is bounded by
the page; `Table::fillMaxRecords()` bounds a forged request.

**A fill is not atomic across records.** Per-record refusals (a lost lock race, a
disabled cell, a failed rule) are returned as results, not thrown, so one bad row
cannot discard the rest. Only an infrastructure failure rolls back.

### One handle per table, positioned by JS

The handle is a single element rendered once per table and moved onto the active
cell by `wireFillHandle`. A per-cell handle would be columns×rows elements of
record-invariant markup — the render-cost model forbids exactly that — and it
would have to live inside the editable cell partial, where the skeleton splice
would freeze it at the first row's values.

### Cells reconcile through their own Alpine state

The controller writes `value`, `serverValue` and `recordVersion` on each cell's
`wireEditableCell` component.

The first implementation used the `data-server-value` attribute instead, on the
grounds that the cell already observes it. That was wrong: the attribute is a
*server→client* channel written by Blade and nothing keeps it current
afterwards, so a cell edited inline still carries the value it was rendered with.
Copying it filled the range with a stale value while sending the live one to the
server — the two disagreed until the next full render. The browser driver caught
this; the PHP suite could not.

### Opt-in

`Table::fillHandle()` is off by default, and the server refuses the endpoint
unless it is on. Enabling it by default would silently change behaviour for every
existing table with an editable column: a drag across cells would start
overwriting data.

## Consequences

- **Good:** one request for a whole range, with no weakening of validation,
  authorization, events or optimistic locking.
- **Good:** the endpoint takes a *list* of `{column, value, records}` entries, so
  horizontal and rectangular fill need no second endpoint. `FillRange` already
  carries `{row, col}`; v1 only clamps the column.
- **Good:** per-record results let the client roll back precisely.
- **Trade-off:** writes are O(rows), not one statement. Acceptable while the row
  count is bounded by the page; a future virtual-scroll mode would need to
  revisit the cap.
- **Trade-off:** the controller depends on `wireEditableCell`'s public state
  (`value`, `serverValue`, `recordVersion`, `parse`). That coupling is real, and
  is the price of not re-rendering the table.
- **Not done:** no bulk `CellsFilled` event. Per-cell `CellUpdating`/`CellUpdated`
  already fire, so an audit listener sees fills unchanged; adding a bulk event
  later is non-breaking, removing one would not be.

## See also

- `architecture/plans/excel-fill-handle.md` — the implementation plan
- ADR 0003 — why inline editing columns are standalone
- ADR 0021 — the state dehydration contract the fill reuses
