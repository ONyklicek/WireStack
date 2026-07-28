---
order: 47
---

# Selecting Rows

Selection can be a gesture surface, not just a column of checkboxes. A table that
is `->selectable()` — or that merely has `->bulkActions()`, which implies it —
gives you the checkboxes, the select-all controls and the bulk bar:

```php
->selectable()
->bulkActions([DeleteBulkAction::make()])
```

Add `->gestures()` and it behaves like a list in a desktop file manager as well:
`Shift`+click takes a range, `mod`+click adds one row, a drag down the checkbox
column sweeps a block in, and from the keyboard the arrows walk the rows,
`Space` toggles, `Shift`+arrows extend and `mod`+`A` takes the page.

```php
->gestures()
->selectable()
```

That split is deliberate: keyboard navigation, range selection and the drag
sweep all change how the table answers someone who never meant to operate it, so
they wait to be asked (see [The Gesture Layer](gestures.md)). Everything below is
marked with what it needs.

## What the mouse does

| Gesture | Result | Needs |
|---------|--------|-------|
| Click the selection cell | Toggle that row, and set the range anchor | — |
| `Shift` + click | Select the range between the anchor and this row | `gestures()` |
| `mod` + click | Toggle this one row, anywhere on it, and anchor here | `gestures()` |
| `mod` + `Shift` + click | Add the whole block to what is already selected | `gestures()` |
| Drag down the checkbox column | Sweep a run of rows into the selection | `gestures()` |
| Click the row itself | Marks the row (see below) — it never ticks the checkbox | — |

The **whole selection cell** is the target, not just the 16-pixel box inside it:
the box alone is under every touch-target guideline and leaves most of the cell
dead. A click in the cell can never reach a record action bound to the row.

A plain click on the row *body* marks the row — it becomes the active row for
the keyboard, and the anchor for the next range — but never selects it.
Selection stays deliberate. `mod`+click is the exception, and that is what the
modifier is for.

## What the keyboard does

Everything in this section needs `->gestures()` — see
[The Gesture Layer](gestures.md).

| Key | Result |
|-----|--------|
| `↑` / `↓` | Move the active row |
| `Home` / `End` | Jump to the first / last row on the page |
| `PageUp` / `PageDown` | Move by one screenful |
| `Space` | Toggle the active row, and anchor here |
| `Shift` + `↑` / `↓` | Grow or shrink the range from the anchor |
| `Shift` + `Home` / `End` | Extend the range to the first / last row |
| `mod` + `Shift` + `↑` / `↓` | The same as `Shift`+`Home` / `End` |
| `mod` + `A` | Select every row on the page |
| `?` | Show the shortcuts this table answers to |

Keyboard selection drives the **same** state as the checkboxes and the bulk bar:
arrow to a row, `Space` to select, `Shift`+arrow to extend, then run the bulk
action.

Keys only reach the table when a **row itself** has the focus. A keystroke inside
a row — an action button, an inline-editable cell, a dropdown — belongs to that
element, so `Space` typed into a cell stays a space and `?` typed into the search
box does not open the help.

## How ranges behave

Every range grows from an **anchor**: the row you last picked with `Space`, with
a checkbox, or with `mod`+click. The anchor is invisible and one-shot — a plain
arrow move clears it.

A range writes **what was already selected, plus the range** — not the range
alone. Select rows 2–6, anchor on row 8, `Shift`+arrow down to 12, and you have
2–6 and 8–12. Shrinking the range back gives up only the rows the range itself
added.

When the selection carries no anchor of its own — it came from `mod`+`A`, or from
the select-all strip — the first `Shift`+arrow grows from the far edge of the
contiguous block you are standing in. That way it *shrinks or grows the block you
can see* instead of throwing the rest of the selection away.

To drop individual rows out of a selection, arrow to each one and press `Space`,
or `mod`+click them.

## Selecting beyond the page

The bulk bar offers **Select all N** once a page is selected, which switches the
selection from an explicit list of keys to "everything the current filter
matches" (see [Bulk Actions](actions.md#bulk-actions) for what that
shape means and why it exists).

Inside that mode the gestures still work, and they read the way you would expect
of "everything except…":

- `Shift`+arrow over a range **deselects** it, because the stored list is the set
  of exclusions.
- `mod`+`A` stands down. Everything is already selected; there is nothing for it
  to add.
- The header checkbox edits the exclusions and never silently drops you back to
  an explicit selection.

## Dragging down the column

Press in the checkbox column and drag: every row you pass is selected, and the
table scrolls when you reach its edge. The gesture is deliberately narrow.

- **Additive only.** Backing up does not deselect. A sweep can only ever add.
- **Mouse only.** A finger dragging the checkbox column scrolls the page, as it
  should.
- **Only in the checkbox column.** Dragging anywhere else selects text, as it
  always did.
- It starts on the first movement that changes rows, so a plain click stays a
  plain click.

## The shortcut help

Press `?` with a row focused and the table shows exactly what it answers to —
including any `->onKey()` binding of your own, and its label. The list is built
from the table's own configuration, so a table without record actions does not
claim to have any.

The same list is available as data if you want to render it yourself:

```php
$sections = $table->shortcutLegend()->sections();
```

Each section has a translated `heading` and a list of `ShortcutHint` value
objects (`->keys`, `->description`, `->labels(mac: true)`).

## Accessibility

Selection is not a mouse-only feature, and the table says so:

- The table is an ARIA **grid**: `aria-rowcount`, `aria-multiselectable`, and an
  `aria-rowindex` on every row counted through the whole result set — so row 1 of
  page 2 announces as row 12, not row 1 again.
- Every row reports `aria-selected`, kept in step with the live selection rather
  than the last server response.
- Selection changes are announced in a polite live region: *"3 of 40 selected"*,
  *"All 40 selected"*, *"Selection cleared"*.
- The active row is marked by a background tint **and** a stripe down its leading
  edge. Colour alone would fail anyone who cannot separate the two hues, and the
  tint alone measures about 1.1:1 — under the 3:1 contrast floor. The stripe
  clears it in both light and dark.

Override the marker if it clashes with your design — an override replaces both
halves, so it owns its own contrast:

```php
->activeRowClass('bg-amber-100 [&>td:first-of-type]:before:bg-amber-600')
```

## Keys the table reserves

The grid owns the keys it navigates with, so binding a record action to one of
them would be dead code. `->onKey()` refuses at configuration time rather than
silently dropping the binding:

```text
Enter  Space  ArrowUp  ArrowDown  Home  End  PageUp  PageDown  ContextMenu  F10  ?
```

`Backspace` is deliberately **not** reserved: it acts as a platform alias of
`Delete`, so a `->onKey('Delete')` binding answers to both, and an explicit
`->onKey('Backspace')` stays valid.

## Turning it back off

A table that asked for the gesture layer can hand back one capability at a time,
or the lot:

```php
->gestures(fn (TableGestures $g) => $g->dragSelect(false))  // keep the keyboard, drop the sweep
->gestures(fn (TableGestures $g) => $g->keyboard(false))    // the other way round
->gestures(false)                                           // every gesture, ranges included
```

The checkboxes keep working in every one of those — see
[The Gesture Layer](gestures.md).

## Related docs

- [Record Actions](record-actions.md) — whole-row click, double-click and
  context-menu bindings
- [Bulk Actions](actions.md#bulk-actions) — acting on a selection
- [The Gesture Layer](gestures.md) — switching these gestures off, whole or in part
