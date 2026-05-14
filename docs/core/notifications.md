# Notifications

Pluggable notification system with multiple drivers. Lives inside `wire-core` as a separate module, prepared for future extraction ([ADR 0006](../decisions/0006-modular-core-extraction-strategy.md)).

## Drivers

| Driver | Class | Description | Requirements |
|--------|-------|-------------|--------------|
| Session | `SessionDriver` | Laravel `session()->flash()` | None (default) |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser events | Frontend listener |
| Flasher | `FlasherDriver` | [PHP Flasher](https://php-flasher.io) integration | `php-flasher/flasher-laravel` |
| Log | `LogDriver` | Logs notifications for debugging | None |
| Null | `NullDriver` | No-op (disables notifications) | None |

See [ADR 0004](../decisions/0004-notification-driver-defaults.md) for default driver selection.

## Notification Builder

```php
use NyonCode\WireCore\Notifications\Notification;

// Fluent builder
Notification::make()
    ->title('Record Saved')
    ->body('The user was successfully updated.')
    ->success()
    ->send();

// Shorthand factories
Notification::success('User saved');
Notification::error('Failed to delete');
Notification::warning('Disk space low');
Notification::info('3 new messages');

// Full customization
Notification::make()
    ->title('Custom')
    ->body('Detailed message...')
    ->icon('check')
    ->duration(5000)            // ms, 0 = persistent
    ->position('top-right')     // top-right, top-left, bottom-right, bottom-left
    ->extra(['link' => '/details'])
    ->send();
```

### Notification API

```php
->title(?string $title)
->body(?string $body)
->success()                      // color: success
->danger()                       // color: danger
->warning()                      // color: warning
->info()                         // color: info
->icon(?string $icon)
->duration(?int $ms)             // auto-dismiss time, 0 = persistent
->position(?string $position)    // toast position
->extra(array $data)             // arbitrary extra data
->send()                         // dispatch via active driver
->toArray(): array               // serialize to array
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

See [ADR 0010](../decisions/0010-form-save-notifications-integration.md).

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

## Module Dependencies

Notifications depends only on Foundation. It does not depend on Actions, Modals, or Forms ([ADR 0007](../decisions/0007-internal-module-dependencies.md)).
