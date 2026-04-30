# Actions

The Actions module provides a complete action system for row, bulk, and header actions. It lives inside `wire-core` as a separate module, prepared for future extraction (see [ADR 0006](../decisions/0006-modular-core-extraction-strategy.md)).

## Action Types

| Class | Description |
|-------|-------------|
| `Action` | Row action (operates on a single record) |
| `BulkAction` | Bulk action (operates on selected records) |
| `HeaderAction` | Header action (operates without record context) |
| `ActionGroup` | Groups actions into a dropdown menu |

## Pre-built Actions

| Class | Description |
|-------|-------------|
| `DeleteAction` | Single record delete with confirmation |
| `DeleteBulkAction` | Bulk delete with confirmation |
| `EditAction` | Opens edit modal/form |
| `ViewAction` | Opens view modal |

## Usage

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\ActionGroup;

$table->actions([
    Action::make('edit')
        ->label('Edit')
        ->icon('pencil')
        ->color('primary')
        ->url(fn ($record) => route('users.edit', $record)),

    ActionGroup::make('More', [
        Action::make('duplicate')
            ->icon('copy')
            ->action(fn ($record) => $record->replicate()->save()),
        DeleteAction::make(),
    ]),
]);
```

## Dynamic Properties

All properties support Closures for per-record customization:

```php
Action::make('toggle')
    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
    ->icon(fn ($record) => $record->is_active ? 'x' : 'check');
```

## Confirmation Modal

```php
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete record?')
    ->modalDescription('This cannot be undone.')
    ->modalIcon('trash', 'danger')
    ->action(fn ($record) => $record->delete());
```

## Form Modal

When `wire-forms` is installed, actions can display form modals (see [ADR 0001](../decisions/0001-action-form-integration.md)):

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;

Action::make('edit')
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')->options([...]),
    ])
    ->fillFormUsing(fn ($record) => $record->only(['name', 'role']))
    ->action(fn ($record, array $data) => $record->update($data));
```

## Multi-Step Wizard

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('wizard')
    ->steps([
        ModalStep::make('basics')
            ->label('Basic Info')
            ->fields([
                TextInput::make('name')->required(),
            ]),
        ModalStep::make('settings')
            ->label('Settings')
            ->fields([
                Toggle::make('active'),
            ]),
    ])
    ->action(fn ($record, $data) => $record->update($data));
```

## Lifecycle Hooks

```php
Action::make('publish')
    ->before(fn ($record) => $record->validate())
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->after(fn ($record) => event(new Published($record)))
    ->successNotification('Published!')
    ->failureNotification('Failed.');
```

## Halt Execution

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->modalHeading('Warnings Detected')
                ->modalDescription('Continue anyway?');
        }
    })
    ->action(fn ($record) => $record->process());
```

## Keyboard Shortcuts

```php
Action::make('save')->keyboardShortcut('mod+s');
Action::make('delete')->keyboardShortcut('Delete');
```

## Blade Components

```blade
<x-wire-actions::button :action="$action" />
<x-wire-actions::group :group="$group" />
```

## Module Dependencies

Actions depends only on Foundation. Cross-module communication with Notifications uses service container resolution (see [ADR 0007](../decisions/0007-internal-module-dependencies.md)).
