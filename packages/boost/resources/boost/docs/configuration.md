---
order: 40
---

# Configuration

Wire works out of the box after installation. Publish config files only when you need to change defaults for notifications, date formats, uploads, table behavior, sortable behavior, or audit logging.

## Publish Config Files

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-forms::config
php artisan vendor:publish --tag=wire-table::config
php artisan vendor:publish --tag=wire-sortable::config
php artisan vendor:publish --tag=wire-boost::config
```

You only need the tags for packages you installed.

## Environment Variables

| Variable | Default | Used by |
|----------|---------|---------|
| `WIRE_NOTIFICATIONS_DRIVER` | `session` | Core notifications |
| `WIRE_AUDIT_ENABLED` | `true` | Core audit log |
| `WIRE_AUDIT_USER_MODEL` | `App\Models\User` | Core audit log |
| `WIRE_FORMS_UPLOAD_DISK` | `public` | Forms file upload |
| `WIRE_MOBILE_SHEET` | `true` | Core mobile bottom-sheets |
| `WIRE_MOBILE_BREAKPOINT` | `sm` | Core mobile sheet breakpoint |

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
            // 'lucide' => App\Wire\Icons\LucideIconSet::class,   // => "lucide:home"
        ],
        'paths' => [
            // resource_path('icons'),                 // logo.svg => "logo"
            // 'brand' => resource_path('icons/brand'), // mark.svg => "brand-mark"
        ],
        'warn_missing' => env('WIRE_ICONS_WARN_MISSING', false),
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

### Icons

| Key | Purpose |
|-----|---------|
| `default_set` | Which `sets` key is the **unprefixed** base set (default `'default'` = Heroicons). |
| `sets` | Registered icon sets. The default-set key is unprefixed; **every other key is a required prefix**, so its icons are addressed as `prefix:name` (e.g. `lucide:home`). Registering a non-default set without a string prefix throws. |
| `paths` | Folders of `.svg` files auto-registered as bare-named icons. A string key adds a dash-joined name prefix (`'brand' => …` → `brand-mark`). |
| `warn_missing` | Log a warning (and render the fallback) when an unknown icon name is used — handy for catching typos in development. |

```php
'icons' => [
    'default_set' => 'default',
    'sets' => [
        'default' => DefaultIconSet::class,   // "pencil"      (Heroicons, 20×20 fill)
        'lucide'  => LucideIconSet::class,    // "lucide:home" (24×24 stroke)
    ],
    'paths' => [
        'brand' => resource_path('icons/brand'),
    ],
    'warn_missing' => env('WIRE_ICONS_WARN_MISSING', false),
],
```

Sets are used together with deterministic, collision-free resolution. See
[Core → Foundation → Icons](core/foundation.md#icons) for the full API, the
`prefix:name` model, custom sets, and accessibility.

### Plugins

Register application or package plugins in the `plugins` array:

```php
'plugins' => [
    App\Wire\Plugins\TenantPlugin::class,
],
```

Plugins that implement `HasConfiguration` can also read merged options from `wire-core.plugins.config.{pluginId}`:

```php
'plugins' => [
    App\Wire\Plugins\ExportPlugin::class,

    'config' => [
        'export' => [
            'format' => 'xlsx',
        ],
    ],
],
```

See [Core Plugins](core/plugins.md) for plugin classes, lifecycle, dependencies, macros, hooks, type registries, query pipes, and plugin configuration.

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

### Mobile

Floating panels (dropdowns, action-group menus, select/date/tag pickers, table filter & column-toggle
panels) and the mobile modal variants present as a **bottom sheet** below a breakpoint. These are the
global defaults — every component overrides them per instance.

```php
'mobile' => [
    // Present floating panels as a bottom sheet on mobile. false = classic
    // trigger-anchored floating panel everywhere.
    'sheet' => env('WIRE_MOBILE_SHEET', true),

    // Breakpoint below which panels become a sheet:
    //   'sm' (< 640px, phones — default)
    //   'md' (< 768px, incl. small tablets)
    //   'lg' (< 1024px, incl. tablet portrait)
    'breakpoint' => env('WIRE_MOBILE_BREAKPOINT', 'sm'),
],
```

Per-component overrides (win over the global defaults):

```php
// Sheet on/off
Select::make('role')->options([...])->sheetOnMobile(false);   // force floating
Select::make('country')->searchable()->sheetOnMobile();       // force sheet even when searchable
$table->sheetOnMobile(false);                                 // filter + column-toggle panels

// Breakpoint (sm | md | lg)
Select::make('role')->mobileBreakpoint('lg');                 // sheet up to 1024px
$table->mobileBreakpoint('md');
ActionGroup::make([...])->mobileBreakpoint('md');
Action::make('edit')->form([...])->slideOverOnMobile()->mobileBreakpoint('md');
```

```blade
<x-wire::dropdown :sheet-on-mobile="false" :breakpoint="'md'">…</x-wire::dropdown>
```

Priority: per-component (`->sheetOnMobile()` / `->mobileBreakpoint()`) > searchable-auto-floating > global
config. Searchable selects default to floating so the search box stays usable. Sheets add safe-area
padding, a drag-to-dismiss grabber and a focus trap automatically.

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

See [Table Overview](table/overview.md), [Columns](table/columns/index.md), and [Exports](table/exports.md).

## Sortable

The `wire-sortable` config controls row ordering and SortableJS loading.

```php
return [
    'order_column' => 'sort_order',
    'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
    'animation' => 150,
    'user_model' => 'App\\Models\\User',
    'user_key_type' => 'id', // 'uuid' / 'ulid' for non-integer user keys
];
```

Set `sortablejs_cdn` to `null` when your application bundles SortableJS itself. Set `user_key_type` to `uuid` or `ulid` (before running the column-order migration) when your user model uses a non-integer primary key.

See [Sortable Installation](sortable/installation.md).

## Boost

The `wire-boost` config controls the AI tooling MCP server. The two code-executing tools are disabled by default.

```php
return [
    'server' => [
        'name' => 'WireStack Boost',
        'version' => '1.0.0',
    ],
    'tools' => [
        'database_query' => env('WIRE_BOOST_DATABASE_QUERY', false),
        'tinker' => env('WIRE_BOOST_TINKER', false),
        'browser_logs' => env('WIRE_BOOST_BROWSER_LOGS', true),
    ],
    'scan' => [
        'paths' => [app_path()], // where list-wire-components searches
    ],
    'docs' => [
        'paths' => [], // extra Markdown directories for search-wire-docs
    ],
    'browser_logs' => [
        'path' => storage_path('wire-boost/browser.log'),
        'max_entries' => 50,
    ],
];
```

Enable `database-query` and `tinker` only when you trust the agent connecting to the server. See
[MCP Server & Tools](boost/mcp-tools.md).

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
