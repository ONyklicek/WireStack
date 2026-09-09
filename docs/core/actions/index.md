---
order: 10
summary: "The four action classes, what each callback receives, and the fluent surface they all share."
---

# Actions

An action is a declared button with a callback behind it: `Action` for one
record, `BulkAction` for a selection, `HeaderAction` for neither, and
`ActionGroup` to fold several into a dropdown. All of them extend `BaseAction`,
so what you learn here holds wherever a button is drawn — a table row, a header,
an infolist, a page, or a component of your own.

## Action Types

| Class | Use Case | Callback Receives |
|-------|----------|-------------------|
| `Action` | Row action — single record | `fn (Model $record, array $data)` |
| `BulkAction` | Selected records | `fn (Collection $records, array $data)` |
| `HeaderAction` | Table header — no record context | `fn (array $data)` |
| `ActionGroup` | Groups actions into a dropdown | — |

All extend `BaseAction` and share the same fluent API for label, icon, color, size, modal, lifecycle.

## Pre-built Actions

| Class | Description |
|-------|-------------|
| `DeleteAction` | Single record delete with confirmation |
| `DeleteBulkAction` | Bulk delete with confirmation |
| `RestoreBulkAction` | Bulk restore of soft-deleted records, with confirmation |
| `ForceDeleteBulkAction` | Bulk permanent delete of soft-deleted records, with confirmation |
| `EditAction` | Opens edit modal/form |
| `ViewAction` | Opens view modal |

```php
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

$table->actions([DeleteAction::make()])
      ->bulkActions([DeleteBulkAction::make()]);
```

Each preset ships the label, icon, color and confirmation modal; you supply the
behavior with `->action()`. The soft-delete presets pair with a table scoped to
trashed records (e.g. `->query(User::onlyTrashed())`):

```php
use NyonCode\WireCore\Actions\ForceDeleteBulkAction;
use NyonCode\WireCore\Actions\RestoreBulkAction;

$table->bulkActions([
    RestoreBulkAction::make()->action(fn ($records) => $records->each->restore()),
    ForceDeleteBulkAction::make()->action(fn ($records) => $records->each->forceDelete()),
]);
```

## Basic Usage

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;

// Row action
Action::make('edit')
    ->label('Edit')
    ->icon('pencil')
    ->color('primary')
    ->url(fn (User $record) => route('users.edit', $record)) // [tl! focus]

// Row action with callback
Action::make('archive')
    ->label('Archive')
    ->icon('archive')
    ->action(fn (User $record) => $record->update(['archived' => true])) // [tl! focus]
    ->successNotification('Archived!')

// Bulk action
BulkAction::make('export')
    ->label('Export Selected')
    ->icon('download')
    ->action(fn (Collection $records) => Excel::download($records)) // [tl! focus:start]
    ->deselectRecordsAfterCompletion() // [tl! focus:end]

// Header action
HeaderAction::make('create')
    ->label('New User')
    ->icon('plus')
    ->url(route('users.create'))
    ->badge(fn () => User::whereNull('verified_at')->count()) // [tl! focus:start]
    ->badgeColor('danger') // [tl! focus:end]
```

## Action Groups

Collapse secondary actions into a dropdown menu. On a phone the menu opens as a
bottom sheet — override with `->sheetOnMobile(false)` / `->mobileBreakpoint('md')`;
see [mobile presentation](../../start/configuration.md#mobile).

```php
use NyonCode\WireCore\Actions\ActionGroup;

$table->actions([
    Action::make('edit')->icon('pencil'),

    ActionGroup::make('more', [
        Action::make('duplicate')
            ->icon('copy')
            ->action(fn ($record) => $record->replicate()->save()),
        Action::make('archive')
            ->icon('archive')
            ->action(fn ($record) => $record->archive()),
        Action::divider(),                    // visual separator
        Action::make('delete')
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn ($record) => $record->delete()),
    ])->divided(),                            // auto-insert dividers between items
]);
```

Groups support `badge()` and `badgeColor()` just like HeaderAction, plus three
settings of their own:

```php
->dropdownPosition(string|Placement $position)   // 'bottom-start'|'bottom-end'|'top-start'|'top-end' — default 'bottom-end'
->dropdownWidth(string $width)                   // a Tailwind width class for the panel, e.g. 'w-56'
->lazyMenu(bool $lazy = true)                    // build the menu in the browser on first open
```

`lazyMenu()` is the one worth a sentence. By default a group renders its whole
menu into every row; with it the row carries the trigger and a JSON spec of the
items, and the menu is built on first open. That trades a small open-time cost
for a large drop in per-row render work — worth it on a long table where every
row has a group, and not worth it on a table with three rows.

## Dynamic Properties

All properties support Closures — evaluated per-record at render time:

```php
Action::make('toggle')
    ->label(fn (User $record) => $record->is_active ? 'Deactivate' : 'Activate')
    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
    ->icon(fn (User $record) => $record->is_active ? 'x' : 'check')
    ->hidden(fn (User $record) => $record->trashed())
```

## BaseAction API Reference

Shared across Action, BulkAction, HeaderAction:

```php
->label(string|Closure $label)
->icon(string|Closure $icon, ?string $position = null)   // position: 'before' | 'after'
->color(string|Closure $color)          // primary, danger, success, warning, info, gray
->size(string $size)                    // xs, sm, md, lg
->outlined(bool $outlined = true)
->tooltip(string|Closure $tooltip)
->action(Closure $callback)
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->disabled(bool|Closure $disabled = true)
->requiresConfirmation()
->modalHeading(string $heading)
->modalDescription(string $description)
->modalIcon(string $icon, ?string $color)
->modalWidth(string $width)
->modalSubmitActionLabel(string $label)
->modalCancelActionLabel(string $label)
->slideOver()
->form(array $components)
->fillFormUsing(Closure $fn)
->steps(array $steps)
->modal(ModalContract $modal)        // Modal | SlideOver | ConfirmationDialog | Wizard
->before(Closure $fn)
->after(Closure $fn)
->successNotification(string $message)
->failureNotification(string $message)
->keyboardShortcut(string $keys)
->extraAttributes(array $attrs)
```

Row-action (`Action`) presentation overrides, honored under `Table::actionsStyle('quiet')`:

```php
->quiet(bool $quiet = true)   // neutral at rest, color on hover/focus (usually set table-wide)
->solid(bool $solid = true)   // force the solid fill even under a quiet table
```

## Blade Components

```blade
<x-wire-actions::button :action="$action" />
<x-wire-actions::group :group="$group" />
<x-wire-actions::modal-host :component="$this" />  {{-- for a WithActions host --}}
<x-wire-actions::halt-host :component="$this" />   {{-- only for a halt without the runtime --}}
```

## In This Section

| Page | What it covers |
| --- | --- |
| [Action Modals](modals.md) | Confirmation, slide-over, form and infolist modals, wizards, and stacking them |
| [Lifecycle And Queues](lifecycle.md) | The hooks around a run, halting it from inside one — or from a component with no actions at all — and handing the work to a queue |
| [Buttons And Appearance](appearance.md) | Icon buttons, links, shortcuts, sizing, and the quiet row variant |
| [Outside A Table](standalone.md) | `WithActions` on any Livewire component |
| [Workflow And Transitions](workflow.md) | Which state moves are legal, declared in one place |

## Related

- [Modals](../modals.md) — the modal classes an action opens
- [Table Actions](../../table/actions.md) — the table's own row, bulk and header actions
- [Record Actions](../../table/record-actions.md) — a whole row as an affordance
- [Notifications](../notifications/index.md) — what an action says when it finishes
- [Authorization](../../start/authorization.md) — the gate every action consults
