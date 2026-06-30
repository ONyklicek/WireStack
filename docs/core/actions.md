---
order: 10
---

# Actions

The Actions module provides row, bulk, and header actions for tables and related UI flows.

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
| `EditAction` | Opens edit modal/form |
| `ViewAction` | Opens view modal |

```php
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

$table->actions([DeleteAction::make()])
      ->bulkActions([DeleteBulkAction::make()]);
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
    ->url(fn (User $record) => route('users.edit', $record))

// Row action with callback
Action::make('archive')
    ->label('Archive')
    ->icon('archive')
    ->action(fn (User $record) => $record->update(['archived' => true]))
    ->successNotification('Archived!')

// Bulk action
BulkAction::make('export')
    ->label('Export Selected')
    ->icon('download')
    ->action(fn (Collection $records) => Excel::download($records))
    ->deselectRecordsAfterCompletion()

// Header action
HeaderAction::make('create')
    ->label('New User')
    ->icon('plus')
    ->url(route('users.create'))
    ->badge(fn () => User::whereNull('verified_at')->count())
    ->badgeColor('danger')
```

## Action Groups

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

Groups support `badge()` and `badgeColor()` just like HeaderAction.

## Dynamic Properties

All properties support Closures — evaluated per-record at render time:

```php
Action::make('toggle')
    ->label(fn (User $record) => $record->is_active ? 'Deactivate' : 'Activate')
    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
    ->icon(fn (User $record) => $record->is_active ? 'x' : 'check')
    ->hidden(fn (User $record) => $record->trashed())
```

## Confirmation Modal

```php
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete this record?')
    ->modalDescription('This action cannot be undone.')
    ->modalIcon('trash', 'danger')
    ->modalSubmitActionLabel('Yes, delete')
    ->modalCancelActionLabel('Cancel')
    ->action(fn ($record) => $record->delete());
```

## Slide-Over

```php
Action::make('details')
    ->slideOver()
    ->stickyHeader()
    ->stickyFooter()
    ->modalMaxHeight('60vh');
```

## Modal Appearance

```php
Action::make('edit')
    ->modalWidth('2xl')              // sm, md, lg, xl, 2xl, 3xl, 4xl, 5xl
    ->closeModalOnClickAway()
    ->closeModalOnEscape()
    ->slideOverOnMobile()            // slide-over on mobile, modal on desktop
    ->fullScreenOnMobile();          // full screen on mobile
```

## Form Modal

When `wire-forms` is installed, actions can display form modals:

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;

Action::make('edit')
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')->options([
            'admin' => 'Admin',
            'editor' => 'Editor',
        ]),
    ])
    ->fillFormUsing(fn ($record) => $record->only(['name', 'role']))
    ->action(fn ($record, array $data) => $record->update($data));
```

A `HeaderAction` form modal has **no record**, so its `fillFormUsing` closure takes no arguments. Use it to seed initial state — and always seed array-typed fields (`CheckboxList`, `Tags`, multiple `Select`) with an empty array so they bind correctly from the first interaction:

```php
HeaderAction::make('create')
    ->form([
        TextInput::make('name')->required(),
        CheckboxList::make('permissions')->options($permissions)->bulkToggleable(),
    ])
    ->fillFormUsing(fn () => ['name' => '', 'permissions' => []])
    ->action(fn (array $data) => Role::create($data));
```

## Infolist Modal

Use `->infolist()` to open a **read-only** modal that displays the record — the counterpart of `->form()`. The action's record is bound automatically, the modal is not a confirmation, and it shows only a close button (no submit). See [Infolists](infolists.md) for the full entry reference.

```php
use NyonCode\WireCore\Actions\ViewAction;
use NyonCode\WireCore\Infolists\Components\TextEntry;

ViewAction::make()
    ->slideOver()
    ->infolist([
        TextEntry::make('name')->weight('bold'),
        TextEntry::make('email')->copyable(),
        TextEntry::make('created_at')->dateTime()->since(),
    ]);
```

## Multi-Step Wizard

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('create')
    ->steps([
        ModalStep::make('Basic Info')
            ->description('Enter user details')
            ->icon('user')
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ]),

        ModalStep::make('Settings')
            ->schema([
                Select::make('role')->options([...]),
                Toggle::make('active'),
            ]),

        ModalStep::make('Review')
            ->schema([
                Placeholder::make('summary'),
            ]),
    ])
    ->action(fn ($record, $data) => $record->update($data));
```

A step's `->schema()` accepts a Closure to build its fields from data entered in
earlier steps — `->schema(fn (array $data) => [...])`. The Closure receives the live
form-data bag even for `HeaderAction` (which has no record). See
[Multi-Step Wizard](modals.md#multi-step-wizard) for a worked example.

## Footer Actions

```php
use NyonCode\WireCore\Actions\ModalFooterAction;

Action::make('edit')
    ->form([...])
    ->modalFooterActions([
        ModalFooterAction::make('save')
            ->label('Save')
            ->color('primary')
            ->submitsForm(),
        ModalFooterAction::make('save-and-close')
            ->label('Save & Close')
            ->action(fn () => $this->saveAndClose()),
    ]);
```

## Lifecycle Hooks

```php
Action::make('publish')
    ->before(fn ($record) => $record->validate())
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->after(fn ($record) => event(new Published($record)))
    ->successNotification('Published!')
    ->failureNotification('Publish failed.');
```

## Halt Execution

Halt pauses execution and shows a secondary modal for user confirmation:

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->modalHeading('Warnings Detected')
                ->modalDescription('There are unresolved warnings. Continue anyway?');
        }
    })
    ->action(fn ($record) => $record->process());
```

## Icon Button

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()          // renders as icon-only button
    ->tooltip('Edit record');

// Or hide just the label
Action::make('edit')
    ->icon('pencil')
    ->hideLabel();
```

## URL Actions

```php
Action::make('view')
    ->url(fn ($record) => route('users.show', $record), openInNewTab: true);

// String URL
Action::make('docs')
    ->url('/docs', openInNewTab: true);
```

## Keyboard Shortcuts

```php
Action::make('save')->keyboardShortcut('mod+s');
Action::make('delete')->keyboardShortcut('Delete');
```

Uses Alpine.js `@keydown` under the hood.

## Outlined & Sizing

```php
Action::make('cancel')
    ->outlined()                    // outline variant instead of solid
    ->color('gray')
    ->size('sm');                   // xs, sm, md, lg
```

## Extra Attributes

```php
Action::make('custom')
    ->extraAttributes([
        'data-testid' => 'custom-action',
        'x-on:click' => 'console.log("clicked")',
    ]);
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

## Blade Components

```blade
<x-wire-actions::button :action="$action" />
<x-wire-actions::group :group="$group" />
```
