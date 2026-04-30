# Notifications

Pluggable notification system with multiple drivers. Lives inside `wire-core` as a separate module, prepared for future extraction (see [ADR 0006](../decisions/0006-modular-core-extraction-strategy.md)).

## Drivers

| Driver | Description | Requirements |
|--------|-------------|--------------|
| `SessionDriver` | Laravel `session()->flash()` | None (default) |
| `LivewireEventDriver` | Livewire `$dispatch()` browser events | Frontend listener |
| `FlasherDriver` | [PHP Flasher](https://php-flasher.io) integration | `php-flasher/flasher-laravel` |
| `NullDriver` | No-op (disables notifications) | None |

See [ADR 0004](../decisions/0004-notification-driver-defaults.md) for default driver selection.

## Usage

### In Actions
```php
use NyonCode\WireCore\Notifications\TableNotification;

Action::make('save')
    ->action(function ($record, Action $action) {
        $record->save();
        $action->sendSuccessNotification();
    })
    ->successNotification('Saved!');

// Custom notification
$action->sendNotification(
    TableNotification::success('Done')
        ->title('Processed')
        ->duration(3000)
        ->icon('check')
);
```

### In Components
```php
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;

class MyComponent extends Component
{
    use InteractsWithNotifications;

    public function save(): void
    {
        // ... save logic
        $this->notify('success', 'Record saved');
    }
}
```

### Notification Builder

```php
TableNotification::success('Message')
    ->title('Title')
    ->icon('check')
    ->duration(5000)    // milliseconds, 0 = persistent
    ->position('top-right');

TableNotification::danger('Error occurred')
    ->title('Error')
    ->icon('exclamation');
```

## Configuration

```php
// config/wire-core.php
return [
    'notifications' => [
        'driver' => 'session', // session, livewire, flasher, null
    ],
];
```

### Resolution Order
1. Per-table driver (`$table->notificationDriver()`)
2. Global default (`TableNotificationManager::setDefaultDriver()`)
3. Config value (`wire-core.notifications.driver`)
4. Fallback: `SessionDriver`

## Blade Component

```blade
<x-wire-notifications::toast-container />
```

Place this in your layout to display notifications.

## Custom Drivers

Implement the `NotificationDriver` contract:

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;

class CustomDriver implements NotificationDriver
{
    public function send(TableNotification $notification): void
    {
        // Your notification logic
    }
}
```

Register in a service provider:
```php
TableNotificationManager::registerDriver('custom', CustomDriver::class);
```
