---
order: 30
summary: "Buttons on a read-only surface — what an infolist action can reach, and what changes when the infolist is inside an action modal."
---

# Infolist Actions

A read-only surface still has things to do from it: approve, download, open the
record elsewhere. An infolist action is the ordinary [action](../actions/index.md)
rendered here — with one rule worth knowing before you reach for it, which is what
a page composing no host trait can and cannot run.

## Actions

Entries, section headers, and repeatable rows can carry interactive [`Action`](actions.md) buttons — built from the same fluent `Action` API as table and modal actions, and sharing the field-action dispatch contract (`HasFieldActions`). Action **names must be unique** within an infolist.

> **Host requirement.** Infolist actions dispatch through the host's `callInfolistAction()`, provided by the core action runtime (`InteractsWithActions`). They work out of the box when the infolist is shown [inside an action modal](#inside-an-action-modal) (the table / `WithActions` host composes it). A standalone infolist echoed in a plain Livewire component only dispatches if that component composes the action runtime — and the resource `ViewPage` is such a component, so on a resource detail page an action needs a `url()` to do anything at all. See [Panels: Pages](../../panels/pages.md).

**Section header actions** — rendered in the section header, receive the bound record:

```php
Section::make('Profile')
    ->headerActions([
        Action::make('edit')->icon('pencil')->action(fn ($record) => /* … */),
    ])
    ->schema([ /* entries */ ]);
```

**Entry actions** — rendered below the value, receive the record and the entry's `$state`:

```php
TextEntry::make('api_token')
    ->actions([
        Action::make('regenerate')->icon('arrow-path')
            ->action(fn ($record) => $record->regenerateToken()),
    ]);
```

**Per-row actions** — declared on a `RepeatableEntry`, rendered once per row, and invoked with **that row's item** as `$record` / `$state`:

```php
RepeatableEntry::make('lines')
    ->schema([TextEntry::make('sku'), TextEntry::make('qty')->numeric()])
    ->actions([
        Action::make('viewLine')->icon('eye')
            ->action(fn ($record) => /* $record is the row item */),
    ]);
```

## Inside an action modal

A `ViewAction` (or any action) can open a read-only modal that shows the record in an infolist. `infolist()` mirrors `form()`: the action's record is bound automatically, the modal is **not** a confirmation, and it renders only a close button.

```php
use NyonCode\WireCore\Actions\ViewAction;

ViewAction::make()
    ->slideOver()
    ->infolist([
        TextEntry::make('name')->weight('bold'),
        TextEntry::make('email')->copyable(),
        TextEntry::make('created_at')->dateTime()->since(),
    ]);

// Closure form receives the record:
ViewAction::make()->infolist(fn ($record) => Infolist::make()->schema([
    TextEntry::make('name'),
]));
```

## Related

- [Infolists](index.md) — the surface these sit on
- [Actions](../actions/index.md) — the classes being rendered
- [Action Modals](../actions/modals.md) — the infolist modal from the other side
- [Panels: Pages](../../panels/pages.md) — why a view page composes no host trait
