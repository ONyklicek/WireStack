---
order: 70
summary: What the table says when an action finishes, fails, or hands work to a queue.
---

# Table Notifications

Wire uses notifications to confirm completed actions, report failures, and surface background work to the user.

## Default Usage

The simplest path is to let actions send success or failure messages.

```php
use NyonCode\WireCore\Actions\Action;

Action::make('save')
    ->successNotification('User saved.')
    ->failureNotification('User could not be saved.')
    ->action(function (User $record, Action $action) {
        try {
            $record->save();
            $action->sendSuccessNotification();
        } catch (\Throwable $e) {
            $action->sendFailureNotification();
        }
    })
```

## Manual Notifications

Use manual notifications when the result depends on runtime conditions.

```php
use NyonCode\WireCore\Notifications\Notification;

Action::make('process')
    ->action(function (User $record, Action $action) {
        if ($record->hasWarnings()) {
            $action->sendNotification(
                Notification::warning('Processed with warnings.')
            );

            return;
        }

        $action->sendNotification(
            Notification::success('Processing finished.')
        );
    })
```

## Notification Types

| Type | Typical use |
|------|-------------|
| `success` | Save, delete, import, publish |
| `error` | Failure, exception, invalid external state |
| `warning` | Partial completion, risky follow-up |
| `info` | Started job, queued work, neutral feedback |

## Toast Container

If you want built-in toast rendering, include the container in your layout:

```blade
<x-wire-notifications::toast-container />
```

Without it, notifications can still be dispatched through a custom driver, but the default visual container will not render.

## Drivers

Notification delivery is driver-based.

| Driver | Use when |
|--------|----------|
| CurrentComponentDriver | Built-in default — resolves the active Livewire component and delegates to `SessionDriver` (flash + full-payload live toast) |
| SessionDriver | Laravel flash-style delivery plus a full-payload live event |
| LivewireEventDriver | You want live toasts only, without the session flash |
| FlasherDriver | You use `php-flasher` |
| Custom driver | You need a project-specific integration |

### Per-table override

```php
use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;

->notificationDriver(new LivewireEventDriver('wire-toast'))
```

### Global config

```php
// config/wire-table.php
'notification_driver' => null,
```

Set a driver class there if you want one default for the whole app.

## Custom Notification Object

When you need more control, build the notification explicitly.

```php
Notification::success('User saved.')
    ->title('Done')
    ->duration(4000)
    ->icon('check')
    ->position('top-right')
```

`TableNotification` and `TableNotificationManager` were aliases of these two and
were removed in 2.0 — see [Upgrade Guide](../start/upgrade.md#removed-every-shim-marked-for-20-20).

## Related Docs

- [Getting Started](../start/getting-started.md)
- [Table Actions](actions.md)
- [Core Notifications](../core/notifications/index.md)
