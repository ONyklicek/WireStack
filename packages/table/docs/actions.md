# Actions

WireTable supports three types of actions: **row actions**, **bulk actions**, and **header actions**, plus **action groups** for dropdown menus.

## Row Actions

Actions that operate on a single record.

```php
use NyonCode\WireCore\Actions\Action;

$table->actions([
    Action::make('edit')
        ->label('Edit')
        ->icon('pencil')
        ->color('primary')
        ->action(fn (Model $record) => $this->editRecord($record)),
])
```

### URL Actions

```php
Action::make('view')
    ->label('View')
    ->icon('eye')
    ->url(fn (Model $record) => route('users.show', $record))
    ->url('/users/{id}', openInNewTab: true)
```

### Icon Buttons

```php
Action::make('edit')
    ->icon('pencil')
    ->iconButton()           // Render as icon-only button
    ->tooltip('Edit record') // Hover text

Action::make('delete')
    ->icon('trash')
    ->hideLabel()            // Same as iconButton for inline display
    ->onlyIcon()             // Alias
```

### Dynamic Properties

All properties support Closures for per-record customization:

```php
Action::make('toggle')
    ->label(fn (Model $record) => $record->is_active ? 'Deactivate' : 'Activate')
    ->color(fn (Model $record) => $record->is_active ? 'danger' : 'success')
    ->icon(fn (Model $record) => $record->is_active ? 'x' : 'check')
    ->tooltip(fn (Model $record) => "Currently: " . ($record->is_active ? 'active' : 'inactive'))
    ->size('sm')             // xs, sm, md, lg
    ->extraAttributes(['data-testid' => 'toggle-btn'])
```

### Outlined Style

```php
Action::make('export')
    ->label('Export')
    ->outlined()
```

---

## Bulk Actions

Actions that operate on multiple selected records.

```php
use NyonCode\WireCore\Actions\BulkAction;

$table->bulkActions([
    BulkAction::make('delete')
        ->label('Delete Selected')
        ->icon('trash')
        ->color('danger')
        ->requiresConfirmation()
        ->deselectRecordsAfterCompletion()
        ->action(fn (array $records) => User::whereIn('id', $records)->delete()),

    BulkAction::make('export')
        ->label('Export Selected')
        ->icon('download')
        ->action(fn (array $records) => $this->exportRecords($records)),
])
```

When bulk actions are defined, row selection checkboxes are automatically enabled.

---

## Header Actions

Actions displayed in the table header area.

```php
use NyonCode\WireCore\Actions\HeaderAction;

$table->headerActions([
    HeaderAction::make('create')
        ->label('New User')
        ->icon('plus')
        ->color('primary')
        ->url(route('users.create')),

    HeaderAction::make('export')
        ->label('Export')
        ->icon('download')
        ->action(fn () => $this->exportAll()),

    // With badge count
    HeaderAction::make('pending')
        ->label('Pending Review')
        ->badge(fn () => User::where('status', 'pending')->count())
        ->badgeColor('warning')
        ->url(route('users.pending')),
])
```

---

## Action Groups

Group multiple actions into a dropdown menu.

```php
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\Action;

$table->actions([
    ActionGroup::make([
        Action::make('view')
            ->label('View')
            ->icon('eye')
            ->url(fn (Model $record) => route('users.show', $record)),

        Action::make('edit')
            ->label('Edit')
            ->icon('pencil')
            ->action(fn (Model $record) => $this->edit($record)),

        Action::divider(),   // Visual separator

        Action::make('delete')
            ->label('Delete')
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (Model $record) => $record->delete()),
    ])
    ->label('Actions')
    ->icon('dots-vertical')  // Trigger icon (default)
    ->color('gray')
    ->tooltip('More actions')
    ->divided()              // Auto-insert dividers between actions
    ->dropdownPosition('bottom-end')   // bottom-end, bottom-start, top-end, top-start
    ->dropdownWidth('w-48')
    ->badge(fn (Model $record) => $record->notifications_count)
    ->badgeColor('danger')
])
```

---

## Prebuilt Actions

### ViewAction

```php
use NyonCode\WireCore\Actions\ViewAction;

ViewAction::make()  // Pre-configured view action with eye icon
```

### EditAction

```php
use NyonCode\WireCore\Actions\EditAction;

EditAction::make()  // Pre-configured edit action with pencil icon
```

### DeleteAction

```php
use NyonCode\WireCore\Actions\DeleteAction;

DeleteAction::make()  // Pre-configured delete action with trash icon and confirmation
```

### DeleteBulkAction

```php
use NyonCode\WireCore\Actions\DeleteBulkAction;

DeleteBulkAction::make()  // Pre-configured bulk delete with confirmation
```

---

## Visibility and Permissions

```php
Action::make('admin-action')
    ->visible(fn (Model $record) => auth()->user()->isAdmin())
    ->hidden(fn (Model $record) => $record->is_archived)
    ->disabled(fn (Model $record) => $record->is_locked)
    ->permission('edit-users')         // Laravel permission check
```

The `canExecute()` method checks visibility, disabled state, and permissions. Users with a `Super Admin` role bypass permission checks.

---

## Confirmation Modal

```php
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete Record')
    ->modalDescription('Are you sure? This action cannot be undone.')
    ->modalIcon('exclamation', 'danger')
    ->modalSubmitActionLabel('Yes, delete')
    ->modalCancelActionLabel('Cancel')
    ->modalWidth('md')                 // sm, md, lg, xl
    ->closeModalOnClickAway()
    ->closeModalOnEscape()
    ->action(fn (Model $record) => $record->delete())
```

---

## Form Modal

Attach a form to an action modal. See [Forms](forms.md) for all available field types.

```php
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Select;

Action::make('edit')
    ->form([
        TextInput::make('name')
            ->label('Name')
            ->required()
            ->rules(['string', 'max:255']),

        Select::make('role')
            ->label('Role')
            ->options(['admin' => 'Admin', 'user' => 'User'])
            ->required(),
    ])
    ->fillFormUsing(fn (Model $record) => [
        'name' => $record->name,
        'role' => $record->role,
    ])
    ->formValidation(['name' => 'required|string|max:255'])
    ->validationMessages(['name.required' => 'Name is required.'])
    ->action(fn (Model $record, array $data) => $record->update($data))
```

### Multi-Step Modal

```php
use NyonCode\WireCore\Actions\ModalStep;

Action::make('wizard')
    ->steps([
        ModalStep::make('basics')
            ->label('Basic Info')
            ->description('Enter basic details')
            ->icon('user')
            ->fields([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required(),
            ]),

        ModalStep::make('settings')
            ->label('Settings')
            ->fields([
                Select::make('role')->options([...]),
                Toggle::make('notifications'),
            ]),
    ])
    ->action(fn (Model $record, array $data) => $record->update($data))
```

### Modal Appearance

```php
Action::make('details')
    ->slideOver()                      // Slide-over panel from the right
    ->slideOverOnMobile()              // Slide-over only on mobile
    ->fullScreenOnMobile()             // Full screen on mobile
    ->stickyHeader()                   // Fixed header on scroll
    ->stickyFooter()                   // Fixed footer on scroll
    ->modalMaxHeight('60vh')           // Scrollable body
    ->modalFooterActions([...])        // Extra footer buttons
    ->modalHeaderActions([...])        // Header action buttons
```

---

## Lifecycle Hooks

```php
Action::make('publish')
    ->before(fn (Model $record) => $record->validate())
    ->action(fn (Model $record) => $record->update(['status' => 'published']))
    ->after(fn (Model $record) => event(new RecordPublished($record)))
    ->successNotification('Record published successfully.')
    ->failureNotification('Failed to publish record.')
    ->successRedirect(fn (Model $record) => route('records.show', $record))
```

### Halt Execution

Use `halt()` in a before hook to interrupt action execution and show a secondary confirmation:

```php
Action::make('process')
    ->before(function (Model $record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->modalHeading('Warnings Detected')
                ->modalDescription('This record has warnings. Continue anyway?')
                ->modalIcon('warning', 'warning');
        }
    })
    ->action(fn (Model $record) => $record->process())
```

---

## Notifications

```php
use NyonCode\WireCore\Notifications\TableNotification;

Action::make('save')
    ->action(function (Model $record, Action $action) {
        $record->save();
        $action->sendSuccessNotification();
    })
    ->successNotification('Saved!')

// Custom notification
Action::make('process')
    ->action(function (Model $record, Action $action) {
        try {
            $record->process();
            $action->sendNotification(
                TableNotification::success('Processed')
                    ->title('Done')
                    ->duration(3000)
                    ->icon('check')
            );
        } catch (\Exception $e) {
            $action->sendFailureNotification();
        }
    })
    ->failureNotification('Processing failed.')
```

---

## Loading State

```php
Action::make('export')
    ->loadingIndicator()               // Show spinner during execution
    ->loadingIndicator('Exporting...') // Custom loading text
    ->debounce(300)                    // Prevent double-clicks (milliseconds)
    ->timeout(30)                      // Action timeout (seconds)
```

---

## Keyboard Shortcuts

```php
Action::make('save')
    ->keyboardShortcut('mod+s')        // Ctrl+S (Win/Linux) or Cmd+S (Mac)

Action::make('delete')
    ->keyboardShortcut('Delete')

Action::make('new')
    ->keyboardShortcut('ctrl+shift+n', 'Ctrl+Shift+N')  // Custom label
```

Supported modifiers: `mod` (platform-aware), `ctrl`, `shift`, `alt`, `meta`. Single keys: `Delete`, `Enter`, `Escape`, `F1`-`F12`.
