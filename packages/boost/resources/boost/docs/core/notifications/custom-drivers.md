---
order: 50
summary: "Delivering a notification somewhere this package does not know about — the contract, and where the driver is registered."
---

# Custom Drivers

Slack, a log line, a websocket of your own, a test spy: a driver is one small
interface, and the calling code never learns which one answered. This is the
seam that keeps every notification in the framework deliverable anywhere.

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

## Related

- [Notifications](index.md) — the object a driver receives
- [Toasts](toasts.md) and [Persistent Notifications](persistent.md) — the two shipped drivers
- [Plugins](../plugins/index.md) — registering a driver from a package
- [Configuration](../../start/configuration.md) — naming the default driver
