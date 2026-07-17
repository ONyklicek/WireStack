---
order: 70
---

# Audit Log

Wire Core includes an audit log for recording model changes and table-related events. It stores the event type, auditable record, user, old values, new values, metadata, and timestamp.

## Install

The audit log is part of `wire-core`. If you installed `wire-table` or `wire-forms`, core is already installed.

Publish the config and migration:

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-core::migrations
php artisan migrate
```

That's the whole setup — the package registers the audit event subscriber
automatically, and the logger is gated by `wire-core.audit.enabled` (on by
default). If you registered `AuditEventSubscriber` manually in an application
service provider (the pre-1.7.1 setup), you can remove that line; the
subscription is idempotent, so keeping it does not double-log.

## Enable Auditing On A Model

Add `HasAuditable` to any Eloquent model you want to track.

```php
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Audit\Concerns\HasAuditable;

class Order extends Model
{
    use HasAuditable;
}
```

The trait records Eloquent `created`, `updated`, and `deleted` events.

## Exclude Or Include Columns

Use `getAuditExclude()` to hide noisy or sensitive columns for a single model.

```php
class Order extends Model
{
    use HasAuditable;

    protected function getAuditExclude(): array
    {
        return ['cached_total', 'internal_token'];
    }
}
```

Use `getAuditInclude()` when you want a whitelist.

```php
protected function getAuditInclude(): array
{
    return ['status', 'total', 'assigned_user_id'];
}
```

Global exclusions live in `config/wire-core.php`:

```php
'audit' => [
    'exclude_columns' => [
        'password',
        'remember_token',
    ],
],
```

## View Audit Entries

Each auditable model gets an `audits()` relation.

```php
$entries = $order->audits()->latest()->get();
```

Audit entries expose helpers for common queries:

```php
use NyonCode\WireCore\Audit\AuditEntry;

AuditEntry::forRecord($order)->get();
AuditEntry::forEvent('updated')->get();
AuditEntry::byUser($user->id)->get();
AuditEntry::olderThan(90)->delete();
```

To show a row-level audit trail in a Wire Table, add the built-in action:

```php
use NyonCode\WireCore\Audit\Actions\AuditTrailAction;

return $table
    ->model(Order::class)
    ->actions([
        AuditTrailAction::make(),
    ]);
```

The action opens a slide-over with the record history.

## Manual Audit Events

You can dispatch audit events manually for operations that do not go through an audited model event.

```php
use NyonCode\WireCore\Audit\Events\BulkActionExecuted;

event(new BulkActionExecuted(
    actionName: 'archive',
    modelType: Order::class,
    recordIds: $orders->modelKeys(),
    success: true,
    metadata: ['source' => 'orders-table'],
));
```

For a single cell update:

```php
use NyonCode\WireCore\Audit\Events\InlineCellUpdated;

event(new InlineCellUpdated(
    modelType: Order::class,
    recordId: $order->id,
    column: 'status',
    oldValue: 'draft',
    newValue: 'approved',
));
```

## Disable Temporarily

Disable audit logging during imports, seeders, or maintenance jobs:

```php
use NyonCode\WireCore\Audit\AuditLogger;

AuditLogger::withoutAuditing(function () {
    Order::query()->update(['synced_at' => now()]);
});
```

## Retention

Set a retention period in days:

```php
'audit' => [
    'retention_days' => 180,
],
```

Then schedule the bundled prune command:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('wire-core:audit-prune')->daily();
```

Run it manually with an ad-hoc period override:

```bash
php artisan wire-core:audit-prune --days=90
```

Without a configured `retention_days` (and no `--days`), the command warns and
prunes nothing. Programmatic pruning is still available via
`app(AuditLogger::class)->prune(?int $days = null)`.

## Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Global on/off switch |
| `model` | `AuditEntry::class` | Custom audit entry model |
| `user_model` | `App\Models\User` | User model for the `user()` relation |
| `events` | `null` | `null` logs all supported events; array logs only selected event types |
| `exclude_columns` | `password`, `remember_token` | Global column exclusions |
| `retention_days` | `null` | Number of days to keep entries |

Supported event types are `created`, `updated`, `deleted`, `bulk_action`, and `cell_updated`.

See [Configuration](../configuration.md) for the full config reference.
