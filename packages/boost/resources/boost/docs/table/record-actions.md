---
order: 45
---

# Record Actions

Record actions turn a whole row into an affordance: a double-click opens the
record, a right-click shows a menu, the arrow keys move between rows and Enter
runs the primary action — the way a desktop app behaves. They are a distinct
group from row `->actions()`, `->bulkActions()` and `->headerActions()`, and they
run through the same execution as any other action, so authorization,
confirmation modals and forms all work unchanged.

## Basic usage

Bind an action to a whole-row gesture. Because the trigger lives on the binding,
the fluent methods read as the gesture:

```php
use NyonCode\WireCore\Actions\Action;

->recordActions([
    Action::make('view')->onClick(),

    Action::make('edit')
        ->icon('pencil')
        ->onDoubleClick()
        ->action(fn (User $record) => $this->edit($record)),
])
```

`Action::make(...)->onDoubleClick()` returns a **record action**, so it belongs in
`recordActions()`, never in `->actions()`.

### Reference an existing action

If the action already exists in `->actions()`, name it instead of redefining it:

```php
->actions([
    Action::make('edit')->action(fn (User $record) => /* … */),
])
->recordAction('edit') // double-click runs the same 'edit' action
```

A bare name defaults to the double-click trigger.

## Triggers

| Trigger | Method | Typical use |
|---------|--------|-------------|
| Single click | `->onClick()` | Open a record when the table has no selection |
| Double click | `->onDoubleClick()` | Open / edit — the recommended primary |
| Right click | `->onContextMenu()` | A row context menu |
| Key | `->onKey('Delete')` | A keyboard shortcut against the active row |

`->onKey()` is sugar over the canonical `->keyboardShortcut()`, so `mod+d`, `ctrl+c`
and single keys all resolve the same way (⌘ on Mac, Ctrl elsewhere). One binding
can carry several triggers:

```php
Action::make('edit')->onDoubleClick()->onKey('Enter')
```

## Behaviour-only vs. also a button

A record action renders **no button** by default — it is pure row behaviour. This
is what lets a table feel like an app instead of a grid of buttons:

```php
Action::make('open')
    ->onDoubleClick()
    ->behaviorOnly() // default; the row is the only affordance
```

Opt in to *also* showing it in the actions column:

```php
Action::make('edit')->onDoubleClick()->alsoInRowActions()
```

## Multiple record actions

```php
->recordActions([
    Action::make('view')->onClick(),
    Action::make('edit')->onDoubleClick(),
    Action::make('preview')->onContextMenu(),
])
```

## Keyboard navigation

When a table has any record action, keyboard navigation turns on automatically
and the table announces itself as a grid:

| Key | Action |
|-----|--------|
| `↑` / `↓` | Move the active row |
| `Enter` | Primary record action (double-click binding, else click) |
| `Shift` + `Enter` | Secondary record action (the other pointer binding) |
| `Space` | Toggle selection of the active row (and set the range anchor) when selectable, else the primary action |
| `Shift` + `↑` / `↓` | Extend a contiguous selection range from the anchor (desktop range-select) |
| `mod` + `A` | Select every row on the page |
| Menu key | Open the row context menu |
| `Delete`, `mod+d`, … | Any record action's own `->onKey()` / `->keyboardShortcut()` |

Keyboard selection drives the **same** selection state as the checkboxes and the
bulk-action bar — arrow to a row, `Space` to select it, `Shift`+arrow to extend a
block — then run the bulk action from the bar.

`Shift`+`↑`/`↓` builds **one contiguous block from the anchor** and makes that
block the selection. The anchor is the row you last picked — with `Space`, with a
checkbox — and when the selection came from `mod`+`A` or the select-all strip
(no anchor of its own) it falls back to the far edge of the block you are
standing in, so the first `Shift`+arrow *shrinks or grows* that block instead of
throwing the rest of the selection away. To drop individual rows out of a
selection, arrow to each one and press `Space`.

Pointer and keyboard share one active row: **clicking a row marks it** and the
arrows continue from there, so a table is never navigated from two places at
once. The marker stays visible while the pointer hovers the row it marks, it
survives the roundtrip an action triggers, and it follows its record through a
re-sort (when the record leaves the page entirely, the tabstop falls back to the
first row).

Keys only reach the grid when a **row itself** has the focus: a keystroke inside
a row action button, an inline-editable cell or a dropdown belongs to that
element. While an action modal is open the grid is inert — no arrow moves the
marker behind the dialog and no shortcut fires a second action — and closing the
modal hands the focus back to the active row, so the arrows keep working.

Force it off (or on) if you need to:

```php
->recordActionKeyboard(false)
```

Because Enter always reaches the primary action, every record action stays
keyboard-accessible — a behaviour-only action is never a mouse-only trap.

## Combining with selection and bulk actions

When the table is `->selectable()`, the default record-action trigger becomes
**double-click**, so a single click stays free for working with the selection —
it only marks the row it lands on (the active row for the keyboard and the anchor
of the next `Shift`+range). Selection itself is always the checkbox's job: a
click on the row never ticks it. Bulk actions are untouched:

```php
->selectable()
->bulkActions([DeleteBulkAction::make()])
->recordActions([
    Action::make('open')->onDoubleClick()->action(/* … */),
])
```

## Styling

The row shows a pointer cursor when it is clickable. Keep the neutral hover, or
tint it for a stronger "this row is clickable" hint:

```php
->recordActionHover('primary')   // colored hover instead of neutral gray
->activeRowClass('bg-amber-100')  // override the active-row marker (click + keyboard)
```

The active row drops its hover tint while it is marked, so the marker is never
painted over by `hover:bg-*` when the pointer rests on it.

## Recommended UX

- **App-like table** — `->recordAction('open')->behaviorOnly()` plus a context
  menu; shrink or drop the actions column. Rows behave like items in a file
  explorer.
- **Classic table + shortcut** — keep the full actions column and add
  `->recordAction('view')->onDoubleClick()->alsoInRowActions()` purely as an
  accelerator.
- **Read-heavy** — double-click opens a detail view; right-click offers
  edit / delete.

## Common mistakes

- **Single click as the primary on a selectable table** — it steals the
  selection click. Use double-click (the default when selectable).
- **Putting `Action::make()->onDoubleClick()` in `->actions()`** — it returns a
  record action and is rejected there; pass it to `recordActions()`.
- **Expecting record actions on the mobile card or sub-rows** — record actions
  are a desktop pointer affordance on the main rows; touch cards use the visible
  action buttons, and sub-rows are intentionally excluded.

## Migrating from `rowContextMenu()`

`Table::rowContextMenu([...])` is deprecated. Bind the right-click trigger
instead:

```php
// before
->rowContextMenu([Action::make('edit'), Action::make('delete')])

// after
->recordActions([
    Action::make('edit')->onContextMenu(),
    Action::make('delete')->onContextMenu(),
])
```
