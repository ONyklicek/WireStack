# Notifications

Namespace: `NyonCode\WireCore\Notifications`
Owner package: `packages/core`

The notification subsystem turns a single immutable **value object** (`Notification`) into user-facing feedback through a **pluggable driver**. Producers (actions, Livewire components, the engine) only construct a `Notification` and hand it to a driver; how it actually reaches the screen — session flash, Livewire/Alpine toast, php-flasher — is the driver's concern.

```text
                       ┌─────────────────────────────┐
 producer ───────────▶ │  Notification (value object)│
 (action / component)  └──────────────┬──────────────┘
                                       │ NotificationManager::send($n, $driver, $component)
                                       ▼
                       ┌─────────────────────────────┐
                       │  resolve driver:            │
                       │  explicit > global > Session│
                       └──────────────┬──────────────┘
                                       ▼
        SessionDriver │ LivewireEventDriver │ FlasherDriver │ NullDriver │ <custom>
                                       ▼
        session flash  /  Livewire browser event  /  flasher  /  discard
                                       ▼
                       <x-wire-notifications::toast-container />  (Alpine renders the toast)
```

---

## Table of Contents

1. [The `Notification` value object](#the-notification-value-object)
2. [Notification types](#notification-types)
3. [`NotificationManager`](#notificationmanager)
4. [Driver resolution & the default-driver gotcha](#driver-resolution--the-default-driver-gotcha)
5. [Sending from Livewire components](#sending-from-livewire-components)
6. [Sending from actions](#sending-from-actions)
7. [Built-in drivers](#built-in-drivers)
8. [Writing a custom driver](#writing-a-custom-driver)
9. [Frontend: the toast container & event contract](#frontend-the-toast-container--event-contract)
10. [Configuration](#configuration)
11. [Testing](#testing)
12. [Deprecations](#deprecations)
13. [Gotchas & invariants](#gotchas--invariants)

---

## The `Notification` value object

`final class Notification` is **immutable**. The constructor is private; you build instances through factories, and every fluent modifier returns a *new* instance (it never mutates the receiver).

### Properties

| Property | Type | Notes |
|----------|------|-------|
| `type` | `string` | `success` / `error` / `warning` / `info` (free-form, but drivers/toast key off these four) |
| `message` | `string` | the body text (required) |
| `title` | `?string` | optional bold heading above the message |
| `duration` | `?int` | auto-dismiss in **milliseconds**; `null` → driver/UI default |
| `icon` | `?string` | resolved icon name; an `Icon` enum is reduced to its `value()` |
| `position` | `?string` | e.g. `top-right` (interpreted by the renderer) |
| `extra` | `array<string,mixed>` | arbitrary payload merged via `extra()` |

All properties are `public readonly`.

### Factories

```php
use NyonCode\WireCore\Notifications\Notification;

Notification::make('success', 'Saved');   // generic
Notification::success('Saved');
Notification::error('Something failed');
Notification::warning('Careful');
Notification::info('Heads up');
```

### Fluent modifiers (immutable)

```php
$n = Notification::success('User updated')
    ->title('Done')
    ->duration(5000)          // ms
    ->icon('check-circle')    // string | Icon | null  (Icon → ->value())
    ->position('bottom-right')
    ->extra(['undo_url' => route('users.restore', $user)]);
```

Because each call returns a new object, ordering is irrelevant and instances are safe to share/cache. `extra()` **merges** into the existing extra array rather than replacing it.

### Serialization

```php
$n->toArray();
// [
//   'type' => 'success', 'message' => 'User updated',
//   'title' => 'Done', 'duration' => 5000, 'icon' => 'check-circle',
//   'position' => 'bottom-right', 'extra' => ['undo_url' => '…'],
// ]
```

`toArray()` **strips null values** (and an empty `extra`). This is the wire format dispatched to the browser, so a notification built with only `success('msg')` produces just `{type, message}` — the renderer fills in the rest from its own defaults.

---

## Notification types

The canonical types are **`success`, `error`, `warning`, `info`**. Note the body uses `error`, not `danger`. Drivers normalize where needed — e.g. `FlasherDriver::mapType()` folds both `error` and `danger` into flasher's `error`. The toast container picks an icon per type (`success`/`error`/`warning`/`info`); an unknown type renders with no type-specific icon.

---

## `NotificationManager`

`final class NotificationManager` is an all-static facade over the active driver. It holds a process-global default driver and routes every send through driver resolution.

```php
use NyonCode\WireCore\Notifications\NotificationManager;

// Convenience shortcuts (build + send in one call)
NotificationManager::success($message, ?$driver = null, $livewireComponent = null);
NotificationManager::error($message,   ?$driver = null, $livewireComponent = null);
NotificationManager::warning($message, ?$driver = null, $livewireComponent = null);
NotificationManager::info($message,    ?$driver = null, $livewireComponent = null);

// Send a pre-built Notification (use this when you need title/duration/icon/extra)
NotificationManager::send($notification, ?$driver = null, $livewireComponent = null);

// Driver management
NotificationManager::setDefaultDriver($driver);   // global override
NotificationManager::getDefaultDriver();          // current global (lazily a SessionDriver)
NotificationManager::resolve(?$driver);           // apply the resolution rules
NotificationManager::reset();                     // back to built-in default (tests)
```

- `$driver` — an explicit per-call `NotificationDriver`; `null` falls back to the global default.
- `$livewireComponent` — the Livewire component instance, needed by drivers that `dispatch()` browser events. Pass `$this` from inside a component.

---

## Driver resolution & the default-driver gotcha

`resolve()` chooses a driver in this priority order:

1. **Explicit** driver passed to the call (e.g. a per-table/per-component override)
2. **Global default** set via `NotificationManager::setDefaultDriver(...)`
3. **Built-in `SessionDriver`** — lazily instantiated on first use

> **Gotcha — config does not auto-wire the static manager.** The service provider binds the container service `NotificationDriver::class` from `wire-core.notifications.default` (`session`/`livewire`/`flasher`/`null`). But `NotificationManager`'s static `$defaultDriver` is **independent** of that binding: if nobody calls `setDefaultDriver()`, the static manager always falls back to a plain `SessionDriver`, regardless of the config value.
>
> If you want the configured driver to be the global default for the static API, bridge it explicitly — typically in `AppServiceProvider::boot()`:
>
> ```php
> use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
> use NyonCode\WireCore\Notifications\NotificationManager;
>
> NotificationManager::setDefaultDriver(app(NotificationDriver::class));
> ```
>
> Code paths that resolve the driver through the container (constructor/`app()` injection of `NotificationDriver`) already honor config; only the static-default path needs this bridge.

---

## Sending from Livewire components

Use the `InteractsWithNotifications` trait. It carries an optional per-component driver and routes through the manager, passing `$this` as the Livewire component automatically.

```php
use Livewire\Component;
use NyonCode\WireCore\Notifications\Concerns\InteractsWithNotifications;
use NyonCode\WireCore\Notifications\Notification;

class ApproveButton extends Component
{
    use InteractsWithNotifications;

    public function approve(): void
    {
        // …business logic…

        $this->notifySuccess('Approved');
        // or, with full control:
        $this->notify(
            Notification::success('Approved')->title('Done')->duration(6000)
        );
    }
}
```

Trait API:

| Method | Effect |
|--------|--------|
| `notify(Notification $n)` | send through the resolved driver with `$this` as the component |
| `notifySuccess/Error/Warning/Info(string $message)` | build + send the matching type |
| `setNotificationDriver(NotificationDriver $driver)` | override the driver for this component only |

The trait is `@phpstan-require-extends Component` — it's meant for Livewire components, since drivers may `dispatch()` events on them.

---

## Sending from actions

Actions don't call the manager directly — they *declare* notifications, and the engine's **Action Pipeline** (`NotificationStage`) sends them after the action runs. See the [Action Pipeline](unified-engine.md#action-pipeline) and the Actions section of [`core.md`](../core.md).

```php
EditAction::make()
    ->successNotification('User saved')                 // string
    ->failureNotification(fn ($record) => "Couldn't save {$record->name}"); // string|Closure
```

`HasLifecycle` also exposes imperative senders for use inside `before()`/`after()` hooks: `sendSuccessNotification()`, `sendFailureNotification()`, `sendWarningNotification()`, `sendInfoNotification()`, and `sendNotification($notification)`.

---

## Built-in drivers

### When to use which

| Driver | Use it when… | Don't use it when… |
|--------|--------------|--------------------|
| `SessionDriver` *(default)* | you want zero-setup, framework-native feedback that **survives full page loads / redirects** (flash message), with a basic live toast as a bonus. Good default for server-rendered flows and redirect-after-action. | you need rich live toasts with per-notification `title`/`duration` — it only transmits `type`+`message` over the live event. |
| `LivewireEventDriver` | your UI is the Alpine **toast container** and you want **rich, instant toasts** (title, duration, icon, extra) without a page reload. The recommended pairing with `<x-wire-notifications::toast-container />`. | you have no Livewire component in scope (it then just falls back to a session flash). |
| `FlasherDriver` | your app already uses **[php-flasher](https://php-flasher.io)** (Toastr/Notyf/SweetAlert adapters) and you want Wire notifications to flow into that existing UI. | you rely on the built-in toast container — flasher renders its own UI and bypasses it. |
| `NullDriver` | you want to **disable notifications** — tests, queued/background jobs, or contexts with no user to notify. | you actually want the user to see anything. |

Pick a project-wide default via `wire-core.notifications.default`; override per-component (`setNotificationDriver()` / the trait) or per-call (the `$driver` argument) when one flow needs something different.

### Contract

All implement the one-method contract:

```php
namespace NyonCode\WireCore\Notifications\Contracts;

interface NotificationDriver
{
    public function send(Notification $notification, mixed $livewireComponent = null): void;
}
```

### Comparison

| Driver | Config key | Delivery | Carries full payload? | No-Livewire fallback |
|--------|-----------|----------|-----------------------|----------------------|
| `SessionDriver` *(built-in default)* | `session` | session flash **+** Livewire event (`type`,`message` only) | **No** — only type & message | n/a (always flashes) |
| `LivewireEventDriver` | `livewire` | Livewire browser event with full `toArray()` | **Yes** | flashes to session |
| `FlasherDriver` | `flasher` | php-flasher (`flash()`) | partial (title/timeout/position mapped) | flashes if flasher absent |
| `NullDriver` | `null` | discards | — | — |

### `SessionDriver`

```php
new SessionDriver(sessionKey: 'table-notification', eventName: 'table-notification');
```

Does two things: flashes `['type','message']` under `$sessionKey`, and — if a Livewire component with a `dispatch()` method is supplied — dispatches `$eventName` with `type` and `message`. **Only those two fields travel through the event**, so `title`/`duration`/`icon` set on the notification will *not* reach a toast via this driver; the toast uses its own defaults. Choose `LivewireEventDriver` when you need the richer payload live.

### `LivewireEventDriver`

```php
new LivewireEventDriver(eventName: 'table-notification');
```

Dispatches the event with the **entire** notification payload (`dispatch($eventName, ...$notification->toArray())`), so `title`, `duration`, `icon`, `position`, and `extra` all reach the frontend listener. If no Livewire component is available it flashes `toArray()` to session as a fallback so the notification isn't lost.

### `FlasherDriver`

```php
new FlasherDriver(adapter: 'toastr'); // adapter optional, e.g. 'toastr' | 'notyf' | 'sweetalert'
```

Integrates [php-flasher](https://php-flasher.io). Maps `error`/`danger` → flasher `error`; forwards `duration` as `timeout`, plus `position` and `title` when present. If the global `flash()` helper isn't installed it silently flashes `toArray()` to session instead of throwing — so the package degrades gracefully without flasher.

### `NullDriver`

No-op. Use it to disable notifications entirely (e.g. in tests or background jobs) via `wire-core.notifications.default = 'null'` or `setDefaultDriver(new NullDriver)`.

---

## Writing a custom driver

Implement `NotificationDriver` and register it. Any toast/alert library works — Notyf, SweetAlert, a native browser API, a server-sent log, etc.

```php
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;

class NotyfDriver implements NotificationDriver
{
    public function send(Notification $notification, mixed $livewireComponent = null): void
    {
        if ($livewireComponent && method_exists($livewireComponent, 'dispatch')) {
            $livewireComponent->dispatch('notyf', ...$notification->toArray());
            return;
        }

        // Always provide a no-Livewire fallback so notifications are never silently dropped.
        session()->flash('table-notification', $notification->toArray());
    }
}
```

Register it globally:

```php
NotificationManager::setDefaultDriver(new NotyfDriver());
```

Or make it config-selectable by extending the provider's `match` in `registerNotifications()` (or binding `NotificationDriver::class` yourself).

**Driver authoring conventions:**
- Treat `$livewireComponent` as possibly `null` — guard with `method_exists($c, 'dispatch')`.
- Provide a session-flash fallback so messages survive a non-Livewire request.
- Read what you support from the `Notification`/`toArray()` and ignore the rest; don't assume every field is set (nulls are stripped).

---

## Frontend: the toast container & event contract

`<x-wire-notifications::toast-container />` (class `Notifications\View\ToastContainer`) is the Alpine-powered renderer. Drop it once in your layout.

### Which drivers actually drive the toast?

The toast container is just an Alpine listener on a **Livewire browser event**. So it only renders notifications from drivers that dispatch that event:

| Driver | Drives the toast container? | Detail |
|--------|-----------------------------|--------|
| `LivewireEventDriver` | ✅ **Yes — fully** | dispatches the full `toArray()` payload, so `type`/`message`/`title`/`duration`/`icon` all render |
| `SessionDriver` | ✅ Yes — partially | dispatches the event too, but only `type`+`message`; `title`/`duration` fall back to the container's defaults. Also flashes to session for the non-Livewire case |
| `FlasherDriver` | ❌ No | renders through php-flasher's own UI — bypasses this container entirely |
| `NullDriver` | ❌ No | discards everything |

**Bottom line:** use `LivewireEventDriver` for rich toasts (recommended when the toast container is your UI), `SessionDriver` for a lightweight type+message toast that also survives full page loads, and don't combine the toast container with `FlasherDriver` (you'd get flasher's UI instead). The event only fires when a Livewire component is in scope to `dispatch()` on (e.g. `$this` is passed); a pure session flash without a component won't pop a live toast until the next render that re-dispatches.

```blade
<x-wire-notifications::toast-container />
{{-- or customized: --}}
<x-wire-notifications::toast-container
    position="bottom-right"
    :duration="5000"
    event-name="table-notification" />
```

Props:

| Prop | Default | Purpose |
|------|---------|---------|
| `position` | `top-right` | one of `top-left/top-center/top-right/bottom-left/bottom-center/bottom-right` → mapped to Tailwind classes by `positionClasses()` |
| `duration` | `4000` | fallback auto-dismiss (ms) when a notification has no `duration` |
| `eventName` | `table-notification` | the browser event it listens for (`x-on:{eventName}.window`) |

### Event contract

The container listens on `window` for `eventName` and reads `$event.detail`:

| `detail` field | Used for |
|----------------|----------|
| `type` | icon + color (`success`/`error`/`warning`/`info`) |
| `message` | body text (always shown) |
| `title` | bold heading (shown only if present) |
| `duration` | per-toast dismiss timer; falls back to the container's `duration` prop |

**The `eventName` must match the driver's event name** (both default to `table-notification`). If you instantiate `new LivewireEventDriver('toast')`, set `event-name="toast"` on the container. With `SessionDriver`/`LivewireEventDriver` defaults, no wiring is needed.

> Remember the payload asymmetry: under `SessionDriver` the event only carries `type`+`message`, so `title`/`duration` come from the container defaults. Under `LivewireEventDriver` the full payload arrives, so per-notification `title`/`duration` take effect.

### Triggering toasts from JavaScript

The container installs a global `window.wireToast` helper (and an Alpine `$toast` magic) in its `init()`, so you can pop a toast from the frontend without a server round-trip. The helper just dispatches the container's `eventName` window event with the same `detail` contract above.

```js
// shorthand: type + message
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

```blade
{{-- inside Alpine, via the $toast magic --}}
<button @click="$toast.success('Copied!')">Copy</button>
```

The helper targets the container's configured `eventName`, so a custom `event-name="my-toast"` is wired automatically. `window.wireToast` is installed once (the first container wins); if you render multiple containers with different event names, dispatch the `CustomEvent` yourself for the secondary ones.

---

## Configuration

`config/wire-core.php`:

```php
'notifications' => [
    'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'), // session | livewire | flasher | null
],
```

The provider resolves the container binding `NotificationDriver::class` from this value:

```php
match ($driver) {
    'livewire' => new LivewireEventDriver,
    'flasher'  => new FlasherDriver,
    'null'     => new NullDriver,
    default     => new SessionDriver,   // 'session' and anything unknown
};
```

(See the [default-driver gotcha](#driver-resolution--the-default-driver-gotcha): this binding feeds container injection, not the static `NotificationManager` default.)

---

## Testing

```php
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;

// Silence notifications
NotificationManager::setDefaultDriver(new NullDriver());

// …exercise code…

// Restore the built-in default between tests
NotificationManager::reset();
```

- `NullDriver` swallows everything — handy when asserting business logic without UI noise.
- `reset()` clears the static global default; call it in `tearDown`/`afterEach` to avoid driver leakage between tests.
- For Livewire assertions, use `LivewireEventDriver` and assert the dispatched event (`->assertDispatched('table-notification', ...)`).
- For session-based assertions, `SessionDriver` flashes under `table-notification`.

---

## Deprecations

`TableNotification` and `TableNotificationManager` are **deprecated class aliases** (via `class_alias`) for `Notification` and `NotificationManager` respectively, kept for backwards compatibility and **slated for removal in v2.0**. Use the canonical classes in new code.

```php
// Deprecated
NyonCode\WireCore\Notifications\TableNotification
NyonCode\WireCore\Notifications\TableNotificationManager

// Use instead
NyonCode\WireCore\Notifications\Notification
NyonCode\WireCore\Notifications\NotificationManager
```

---

## Gotchas & invariants

- **Immutability:** every `Notification` modifier returns a new instance — `$n->title('x')` does nothing unless you keep the return value.
- **Null stripping:** `toArray()` drops nulls and empty `extra`; renderers/drivers must tolerate missing fields.
- **`error`, not `danger`:** the canonical failure type is `error`; only `FlasherDriver` accepts `danger` as a synonym.
- **Static default ≠ config default:** the static `NotificationManager` defaults to `SessionDriver` until `setDefaultDriver()` is called; container-injected `NotificationDriver` honors config. Bridge them if you rely on the static API.
- **Event-name matching:** the toast container's `event-name` must equal the driver's dispatched event, or toasts won't appear.
- **Payload asymmetry:** `SessionDriver` only transmits `type`+`message` over the live event; richer fields require `LivewireEventDriver` (or a custom driver that spreads `toArray()`).
- **Never silently drop:** custom drivers should always provide a session-flash fallback for non-Livewire requests.

---

## Related

- Provider wiring & Blade namespaces: [`architecture/core.md`](../core.md)
- Action notification declaration: [Action Pipeline](unified-engine.md#action-pipeline)
- ADR on driver defaults: [`decisions/0004-notification-driver-defaults.md`](../decisions/0004-notification-driver-defaults.md)
