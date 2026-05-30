---
order: 30
---

# Notifications

Pluggable notification system with multiple drivers.

## Drivers

| Driver | Class | Delivery | Requirements |
|--------|-------|----------|--------------|
| Session | `SessionDriver` | `session()->flash()` **+** a Livewire event (`type` + `message` only) | None (default) |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser event with the **full** payload | Frontend listener (toast container) |
| Flasher | `FlasherDriver` | [PHP Flasher](https://php-flasher.io) integration | `php-flasher/flasher-laravel` |
| Null | `NullDriver` | No-op — discards everything | None |

The session driver is the default.

### Which driver for what?

| Use this driver when… | Driver |
|-----------------------|--------|
| You want zero-setup feedback that **survives redirects / full page loads** (flash), with a basic live toast as a bonus — good default for server-rendered and redirect-after-action flows. | `SessionDriver` |
| Your UI is the **toast container** and you want **rich, instant toasts** (title, duration, icon) without a reload. Recommended pairing with `<x-wire-notifications::toast-container />`. | `LivewireEventDriver` |
| Your app already uses **php-flasher** (Toastr / Notyf / SweetAlert adapters) and you want notifications to flow into that existing UI. | `FlasherDriver` |
| You want to **disable notifications** — tests, queued/background jobs, or any context with no user to notify. | `NullDriver` |

> **Which drivers feed the toast container?** `<x-wire-notifications::toast-container />` is an Alpine listener on a Livewire browser event, so only event-dispatching drivers reach it: **`LivewireEventDriver`** (full `title`/`duration`/`icon`) and **`SessionDriver`** (only `type`+`message`; `title`/`duration` fall back to the container defaults). `FlasherDriver` renders its **own** UI and bypasses the container; `NullDriver` shows nothing.

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

## Configuration

```php
// config/wire-core.php
return [
    'notifications' => [
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'), // session, livewire, flasher, null
    ],
];
```

This config value drives the **container-bound** `NotificationDriver` (resolved by the service provider for constructor/`app()` injection).

### Driver Resolution Order

When you call `NotificationManager::send()` (or its shortcuts), the driver is resolved in this order:

1. **Explicit** driver passed to the call / component (`setNotificationDriver()`, the `$driver` argument)
2. **Global default** set via `NotificationManager::setDefaultDriver()`
3. **Fallback:** built-in `SessionDriver`

> **Note:** the static `NotificationManager` does **not** read `wire-core.notifications.default` on its own — that config only feeds the container binding. To make the configured driver the global default for the static API, bridge it once in a service provider:
>
> ```php
> use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
> use NyonCode\WireCore\Notifications\NotificationManager;
>
> NotificationManager::setDefaultDriver(app(NotificationDriver::class));
> ```

## Custom Drivers

Implement the `NotificationDriver` contract — its single `send()` method receives the notification and (optionally) the Livewire component in scope:

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

class SlackDriver implements NotificationDriver
{
    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        Http::post('https://hooks.slack.com/...', [
            'text' => $notification->title . ': ' . $notification->message,
        ]);
    }
}
```

Register it as the global default in a service provider (`boot()`):

```php
use NyonCode\WireCore\Notifications\NotificationManager;

NotificationManager::setDefaultDriver(new SlackDriver());
```

Or use it for a single component/call without changing the global default:

```php
$this->setNotificationDriver(new SlackDriver());      // per-component (trait)
NotificationManager::send($notification, new SlackDriver()); // per-call
```

## Blade Component

Place the toast container in your layout:

```blade
<x-wire-notifications::toast-container />
```
