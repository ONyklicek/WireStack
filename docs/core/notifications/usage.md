---
order: 40
summary: "The three call sites — inside an action, in a plain Livewire component, and around a form's save — and what each one already does for you."
---

# Where To Send From

Most notifications are not written by hand: an action reports its own success, a
form reports a save, and a table reports a bulk run. This page is what each of
those already does, and how to say something else instead from the same place.

## Usage in Actions

```php
Action::make('save')
    ->action(function ($record, Action $action) {
        $record->save();
        $action->sendSuccessNotification();
    })
    ->successNotification('Saved!');

// Custom notification from action
$action->sendNotification(
    Notification::success('Done')
        ->title('Processed')
        ->duration(3000)
        ->icon('check')
);
```

## Usage in Components

```php
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Notification;

class MyComponent extends Component
{
    use InteractsWithNotifications;

    public function save(): void
    {
        // ... save logic

        // Type shortcuts (take a message string)
        $this->notifySuccess('Record saved');
        $this->notifyError('Save failed');
        $this->notifyWarning('Careful');
        $this->notifyInfo('Heads up');

        // Or send a fully-built Notification
        $this->notify(
            Notification::success('Record saved')->title('Done')->duration(5000)
        );
    }
}
```

## Usage in Forms

Forms automatically send a success notification after `save()` unless disabled:

```php
Form::make()
    ->schema([...])
    ->model(User::class)
    ->successMessage('User saved!')          // custom message
    ->save();

// Disable
Form::make()
    ->schema([...])
    ->disableSuccessNotification()
    ->save();
```

## Related

- [Notifications](index.md) — the builder these call sites use
- [Actions: Lifecycle](../actions/lifecycle.md) — where an action's own message is raised
- [Save Lifecycle](../../forms/save-lifecycle.md) — the form's notify step
- [Table Notifications](../../table/notifications.md) — the table's defaults
