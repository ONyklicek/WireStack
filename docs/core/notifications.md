---
order: 30
---

# Notifications

Pluggable notification system with multiple drivers.

## Drivers

| Driver | Class | Description | Requirements |
|--------|-------|-------------|--------------|
| Session | `SessionDriver` | Laravel `session()->flash()` | None (default) |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser events | Frontend listener |
| Flasher | `FlasherDriver` | [PHP Flasher](https://php-flasher.io) integration | `php-flasher/flasher-laravel` |
| Log | `LogDriver` | Logs notifications for debugging | None |
| Null | `NullDriver` | No-op (disables notifications) | None |

The session driver is the default.

## Notification Builder

`Notification` is an immutable value object. Create via static factory, then send through `NotificationManager`.

```php
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;

// Shorthand factories — create and send immediately
NotificationManager::success('User saved');
NotificationManager::error('Failed to delete');

// Build a notification, then send
$notification = Notification::success('The user was successfully updated.')
    ->title('Record Saved');

NotificationManager::send($notification);

// Full customization
$notification = Notification::make('success', 'Changes saved.')
    ->title('Done')
    ->icon('check')
    ->duration(5000)            // ms, 0 = persistent
    ->position('top-right')     // top-right, top-left, bottom-right, bottom-left
    ->extra(['link' => '/details']);

NotificationManager::send($notification);
```

### Notification API

```php
// Static factories (return a new Notification instance)
Notification::make(string $type, string $message): static
Notification::success(string $message): static
Notification::error(string $message): static
Notification::warning(string $message): static
Notification::info(string $message): static

// Fluent immutable modifiers (each returns a new instance)
->title(?string $title): static
->duration(?int $ms): static      // auto-dismiss time, 0 = persistent
->icon(?string $icon): static
->position(?string $position): static
->extra(array $data): static      // arbitrary extra data (merged)
->toArray(): array                // serialize to array

// Sending (via NotificationManager)
NotificationManager::send(Notification $n, ?NotificationDriver $driver = null, mixed $livewire = null): void
NotificationManager::success(string $message, ...): void
NotificationManager::error(string $message, ...): void
NotificationManager::warning(string $message, ...): void
NotificationManager::info(string $message, ...): void
```

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

## Configuration

```php
// config/wire-core.php
return [
    'notifications' => [
        'driver' => 'session', // session, livewire, flasher, log, null
    ],
];
```

### Driver Resolution Order

1. Per-table/per-component driver override
2. Global default (`NotificationManager::setDefaultDriver()`)
3. Config value (`wire-core.notifications.driver`)
4. Fallback: `SessionDriver`

## Custom Drivers

Implement the `NotificationDriver` contract:

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

class SlackDriver implements NotificationDriver
{
    public function send(Notification $notification): void
    {
        Http::post('https://hooks.slack.com/...', [
            'text' => $notification->title . ': ' . $notification->body,
        ]);
    }
}
```

Register in a service provider:

```php
use NyonCode\WireCore\Notifications\NotificationManager;

NotificationManager::registerDriver('slack', SlackDriver::class);
```

Then set in config:

```php
'notifications' => [
    'driver' => 'slack',
],
```

## Blade Component

Place the toast container in your layout:

```blade
<x-wire-notifications::toast-container />
```
