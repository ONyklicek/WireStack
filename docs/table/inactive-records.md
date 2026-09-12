---
order: 49
summary: A cancelled, voided or archived record that stays in the list and stops being writable — dimmed, struck through if you ask, tinted if you ask, and refused by the server either way.
---

# Inactive Records

A record can stop being live without leaving the table. A cancelled order, a
voided invoice line, an archived customer: still read, still searched, still
counted in the totals — and no longer something anyone should type into.

Hiding it would be a lie about the data. Leaving its editors open is an
invitation to a write the domain has already refused. So the table is told which
records are inactive, once:

```php
->rowInactive(fn (Order $order) => $order->status === 'cancelled')
```

That row now dims, its inline editors refuse to open, and the server refuses the
write even if one is forged. Everything else about the row — its actions, its
checkbox, a click that opens it — keeps working.

## What one call decides

`rowInactive()` takes the predicate, and an optional second argument that shapes
what the state does:

```php
use NyonCode\WireTable\Support\InactiveRow;

->rowInactive(
    fn (Order $order) => $order->status === 'cancelled',
    fn (InactiveRow $row) => $row
        ->strikethrough()      // strike the row's text through
        ->color('danger'),     // and tint it
)
```

The closure receives this table's `InactiveRow` and configures it in place, the
way `gestures()` configures a `TableGestures`. Its return value is ignored, so a
fluent chain and a multi-line body both work.

Pass a plain `true` for a table whose every row is inactive (a read-only archive
screen), or a `false` to state the default explicitly:

```php
->rowInactive()          // every record of this table
->rowInactive(false)     // none — the same as never calling it
```

## The lock is server-side

This is the half that matters, and it is not new machinery: the table pushes its
rule into every editable column's own per-record disabled state, which is the
gate `Column::canEdit()` already applies inside the write pipeline.

That has three consequences worth knowing:

- **A forged request is refused.** The `disabled` attribute a browser sees is
  cosmetic; the refusal happens again when the cell edit commits, under the row
  lock, in `CellEditPipeline::commit()`.
- **The fill handle is covered for free.** A drag that writes one value down a
  hundred rows runs the same per-record gate for each of them, so the cancelled
  rows in the middle of the sweep are skipped and reported, not written.
- **A column's own `disabled()` still applies.** The two rules are separate
  slots and either one disables the cell, whichever was declared first — the
  column's rule for its own cells:

```php
TextInputColumn::make('quantity')->disabled(fn (Order $order) => $order->locked)
```

and the table's, for every editable column of that table at once:

```php
->rowInactive(fn (Order $order) => $order->status === 'cancelled')
```

Declaration order does not matter. `->columns([...])->rowInactive(...)` and
`->rowInactive(...)->columns([...])` lock exactly the same cells.

## The look

Two separate switches, because they say different things.

**`dim()` is on by default.** It mutes the row's text: this row has stopped
being live. It is the quiet statement, and it is true of every inactive record.

**`strikethrough()` waits to be asked.** A strike is a claim about the *content*
— "this value is not part of the total" — which is right for a cancelled order
line and wrong for an archived customer.

```php
->rowInactive($when, fn (InactiveRow $row) => $row->strikethrough())
->rowInactive($when, fn (InactiveRow $row) => $row->dim(false))        // strike only
```

The strike reaches the row's form controls too, not only its text. That is
deliberate and it is the point of the feature: a browser does not inherit
`text-decoration` into an `<input>`, and an untouched input is exactly the thing
a reader would otherwise take for editable.

**`color()` tints the whole row** through the same resolver `rowColor()` uses —
the canonical row-tint owner — so an inactive row and a coloured row cannot
drift apart. Any semantic role or raw hue works, and the tinted row gets its
own same-hue hover and drops the neutral striping:

```php
->rowInactive($when, fn (InactiveRow $row) => $row->color('danger'))
```

An explicit `rowColor()` on the table always wins over it, which is what lets a
table tint by status *and* mark the cancelled rows:

```php
->rowColor(fn (Order $o) => $o->isOverdue() ? 'warning' : null)   // wins where it returns a colour
->rowInactive(
    fn (Order $o) => $o->status === 'cancelled',
    fn (InactiveRow $row) => $row->color('gray'),                 // used where rowColor() returned null
)
```

The row also carries `aria-disabled="true"` and `data-inactive="true"`, so
assistive technology is told and an application can style by
`[data-inactive]` without publishing a view.

## What stays live, and how to take it away

Row actions, the selection checkbox and a record click keep working on an
inactive row. That is the default on purpose: the action that *undoes* the state
("restore", "un-cancel") lives in the row's own action column, and locking the
whole row would take away the one control that gets the record back.

Both of the other two are switchable when an inactive record really is inert.

### `selectable(false)` — keep it out of bulk actions

```php
->rowInactive($when, fn (InactiveRow $row) => $row->selectable(false))
```

The row's checkbox goes `inert` (not merely unclickable — it leaves the tab
order), "select page" and the header box skip the row, and a forged toggle is
refused. Unticking is always allowed, so a row locked *after* it was selected can
still be removed from the selection.

One limit, and it is the honest one: a **"select all matching"** selection is a
query, not a list — that is what makes selecting 128 000 rows possible at all —
and a predicate written in PHP cannot be put into that query. A bulk action that
must not touch inactive records checks the record itself, exactly as it would for
any other rule the database cannot express.

### `actions(false)` — make the row inert

```php
->rowInactive($when, fn (InactiveRow $row) => $row->actions(false))
```

The action cell goes `inert`, the right-click menu is not built for that record,
and `executeTableAction()` / `openActionModal()` refuse it server-side — which
covers a *bound* record action too ([Record Actions](record-actions.md):
`onClick()`, `onDoubleClick()`, `onKey()`), because those execute through the
same two methods. The click still reaches the browser and then does nothing.

`recordUrl()` is the one row-wide click this leaves alone: it is a plain link,
and opening a cancelled record is reading rather than writing. Withhold that too
with `recordUrl(fn ($record) => …)` returning `null` if the record should not
open at all.

### `editing(true)` — the state as a label

For a table where "inactive" is a colour and nothing more:

```php
->rowInactive($when, fn (InactiveRow $row) => $row->editing())
```

## Phones get the same state

The stacked card ([Responsive Layout](overview.md#responsive-layout)) carries the
same dimming, the same strike and the same tint, and the same two locks apply to
its checkbox and its action row. Nothing extra to configure — the card is a
second rendering of the same record, not a second definition.

## A project-wide default

A back office where every cancelled row should look the same says so once, in
`config/wire-table.php`:

```php
'defaults' => [
    'inactive_rows' => [
        'strikethrough' => true,
        'color' => 'danger',
    ],
],
```

Every table that calls `rowInactive()` starts from that; a per-table
configurator overrides it key by key. An unknown key throws rather than doing
nothing quietly.

## Extended Example

```php
use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\InactiveRow;
use NyonCode\WireTable\Table;

class ListOrders extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Order::class)
            ->selectable()
            ->columns([
                TextColumn::make('number')->searchable(),
                TextInputColumn::make('quantity'),      // locked on a cancelled row // [tl! focus]
                TextColumn::make('total')->money('CZK'),
            ])
            ->actions([
                Action::make('restore')                 // still operable — it is what undoes the state // [tl! focus:start]
                    ->visible(fn (Order $order) => $order->status === 'cancelled')
                    ->action(fn (Order $order) => $order->update(['status' => 'open'])),
            ])
            ->rowInactive(
                fn (Order $order) => $order->status === 'cancelled',
                fn (InactiveRow $row) => $row
                    ->strikethrough()
                    ->color('danger')
                    ->selectable(false),                // and keep it out of bulk actions // [tl! focus:end]
            );
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}
```

## API reference

### On the table

```php
->rowInactive(bool|Closure $when = true, Closure|InactiveRow|null $configure = null)
// $when: fn ($record): bool — which records are inactive (true = all of them)
// $configure: fn (InactiveRow $row) => … — configured in place, return value ignored

->hasInactiveRecords(): bool                 // whether this table declares the state at all
->isRecordInactive(?Model $record): bool     // the predicate, resolved
->getInactiveRow(): InactiveRow              // this table's configuration, seeded from config
```

### On `InactiveRow`

```php
->strikethrough(bool $condition = true)  // strike the row's text and its inputs — default false
->dim(bool $condition = true)            // mute the row's text — default true
->color(?string $color)                  // 'danger'|'warning'|… or any hue; null = no tint — default null
->editing(bool $allowed = true)          // may an inactive row still be edited — default false
->selectable(bool $allowed = true)       // may it be ticked — default true
->actions(bool $allowed = true)          // are its row actions operable — default true

->isStrikethrough(): bool
->isDimmed(): bool
->getColor(): ?string
->allowsEditing(): bool
->allowsSelection(): bool
->allowsActions(): bool
```

## Troubleshooting

**The row looks inactive but a cell still saves.** The predicate is resolved per
record on both paths, so this means the predicate itself disagrees — check that
it reads a *persisted* attribute rather than one set only during the render.

**Nothing is struck through.** `strikethrough()` is off by default; `dim()` is
the shipped look. Set it per table, or project-wide in the config.

**The strike shows on the text but not on a badge or a link.** A descendant that
sets its own colour or decoration keeps it — that is the same rule that lets a
badge stay legible inside a tinted row.

## See Also

- [Inline Editing](overview.md#inline-editing) — the editors this state locks
- [Selection](selection.md) — what a locked checkbox means for bulk actions
- [Record Actions](record-actions.md) — the click this state leaves alone
- [The Gesture Layer](gestures.md) — the fill handle, which the same gate covers
