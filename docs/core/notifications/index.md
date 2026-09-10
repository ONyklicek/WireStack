---
order: 10
summary: "One notification object, several drivers, and the fluent builder that describes what to say before anything decides where to say it."
---

# Notifications

A notification is a value object first and a delivery second: you describe what
happened — a title, a body, a colour, an icon, maybe actions — and a **driver**
decides where that lands. Swapping the driver changes nothing about the calling
code, which is what lets the same line be a toast in a browser and a row in a
database.

## Drivers

| Driver | Class | Delivery | Requirements |
|--------|-------|----------|--------------|
| Current component | `CurrentComponentDriver` | Decorator — resolves the active Livewire component via `Livewire::current()`, then delegates to a wrapped driver (`SessionDriver` by default) | None (default) |
| Session | `SessionDriver` | `session()->flash()` **+** a Livewire event carrying the **full** payload | None |
| Livewire | `LivewireEventDriver` | Livewire `$dispatch()` browser event with the **full** payload | Frontend listener (toast container) |
| Flasher | `FlasherDriver` | [PHP Flasher](https://php-flasher.io) integration | `php-flasher/flasher-laravel` |
| Database | `DatabaseDriver` | Writes the notification down; it survives the request that raised it | A `wire_notifications` table (migration ships with the package) |
| Broadcast | `BroadcastDriver` | Tells the recipient's **other open pages** that one arrived — a nudge over websockets, carrying no payload | Laravel broadcasting, and `window.Echo` on the page |
| Stack | `StackDriver` | Several drivers at once — the ordinary "toast **and** bell" pairing | Whatever the wrapped drivers need |
| Null | `NullDriver` | No-op — discards everything | None |

The built-in default is **`CurrentComponentDriver`** wrapping `SessionDriver`: it resolves the currently rendering Livewire component itself, so call-sites never have to pass `$this`. Both `SessionDriver` and `LivewireEventDriver` forward the **full** payload (`title`, `duration`, `icon`, `actions`, …), so rich toasts survive the server round-trip.

### Which driver for what?

| Use this driver when… | Driver |
|-----------------------|--------|
| You want zero-setup feedback that **survives redirects / full page loads** (flash), with a basic live toast as a bonus — good default for server-rendered and redirect-after-action flows. | `SessionDriver` |
| Your UI is the **toast container** and you want **rich, instant toasts** (title, duration, icon) without a reload. Recommended pairing with `<x-wire-notifications::toast-container />`. | `LivewireEventDriver` |
| Your app already uses **php-flasher** (Toastr / Notyf / SweetAlert adapters) and you want notifications to flow into that existing UI. | `FlasherDriver` |
| A **queued job** finishes and the user is looking at another tab, or another device. Pair it with `database` — on its own it announces something that was never stored. | `BroadcastDriver` |
| You want to **disable notifications** — tests, queued/background jobs, or any context with no user to notify. | `NullDriver` |

> **Which drivers feed the toast container?** `<x-wire-notifications::toast-container />` listens for a Livewire browser event, so event-dispatching drivers reach it: the default **`CurrentComponentDriver`**, **`SessionDriver`**, and **`LivewireEventDriver`** — all forward the full `title`/`duration`/`icon`/`actions` payload. `FlasherDriver` renders its **own** UI and bypasses the container; `NullDriver` shows nothing.
>
> It also renders what it finds **flashed**, which is the same notification arriving by the other road. See [Toasts that outlive the page](toasts.md#toasts-that-outlive-the-page) — that is what makes an action's `successRedirect()`, or a create page landing on the record it just filed, announce itself at all.

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

Static factories — each returns a new `Notification`:

```php
Notification::make(string $type, string $message): static
Notification::success(string $message): static
Notification::error(string $message): static
Notification::warning(string $message): static
Notification::info(string $message): static
```

Fluent modifiers. The object is immutable, so each returns a **new** instance:

```php
->title(?string $title): static
->duration(?int $ms): static      // auto-dismiss time, 0 = persistent
->persistent(bool $on = true): static   // sticky toast: duration 0, no countdown bar
->icon(?string $icon): static
->position(?string $position): static
->extra(array $data): static      // arbitrary extra data (merged)
->url(?string $url): static       // where it points — the invoice, not the notification
->to(?Model $notifiable): static  // who it is for; overrides ResolvesNotifiable
->action(NotificationAction|string $action, ?string $event = null): static
->actions(array $actions): static      // replace the action button set
->toArray(): array                // the payload only — never the recipient
```

Action buttons. A **link** survives being stored; an **event** needs its listener
to be on the page, which a notification opened three days later has no reason to
expect:

```php
NotificationAction::make(string $label, string $event): static
NotificationAction::link(string $label, string $url): static
->payload(array $payload): static      // sent with the dispatched event
->url(?string $url): static            // go here instead of dispatching
->color(?string $color): static        // button accent; falls back to the type
->keepOpen(bool $keepOpen = true): static   // don't dismiss the toast on click
```

Sending. `$livewire` is optional — the default `CurrentComponentDriver` resolves
the active component itself:

```php
NotificationManager::send(Notification $n, ?NotificationDriver $driver = null, mixed $livewire = null): void
NotificationManager::sendTo(Model $notifiable, Notification $n, ...): void
NotificationManager::success(string $message, ...): void
NotificationManager::error(string $message, ...): void
NotificationManager::warning(string $message, ...): void
NotificationManager::info(string $message, ...): void
```

## Configuration

```php
// config/wire-core.php
return [
    'notifications' => [
        // session, livewire, flasher, database, broadcast, null — or a list
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),
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

## In This Section

| Page | What it covers |
| --- | --- |
| [Toasts](toasts.md) | The on-screen driver, its Blade component, and firing one from JavaScript |
| [Persistent Notifications](persistent.md) | The stored kind — the bell, the panel, the read state |
| [Where To Send From](usage.md) | Actions, plain components and forms |
| [Custom Drivers](custom-drivers.md) | Delivering somewhere this package does not know about |

## Related

- [The Notifications Module](../../modules/notifications.md) — the ready-made history screen
- [Actions](../actions/index.md) — what usually raises one
- [Colors](../foundation/colors.md) and [Icons](../foundation/icons.md) — the vocabulary a notification speaks
- [Configuration](../../start/configuration.md) — the notification block in full
