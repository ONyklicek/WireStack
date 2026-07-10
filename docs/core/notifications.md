---
order: 30
---

# Notifications

Pluggable notification system with multiple drivers.

## Drivers

| Driver | Class | Delivery | Requirements |
|--------|-------|----------|--------------|
| Current component | `CurrentComponentDriver` | Decorator — resolves the active Livewire component via `Livewire::current()`, then delegates to a wrapped driver (`SessionDriver` by default) | None (default) |
| Session | `SessionDriver` | `session()->flash()` **+** a Livewire event carrying the **full** payload | None |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser event with the **full** payload | Frontend listener (toast container) |
| Flasher | `FlasherDriver` | [PHP Flasher](https://php-flasher.io) integration | `php-flasher/flasher-laravel` |
| Null | `NullDriver` | No-op — discards everything | None |

The built-in default is **`CurrentComponentDriver`** wrapping `SessionDriver`: it resolves the currently rendering Livewire component itself, so call-sites never have to pass `$this`. Both `SessionDriver` and `LivewireEventDriver` forward the **full** payload (`title`, `duration`, `icon`, `actions`, …), so rich toasts survive the server round-trip.

### Which driver for what?

| Use this driver when… | Driver |
|-----------------------|--------|
| You want zero-setup feedback that **survives redirects / full page loads** (flash), with a basic live toast as a bonus — good default for server-rendered and redirect-after-action flows. | `SessionDriver` |
| Your UI is the **toast container** and you want **rich, instant toasts** (title, duration, icon) without a reload. Recommended pairing with `<x-wire-notifications::toast-container />`. | `LivewireEventDriver` |
| Your app already uses **php-flasher** (Toastr / Notyf / SweetAlert adapters) and you want notifications to flow into that existing UI. | `FlasherDriver` |
| You want to **disable notifications** — tests, queued/background jobs, or any context with no user to notify. | `NullDriver` |

> **Which drivers feed the toast container?** `<x-wire-notifications::toast-container />` is an Alpine listener on a Livewire browser event, so only event-dispatching drivers reach it: the default **`CurrentComponentDriver`**, **`SessionDriver`**, and **`LivewireEventDriver`** — all forward the full `title`/`duration`/`icon`/`actions` payload. `FlasherDriver` renders its **own** UI and bypasses the container; `NullDriver` shows nothing.

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
->persistent(bool $on = true): static   // sticky toast: duration 0, no countdown bar
->icon(?string $icon): static
->position(?string $position): static
->extra(array $data): static      // arbitrary extra data (merged)
->action(NotificationAction|string $action, ?string $event = null): static  // append an action button
->actions(array $actions): static      // replace the action button set
->toArray(): array                // serialize to array

// Sending (via NotificationManager)
// $livewire is optional — the default CurrentComponentDriver resolves the
// active component itself, so you normally omit it.
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
3. **Fallback:** built-in `CurrentComponentDriver` wrapping `SessionDriver`

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

You can customize the position, the fallback auto-dismiss duration, and the browser event it listens for:

```blade
<x-wire-notifications::toast-container
    position="bottom-right"
    :duration="5000"
    event-name="table-notification" />
```

| Prop | Default | Purpose |
|------|---------|---------|
| `position` | `top-right` | `top-left` / `top-center` / `top-right` / `bottom-left` / `bottom-center` / `bottom-right` |
| `duration` | `4000` | fallback auto-dismiss (ms) for notifications without their own `duration` |
| `event-name` | `table-notification` | the `window` event it listens for (`x-on:{eventName}.window`) |
| `progress` | `true` | show the per-toast countdown bar (see below) |
| `stack` | `false` | collapse toasts into a pile that fans out on hover |
| `max` | `0` | cap the number of visible toasts (`0` = unlimited); the overflow collapses into a “+N more” pill |

## Toasts

Everything below is rendered by `<x-wire-notifications::toast-container />` — the drivers only dispatch payloads; the container decides how a toast looks and behaves.

### Countdown bar

Each auto-dismissing toast shows a thin **countdown bar** along its bottom edge that depletes as the toast ages, so users can see how long until it closes. **Hovering any toast pauses the bar and the auto-dismiss** (and resumes on leave). The bar is on by default and colored by the notification type.

- It is **optional** — pass `:progress="false"` to hide it.
- **Persistent toasts have no bar** — a sticky toast never counts down, so there is nothing to show (see below).

```blade
<x-wire-notifications::toast-container :progress="false" />  {{-- no countdown bar --}}
```

### Persistent toasts

Call `->persistent()` (or `->duration(0)`) to make a toast **sticky**: it stays until the user dismisses it and shows no countdown bar. Ideal for messages that require a decision.

```php
NotificationManager::send(
    Notification::warning('Payment needs review before it can settle.')
        ->title('Action required')
        ->persistent()
);
```

### Action buttons

Add buttons that dispatch a Livewire event on click — the "Undo" affordance. Your host component listens with `#[On(...)]`.

```php
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationAction;

// shorthand: label + Livewire event
NotificationManager::send(
    Notification::success('Item deleted')->action('Undo', 'restore-record')
);

// full control
NotificationManager::send(
    Notification::success('Order #1042 saved')->action(
        NotificationAction::make('Undo', 'restore-record')
            ->payload(['id' => 1042])   // sent with the dispatched event
            ->color('primary')          // button accent (falls back to the toast type)
            ->keepOpen()                // don't dismiss the toast after clicking
    )
);
```

```php
// in the host Livewire component
#[On('restore-record')]
public function restore(int $id): void
{
    // …
}
```

`NotificationAction` is an immutable value object: `make(label, event)`, `->payload([...])`, `->color(...)`, `->keepOpen()`. Clicking dispatches `Livewire.dispatch(event, payload)` and (unless `keepOpen()`) closes the toast.

### Stacking & overflow

- **`stack`** collapses toasts into a tidy pile; hovering the pile fans them out into the full list. The newest toast sits closest to the anchor edge.
- **`max`** caps how many are visible at once; extras collapse into a clickable **“+N more”** pill that reveals the rest.

```blade
<x-wire-notifications::toast-container stack :max="5" />
```

### Accessibility

The container is an `aria-live="polite"` region (error toasts use `role="alert"`), so screen readers announce toasts as they arrive. It also honors **`prefers-reduced-motion`**: when reduced motion is requested, the stack never collapses/fans out and card transitions are disabled.

## Triggering Toasts from JavaScript

The toast container installs a global `window.wireToast` helper (and an Alpine `$toast` magic) when it mounts, so you can pop a toast straight from the frontend — no server round-trip. The helper simply dispatches the container's `eventName` window event with the standard payload (`type`, `message`, `title`, `duration`).

```js
// shorthand — type + message
wireToast.success('Saved');
wireToast.error('Something went wrong');
wireToast.warning('Careful');
wireToast.info('Heads up');

// with options (title, duration, …)
wireToast.success('Saved', { title: 'Done', duration: 6000 });

// full payload object (type defaults to 'info' if omitted)
wireToast({ type: 'success', message: 'Saved', title: 'Done' });
wireToast('Plain info toast');
```

Inside Alpine, use the `$toast` magic:

```blade
<button @click="$toast.success('Copied!')">Copy</button>
```

The helper targets the container's configured `eventName`, so a custom `event-name="my-toast"` is wired up automatically. `window.wireToast` is installed once (the first container wins); if you render multiple containers with different event names, dispatch the `CustomEvent` yourself for the secondary ones:

```js
window.dispatchEvent(new CustomEvent('my-toast', {
    detail: { type: 'success', message: 'Saved' },
}));
```
