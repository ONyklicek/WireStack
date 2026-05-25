# Configuration

Wire works out of the box after installation. Publish config files only when you need to change defaults for notifications, date formats, uploads, table behavior, sortable behavior, or audit logging.

## Publish Config Files

```bash
php artisan vendor:publish --tag=wire-core-config
php artisan vendor:publish --tag=wire-forms-config
php artisan vendor:publish --tag=wire-table-config
php artisan vendor:publish --tag=wire-sortable-config
```

You only need the tags for packages you installed.

## Environment Variables

| Variable | Default | Used by |
|----------|---------|---------|
| `WIRE_NOTIFICATIONS_DRIVER` | `session` | Core notifications |
| `WIRE_AUDIT_ENABLED` | `true` | Core audit log |
| `WIRE_AUDIT_USER_MODEL` | `App\Models\User` | Core audit log |
| `WIRE_FORMS_UPLOAD_DISK` | `public` | Forms file upload |

## Core

The `wire-core` config controls shared UI behavior.

```php
return [
    'notifications' => [
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),
    ],

    'icons' => [
        'default_set' => 'default',
        'sets' => [
            'default' => \NyonCode\WireCore\Foundation\Icons\DefaultIconSet::class,
        ],
    ],

    'colors' => [
        'palette' => [],
    ],

    'plugins' => [
        // App\Wire\Plugins\ExamplePlugin::class,
    ],

    'modals' => [
        'default_width' => 'md',
        'slide_over_width' => 'md',
        'close_on_click_away' => true,
        'close_on_escape' => true,
    ],
];
```

### Notifications

Built-in notification drivers are:

| Value | Driver |
|-------|--------|
| `session` | Stores notifications in session flash data |
| `livewire` | Dispatches Livewire browser events |
| `flasher` | Uses Flasher when your app has it installed |
| `null` | Disables delivery |

```env
WIRE_NOTIFICATIONS_DRIVER=livewire
```

See [Core Notifications](core/notifications.md) for usage examples.

### Plugins

Register application or package plugins in the `plugins` array:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
],
```

See [Core Plugins](core/plugins.md) for plugin classes, lifecycle, macros, hooks, type registries, and query pipes.

### Modals

Modal width values are Tailwind-style size tokens such as `sm`, `md`, `lg`, `xl`, `2xl`, or `full`.

```php
'modals' => [
    'default_width' => 'lg',
    'slide_over_width' => 'xl',
    'close_on_click_away' => false,
    'close_on_escape' => true,
],
```

See [Core Modals](core/modals.md) for modal actions and slide-overs.

## Forms

The `wire-forms` config controls date and time defaults, uploads, and the rich editor toolbar.

```php
return [
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',
    'datetime_format' => 'd.m.Y H:i',
    'first_day_of_week' => 1,

    'file_upload' => [
        'disk' => env('WIRE_FORMS_UPLOAD_DISK', 'public'),
        'directory' => 'uploads',
    ],

    'rich_editor' => [
        'toolbar' => [
            'bold', 'italic', 'underline', 'strike',
            '|', 'heading', 'bulletList', 'orderedList',
            '|', 'link', 'blockquote', 'codeBlock',
            '|', 'undo', 'redo',
        ],
    ],
];
```

Use `WIRE_FORMS_UPLOAD_DISK` to move uploads to a different filesystem disk:

```env
WIRE_FORMS_UPLOAD_DISK=s3
```

See [Field Reference](forms/fields/index.md) for field-specific options.

## Table

The `wire-table` config controls default table behavior and inline text input behavior.

```php
return [
    'defaults' => [
        'per_page' => 10,
        'per_page_options' => [10, 25, 50, 100],
        'searchable' => true,
        'sortable' => true,
        'hoverable' => true,
        'striped' => false,
    ],

    'text_input' => [
        'save_on_blur' => true,
        'save_on_enter' => true,
        'live_validation' => false,
        'live_debounce' => 500,
    ],

    'notification_driver' => null,
];
```

`notification_driver` may be left as `null`; the table then uses the core session driver. Set it only when a table needs a different driver class.

See [Table Overview](table/overview.md), [Columns](table/columns.md), and [Exports](table/exports.md).

## Sortable

The `wire-sortable` config controls row ordering and SortableJS loading.

```php
return [
    'order_column' => 'sort_order',
    'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
    'animation' => 150,
    'user_model' => 'App\\Models\\User',
];
```

Set `sortablejs_cdn` to `null` when your application bundles SortableJS itself.

See [Sortable Installation](sortable/installation.md).

## Audit

Audit log settings live in `config/wire-core.php`:

```php
'audit' => [
    'enabled' => env('WIRE_AUDIT_ENABLED', true),
    'model' => \NyonCode\WireCore\Audit\AuditEntry::class,
    'user_model' => env('WIRE_AUDIT_USER_MODEL', 'App\\Models\\User'),
    'events' => null,
    'exclude_columns' => [
        'password',
        'remember_token',
    ],
    'retention_days' => null,
],
```

Set `events` to an array when you want to log only selected event types:

```php
'events' => ['created', 'updated', 'deleted'],
```

See [Audit Log](core/audit.md) for setup, model usage, and pruning.
