---
order: 40
---

# Table Actions

Use actions for record-level operations, bulk operations, and toolbar commands.

## Action Types

| Type | Use for |
|------|---------|
| Row actions | One record at a time |
| Bulk actions | The currently selected records |
| Header actions | Global table commands |
| Action groups | Compact dropdowns for multiple row actions |

## Row Actions

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;

->actions([
    Action::make('edit')
        ->label('Edit')
        ->icon('pencil')
        ->url(fn (User $record) => route('users.edit', $record)),

    DeleteAction::make(),
])
```

Use a row action when the user is working with a single record and the intent is obvious from the row context.

### Execute PHP logic

```php
Action::make('activate')
    ->label('Activate')
    ->color('success')
    ->action(function (User $record) {
        $record->update(['active' => true]);
    })
```

### Open a URL

```php
Action::make('view')
    ->icon('eye')
    ->url(fn (User $record) => route('users.show', $record), openInNewTab: true)
```

### Icon-only actions

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()
    ->tooltip('Edit')
```

## Bulk Actions

Bulk actions appear when the table has selectable rows.

```php
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

->bulkActions([
    BulkAction::make('export')
        ->label('Export selected')
        ->icon('download')
        ->action(fn (array $records) => $this->exportUsers($records)),

    DeleteBulkAction::make(),
])
```

Use bulk actions for destructive or repetitive operations that should not be repeated row by row.

## Header Actions

Header actions live above the table and are not tied to a specific record.

```php
use NyonCode\WireCore\Actions\HeaderAction;

->headerActions([
    HeaderAction::make('create')
        ->label('New user')
        ->icon('plus')
        ->url(route('users.create')),

    HeaderAction::make('export')
        ->label('Export all')
        ->icon('download')
        ->action(fn () => $this->exportAll()),
])
```

## Confirmation Modals

Require confirmation for destructive or high-impact actions.

```php
Action::make('delete')
    ->color('danger')
    ->requiresConfirmation()
    ->modalHeading('Delete user')
    ->modalDescription('This action cannot be undone.')
    ->action(fn (User $record) => $record->delete())
```

## Form Actions

Attach a Wire Form schema to an action when the user must provide input before execution.

```php
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;

Action::make('edit')
    ->form([
        TextInput::make('name')->required(),
        Select::make('role')
            ->options([
                'admin' => 'Admin',
                'editor' => 'Editor',
                'viewer' => 'Viewer',
            ])
            ->required(),
    ])
    ->fillFormUsing(fn (User $record) => [
        'name' => $record->name,
        'role' => $record->role,
    ])
    ->action(function (User $record, array $data) {
        $record->update($data);
    })
```

For the full form API, see [Forms Overview](../forms/overview.md) and [Form Fields](../forms/fields/index.md).

## Visibility, State, and Permissions

All action types support conditional visibility and authorization.

```php
Action::make('approve')
    ->visible(fn (User $record) => $record->status === 'pending')
    ->disabled(fn (User $record) => $record->is_locked)
    ->permission('approve-users')
```

Keep the UI honest: hide actions users should not see, disable actions they can see but cannot use yet.

## Action Groups

Use action groups when you have too many row actions for a single row.

```php
use NyonCode\WireCore\Actions\ActionGroup;

->actions([
    ActionGroup::make([
        Action::make('view')->icon('eye')->url(fn (User $record) => route('users.show', $record)),
        Action::make('edit')->icon('pencil')->url(fn (User $record) => route('users.edit', $record)),
        Action::divider(),
        DeleteAction::make(),
    ])->tooltip('More actions'),
])
```

## Related Docs

- [Table Overview](overview.md)
- [Columns](columns/index.md)
- [Filters](filters/index.md)
- [Notifications](notifications.md)
