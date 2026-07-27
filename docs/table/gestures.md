---
order: 48
---

# The Gesture Layer

A wire-table table can behave like a desktop application: arrow keys walk the
rows, `Shift` works a range, the mouse sweeps down the checkbox column, right
click opens a row menu, `?` explains itself, and a fill handle drags one value
across many cells.

That is exactly right for a back office. It is usually wrong for a public
listing, where a highlighted row and a hijacked right click are noise at best.

So it is one switch, and a table starts on the quiet side of it:

```php
->gestures()
```

That is the desktop-application table. Without it you get an ordinary web
table — the one most pages want.

## What a table gets without asking

**Keyboard navigation and the drag sweep are off until you ask.** They are the
two that change how the table answers a visitor who never intended to operate
it: keyboard navigation puts the rows in the tab order, marks an active row and
starts answering arrows and `mod`+key, and the sweep turns a press in the
checkbox column into a block selection — a gesture people find by accident
before they find it on purpose.

What is left needs an invitation of its own anyway, so it is allowed from the
start: a range needs `->selectable()`, a context menu needs actions bound to it,
the fill handle needs `->fillHandle()`, and the `?` help needs the keyboard
layer this default leaves off.

```php
// An ordinary listing. Checkboxes work, Shift+click still ranges,
// nothing answers an arrow key, no drag selects.
Table::make()->selectable()

// The same table as an application.
Table::make()->gestures()->selectable()
```

To go further the other way — no right-click menu, no ranges, no fill handle,
nothing at all — say so:

```php
->gestures(false)
```

## What counts as a gesture

Six capabilities, each switchable on its own. "Default" is what a table that
never calls `gestures()` gets:

| Capability | Default | What it covers |
|------------|---------|----------------|
| `keyboard` | **off** | Grid navigation: roving `tabindex`, arrows, `Home`/`End`, `PageUp`/`PageDown`, `Enter` / `Shift`+`Enter` for the primary and secondary record action, `Space` to toggle the selection, and every action's own `keyboardShortcut()` / `onKey()` against the active row. Also what makes the table an ARIA `grid`. |
| `rangeSelection` | on | `Shift`+click, `mod`+click and `mod`+`Shift`+click on a row, plus `Shift`+arrow, `Shift`+`Home` and `Shift`+`End` from the keyboard. |
| `dragSelect` | **off** | The mouse sweep: press in the checkbox column and drag to select a block of rows. |
| `contextMenu` | on | The right-click row menu — both `rowContextMenu()` and any `onContextMenu()` record action. |
| `shortcutHelp` | on¹ | The `?` shortcut help. |
| `fillHandle` | on² | The Excel-style fill handle on editable cells. |

`mod` is `Ctrl` on Windows and `⌘` on macOS.

¹ Allowed, but it reads the keyboard layer, so with the default it never opens.
² Allowed, but the table still has to call `->fillHandle()`.

## Mixing them

Pass a closure. It receives this table's gestures and configures them in place —
the return value is ignored, so a fluent chain and a multi-line body both work.

```php
->gestures(fn (TableGestures $g) => $g
    ->keyboard()          // arrows, Enter, shortcuts …
    ->dragSelect(false))  // … but still no mouse sweep
```

Every setter takes a `bool`, so `->contextMenu(false)` reads as well as
`->contextMenu()`.

You can also hand over a prepared set, which is useful when several tables share
one house style:

```php
use NyonCode\WireTable\Support\TableGestures;

$readOnly = TableGestures::none()->contextMenu();

// …then, in each table:
->gestures($readOnly)
```

`TableGestures::defaults()`, `TableGestures::all()` and `TableGestures::none()`
are the three starting points: the shipped default, everything, nothing.

## A permission is not a switch-on

Every capability is a **permission**, never a trigger. Turning one on does not
conjure the thing it governs:

- `dragSelect` and `rangeSelection` still need `->selectable()` (or
  `->bulkActions()`, which implies it) — there has to be a selection for a range
  to grow in.
- `fillHandle` still needs `->fillHandle()` on the table and editable columns.
- `shortcutHelp` still needs the keyboard layer, because the keyboard layer is
  what listens for the key.

So `->gestures(fn ($g) => $g->dragSelect())` on a table without `selectable()`
changes nothing. This is deliberate: the gesture layer decides what a table is
*allowed* to do, and the rest of the table API decides what it *has*.

## `keyboard()` has three states

The other five capabilities are plain booleans. `keyboard` is three-state,
because "on" has to mean two different things:

| Value | Meaning |
|-------|---------|
| `false` (the default) | Off |
| `null` | The table decides — on for a table with record actions or a selectable one. This is what `gestures()` sets |
| `true` | Force it on, even for a table with neither |

`gestures()` leaves the keyboard at `null` rather than forcing it, because a
table with no record actions and no selection has nothing for the arrows to do,
and a roving tabindex over inert rows is worse than none:

```php
Table::make()->gestures()                                          // not a grid
Table::make()->gestures()->selectable()                            // a grid
Table::make()->gestures(fn (TableGestures $g) => $g->keyboard(true))   // a grid regardless
```

## What the layer does *not* govern

**An explicitly declared record action keeps firing.** A binding like

```php
->recordAction(RecordAction::make(Action::make('view'))->onClick())
```

is a deliberate statement about this table, not an implicit affordance the table
turned on for itself — so it survives `gestures(false)`. The gesture layer only
governs the layer a table would otherwise switch on for itself.

The one exception is `->onKey()`, which needs a keyboard layer to listen with.
With `keyboard` off, an `onKey()` binding has nowhere to fire from.

Selection itself is likewise untouched. With every gesture off, the checkboxes,
both select-all controls and the bulk bar work exactly as they always did — you
lose the shortcuts to them, not the feature.

## The active-row marker

Rows carry the active-row marker when any gesture needs somewhere to grow from —
that is, when the table uses grid semantics, range selection, or the sweep.

A table left with nothing but a declared click action marks nothing. A click
there opens the record and moves on; a highlighted row left behind would be an
application affordance on a page that asked for none.

## A project-wide default

Set it once for every table:

```php
// config/wire-table.php
'defaults' => [
    'gestures' => true,
],
```

`null` (or a missing key) keeps the shipped default described above, `true`
allows everything for every table — a back office turns the layer on once here
instead of on every table — `false` allows nothing, and a map mixes:

```php
'gestures' => ['keyboard' => true, 'drag_select' => false],
```

Capability keys are matched loosely — `drag_select`, `drag-select`, `dragSelect`
and `dragselect` are the same key. An **unknown** key throws
`TableConfigurationException` rather than doing nothing quietly, because a typo
in a permission is the kind of mistake that only shows up as "why doesn't this
work" six months later.

A per-table `->gestures(...)` always wins over the config default.

## It is off on the server too

Switching a capability off is not a matter of the client ignoring events. The
markup and the endpoints go with it:

- The delegated Alpine controllers are not rendered. A table with nothing but the
  gestures off renders no controller at all, and its asset bundles are not
  requested.
- The table stops being an ARIA `grid`: no `role="grid"`, no `role="row"`, no
  roving `tabindex`.
- The rows are not focusable, so nothing steals focus on click.
- The fill endpoint refuses. `fillHandle` off closes `fillTableCells` server-side,
  not just the handle in the UI.
- The shortcut legend drops the rows it no longer applies to — with ranges off,
  `Shift`+arrow is not listed in the `?` help, because it does not work.

That last point is the general rule: the legend is generated from what the table
actually does, so it can never drift from reality.

## Phones get buttons instead

A gesture-driven table is a desktop idea. There is no double click on a phone,
no right click, and no hover to discover either of them — so a record action
that is behaviour-only on the desktop would be **unreachable** on a stacked
mobile card.

It is therefore rendered as an ordinary button there, and only there:

```php
->recordAction(RecordAction::make(Action::make('open'))->onDoubleClick())
```

| Surface | What the user gets |
|---------|--------------------|
| Desktop | A double-click gesture. No column, no button. |
| Mobile card | An `Open` button. |

The fallback is careful about not doubling anything:

- Actions already in `->actions()` keep their order, and the record actions are
  appended after them.
- `recordAction('edit')`, which only *references* an action already declared in
  `->actions()`, shows one button — not the same one twice.
- An action promoted into the column with `->alsoInRowActions()` is already a
  button, so it is left alone.
- The fallback buttons count towards `->collapseActionsOnMobile()`, so a card
  does not quietly grow past the threshold you set.

Switch it off when a card is meant to stay clean:

```php
->recordActionButtonsOnMobile(false)
```

## Choosing

| Table | Suggested |
|-------|-----------|
| Public listing, marketing page | Nothing to do — the default |
| Back-office list, keyboard-heavy operators | `->gestures()`, or `'gestures' => true` in config |
| Public page that must not even right-click | `->gestures(false)` |
| Read-only report, right click still useful | `TableGestures::none()->contextMenu()` |
| Long list, keyboard useful, sweeping risky | `->gestures(fn ($g) => $g->keyboard())` |
| Embedded in a page with its own keyboard handling | `->gestures(fn ($g) => $g->dragSelect())` |

## See Also

- [Selecting Rows](selection.md) — what each selection gesture does
- [Record Actions](record-actions.md) — binding an action to a row gesture
- [Advanced](advanced.md) — the fill handle
