# Notifications

WireTable includes a pluggable notification system for action feedback.

## TableNotification

Immutable value object representing a notification.

### Creating Notifications

```php
use NyonCode\WireCore\Notifications\TableNotification;

// Static factory methods
TableNotification::success('Record saved successfully.')
TableNotification::error('Failed to save record.')
TableNotification::warning('Record has validation warnings.')
TableNotification::info('Processing started.')

// Generic constructor
TableNotification::make('success', 'Record saved.')
```

### Customizing Notifications

All methods return a new instance (immutable):

```php
$notification = TableNotification::success('Saved!')
    ->title('Success')               // Optional title
    ->duration(5000)                 // Display duration in milliseconds
    ->icon('check')                  // Icon name
    ->position('top-right')          // Position on screen
    ->extra(['record_id' => 123]);   // Additional metadata
```

### Serialization

```php
$notification->toArray();
// Returns: ['type', 'message', 'title', 'duration', 'icon', 'position', 'extra']
```

### Readonly Properties

```php
$notification->type        // 'success', 'error', 'warning', 'info'
$notification->message     // The notification message
$notification->title       // Optional title
$notification->duration    // Duration in ms
$notification->icon        // Icon name
$notification->position    // Screen position
$notification->extra       // Additional data
```

---

## Sending Notifications from Actions

### Built-in Methods

```php
use NyonCode\WireCore\Actions\Action;

Action::make('save')
    ->successNotification('Saved successfully.')
    ->failureNotification('Save failed.')
    ->action(function (Model $record, Action $action) {
        try {
            $record->save();
            $action->sendSuccessNotification();
        } catch (\Exception $e) {
            $action->sendFailureNotification();
        }
    })
```

### Custom Notifications in Actions

```php
Action::make('process')
    ->action(function (Model $record, Action $action) {
        $result = $record->process();

        if ($result->hasWarnings()) {
            $action->sendWarningNotification('Completed with warnings.');
        } else {
            $action->sendInfoNotification('Processing complete.');
        }

        // Or send a fully custom notification
        $action->sendNotification(
            TableNotification::success('Processed')
                ->title('Done')
                ->duration(3000)
                ->icon('check')
        );
    })
```

---

## TableNotificationManager

Central static dispatcher for sending notifications from anywhere.

```php
use NyonCode\WireCore\Notifications\TableNotificationManager;

TableNotificationManager::success('Record created.');
TableNotificationManager::error('Something went wrong.');
TableNotificationManager::warning('Check your input.');
TableNotificationManager::info('Task queued.');

// Custom notification
TableNotificationManager::send(
    TableNotification::make('success', 'Custom notification')
);
```

---

## Notification Drivers

Drivers determine how notifications are delivered to the user.

### SessionDriver (Default)

Stores notifications in the session flash data. Works with standard Laravel flash messages.

```php
use NyonCode\WireCore\Notifications\Drivers\SessionDriver;

// Automatically used when no driver is configured
```

### LivewireEventDriver

Dispatches a Livewire browser event that can be caught by JavaScript.

```php
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;

// In config/wire-table.php:
'notification_driver' => LivewireEventDriver::class,

// Per-table override:
$table->notificationDriver(new LivewireEventDriver('my-toast-event'))
```

Listen for events in your JavaScript:

```js
Livewire.on('my-toast-event', (data) => {
    // Show toast notification using your preferred library
    showToast(data.type, data.message);
});
```

### FlasherDriver

Integrates with the [Flasher](https://php-flasher.io/) package for rich notifications.

```php
use NyonCode\WireCore\Notifications\Drivers\FlasherDriver;

// In config/wire-table.php:
'notification_driver' => FlasherDriver::class,

// Per-table with adapter:
$table->notificationDriver(new FlasherDriver('toastr'))
```

### Custom Driver

Implement the `NotificationDriver` interface:

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\TableNotification;

class CustomDriver implements NotificationDriver
{
    public function send(TableNotification $notification): void
    {
        // Your notification logic
    }
}

// Use it:
$table->notificationDriver(new CustomDriver())
```

---

## Configuration

In `config/wire-table.php`:

```php
'notification_driver' => null,  // null = SessionDriver (default)

// Or specify a driver class:
'notification_driver' => \NyonCode\WireTable\Notifications\Drivers\LivewireEventDriver::class,
'notification_driver' => \NyonCode\WireTable\Notifications\Drivers\FlasherDriver::class,
```

### Per-Table Driver

Override the global driver for a specific table:

```php
$table->notificationDriver(new LivewireEventDriver('custom-event'))
```
