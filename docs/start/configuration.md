---
order: 30
summary: Every config file these packages publish, what each key decides, and the defaults you get for saying nothing.
---

# Configuration

Wire works out of the box after installation. Publish config files only when you need to change defaults for notifications, date formats, uploads, table behavior, sortable behavior, or audit logging.

## Publish Config Files

```bash
php artisan vendor:publish --tag=wire-core::config
php artisan vendor:publish --tag=wire-forms::config
php artisan vendor:publish --tag=wire-table::config
php artisan vendor:publish --tag=wire-sortable::config
php artisan vendor:publish --tag=wire-panels::config
php artisan vendor:publish --tag=wire-admin::config
php artisan vendor:publish --tag=wire-boost::config

# One per installed module
php artisan vendor:publish --tag=wire-module-users::config
php artisan vendor:publish --tag=wire-module-auth::config
php artisan vendor:publish --tag=wire-module-settings::config
php artisan vendor:publish --tag=wire-module-notifications::config
php artisan vendor:publish --tag=wire-module-audit::config
php artisan vendor:publish --tag=wire-module-media::config
```

You only need the tags for packages you installed. Every module's own installer
(`php artisan wire-module-users:install` and friends) publishes its config for
you, so the lines above are for an application setting one up by hand.

## Environment Variables

| Variable | Default | Used by |
|----------|---------|---------|
| `WIRE_NOTIFICATIONS_DRIVER` | `session` | Core notifications |
| `WIRE_AUDIT_ENABLED` | `true` | Core audit log |
| `WIRE_AUDIT_USER_MODEL` | `App\Models\User` | Core audit log |
| `WIRE_FORMS_UPLOAD_DISK` | `public` | Forms file upload |
| `WIRE_MOBILE_SHEET` | `true` | Core mobile bottom-sheets |
| `WIRE_MOBILE_BREAKPOINT` | `sm` | Core mobile sheet breakpoint |
| `WIRE_MOBILE_NATIVE` | `false` | Browser-native selects and date/time inputs below the mobile breakpoint |
| `WIRE_MOBILE_TOUCH` | `false` | Touch-built controls below the mobile breakpoint: wheels for dates and times, a full-height list for selects |
| `WIRE_TOURS_DRIVER` | `session` | Where a signed-in user's tour progress and finished tours are kept |
| `WIRE_TOURS_GUEST_DRIVER` | `session` | The same, for a guest |
| `WIRE_TOURS_POSTPONE` | `3` | How many times a tour's welcome block may be answered with "Later" before it stops asking |
| `WIRE_AUTH_CODE_LOGIN` | `false` | Signing in with a mailed code, no password |
| `WIRE_AUTH_CODE_SECOND_FACTOR` | `false` | A mailed code after a correct password |
| `WIRE_AUTH_CODE_VERIFY_EMAIL` | `false` | Confirming an address by code |
| `WIRE_AUTH_CODE_RESET_PASSWORD` | `false` | A reset mail that carries a code, not a link |
| `WIRE_USERS_PASSKEYS` | `auto` | The passkey card on the profile page |

## JavaScript Assets

Nothing needs configuring and nothing needs publishing: every package copies its
own pre-built bundles into `public/vendor/<package>` and serves them as static
files, cache-busted by file modification time. The one thing your app decides is
*where* they are emitted — put

```blade
@wireStackScripts
```

once in the layout `<head>` and every installed package's Alpine controllers are in
the initial document, which is what keeps them working across `wire:navigate`
(including the cached Back/Forward path). Pass a package name —
`@wireStackScripts('wire-table')` — to emit only one package's bundles.

`php artisan vendor:publish --tag=laravel-assets --force` does the same copy ahead
of time, which moves it off the first request after a deploy — useful, never
required. There is no config key either way.

Full explanation in [Getting Started → JavaScript Assets](getting-started.md#javascript-assets).

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

        // Roles, not colours: every surface follows what they point at. // [tl! focus:start]
        'success' => 'emerald',
        'danger' => 'red',
        'warning' => 'amber',
        'info' => 'cyan',
    ],

    // 'normal' or 'compact' — see Theming → Density.
    'density' => env('WIRE_DENSITY', 'normal'),

    // 'rounded' or 'sharp' — see Theming → Shape.
    'shape' => env('WIRE_SHAPE', 'rounded'), // [tl! focus:end]

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

See [Core Notifications](../core/notifications/index.md) for usage examples.

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
[Core → Foundation → Icons](../core/foundation/icons.md#icons) for the full API, the
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

See [Core Plugins](../core/plugins/index.md) for plugin classes, lifecycle, dependencies, macros, hooks, type registries, query pipes, and plugin configuration.

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

See [Core Modals](../core/modals.md) for modal actions and slide-overs.

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

    // Below the breakpoint, render the browser's own <select> / date / time
    // input instead of the custom control, wherever one exists.
    'native' => env('WIRE_MOBILE_NATIVE', false), // [tl! focus]

    // Below the breakpoint, render a control made for a thumb: a wheel for
    // dates and times, a full-height list sheet for selects. Outranks 'native'.
    'touch' => env('WIRE_MOBILE_TOUCH', false), // [tl! focus]
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

// Browser-native control below the breakpoint, the custom one above it
DateTimePicker::make('starts_at')->nativeOnMobile();          // the phone's own date wheel [tl! focus:start]
SelectFilter::make('status')->nativeOnMobile(false);          // keep the combobox under 'native' => true
Select::make('customer_id')->touchOnMobile();                 // full-height touch list, search included [tl! focus:end]
```

```blade
<x-wire::dropdown :sheet-on-mobile="false" :breakpoint="'md'">…</x-wire::dropdown>
```

Priority: per-component (`->sheetOnMobile()` / `->mobileBreakpoint()`) > searchable-auto-floating > global
config. Searchable selects default to floating so the search box stays usable. Sheets add safe-area
padding, a drag-to-dismiss grabber and a focus trap automatically.

`touch` and `native` both swap the control itself rather than its panel, and
one precedence holds for every surface: `->native()` (the browser's element
everywhere) > `->touchOnMobile()` / `touch` > `->nativeOnMobile()` / `native`.
`touch` renders a control built for a thumb — a wheel for a date or a time, a
bottom-sheet list with 48px rows, 16px text and a pinned search for a select —
and, unlike the browser's element, keeps every feature the field has: remote
search, creating an option, disabled days. See
[touch list on phones](../forms/fields/select.md#touch-list-on-phones) and
[touch wheel on phones](../forms/fields/date-time-picker.md#touch-wheel-on-phones).

`native` swaps the control itself rather than its panel. Every select and picker
that has a browser counterpart — `Select`, `BelongsToSelect`, `DateTimePicker`,
`TimePicker`, `SelectFilter`, `TernaryFilter` — renders both, and CSS at the same
breakpoint shows the browser's element below it and the custom control above it,
so no sheet is drawn for that field. A control that would lose something on the
way stays custom on every screen — a remote-search or create/edit-option select;
everything else goes native. A clock step becomes a native `<select>` of the
slots (beside a native date on a datetime), because iOS ignores a time input's
`step`, and the pickers' bounds are validated on the server, because a phone's
wheel ignores them. An explicit `->native(false)` also opts a
field out of the global switch; `->nativeOnMobile()` brings it back.

### Tours

Where a [tour](../core/tours.md) keeps what it remembers per person: the tours
they finished or skipped, the step they reached in one they left halfway, and
the ones they put off. It is the preference store with its own default,
`session` rather than `null`, because a tour on a store that forgets would
interrupt the same person on every page load.

```php
'tours' => [
    'postpone' => env('WIRE_TOURS_POSTPONE', 3),                 // "Later"s before a tour stops asking // [tl! focus]

    'preferences' => [
        'default' => env('WIRE_TOURS_DRIVER', 'session'),        // signed-in users
        'guest' => env('WIRE_TOURS_GUEST_DRIVER', 'session'),    // guests
        'drivers' => [
            'null' => NullPreferenceDriver::class,
            'session' => SessionPreferenceDriver::class,
            'database' => DatabasePreferenceDriver::class,       // "once, ever" — needs the migration
        ],
    ],
],
```

`postpone` is how many times a tour's [welcome block](../core/tour-welcome.md)
may be answered with "Later" before it records itself as seen and stops asking.
Each "Later" puts the tour down for that session; zero removes the button, and a
tour may override the number with `->postpone(int $times)`.

A tour's panel docks to the bottom of the screen below the `mobile.breakpoint`
above. See [Tour → Remembering](../core/tours.md#remembering).

## Forms

The `wire-forms` config controls date and time defaults, how money and phone numbers are
written, uploads, and the rich editor toolbar.

```php
return [
    'date_format' => 'd.m.Y',
    'time_format' => 'H:i',
    'datetime_format' => 'd.m.Y H:i',
    'first_day_of_week' => 1,

    'money' => [                                            // [tl! focus:start]
        'currency' => 'CZK',
        'decimal_separator' => ',',
        'thousands_separator' => ' ',
    ],

    'phone' => [
        'countries' => [],
        'default_country' => null,
    ],                                                      // [tl! focus:end]

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

`money` is what [MoneyInput](../forms/fields/money-input.md) writes an amount with when a field does
not say otherwise, and `phone` is [PhoneInput](../forms/fields/phone-input.md)'s offer — an empty
`countries` list offers the whole dialling-code table, and the list is also the validation.

Use `WIRE_FORMS_UPLOAD_DISK` to move uploads to a different filesystem disk:

```env
WIRE_FORMS_UPLOAD_DISK=s3
```

See [Field Reference](../forms/fields/index.md) for field-specific options.

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

See [Table Overview](../table/overview.md), [Columns](../table/columns/index.md), and [Exports](../table/exports.md).

## Sortable

The `wire-sortable` config controls row ordering.

```php
return [
    'order_column' => 'sort_order',
    'sortablejs_cdn' => null,
    'animation' => 150,
    'user_model' => 'App\\Models\\User',
    'user_key_type' => 'id', // 'uuid' / 'ulid' for non-integer user keys
];
```

`sortablejs_cdn` defaults to `null` because SortableJS is compiled into the package's
own bundle — reordering needs no CDN request, so it works offline and under a strict
CSP. Set it only if your **own** code needs a global `window.Sortable`: the tag is then
loaded *in addition to* the bundle, never instead of it, and the drag controller uses
the bundled copy either way.

Set `user_key_type` to `uuid` or `ulid` (before running the column-order migration) when your user model uses a non-integer primary key.

See [Sortable Installation](../sortable/installation.md).

## Panels

The `wire-panels` config decides whether the framework registers a resource's
pages as routes for you.

```php
return [
    'routes' => [
        'enabled' => false,
        'prefix' => 'admin',
        'middleware' => ['web', 'auth'],
        'domain' => null,
        'only' => [],
        'except' => [],
    ],
];
```

`enabled` is `false` because `Route::wireResources()` in your own route file is
the reference path — these are the same group arguments, handed over once, for an
application that would rather not keep a route file for them.

Two things to know before turning it on. Package providers boot before your own,
so these routes are matched **before** everything in `routes/web.php`; an
application with a catch-all under the same prefix wins today and would stop
winning. And enabling this *and* calling `Route::wireResources()` yourself is
refused rather than resolved — it would register every page twice under one route
name.

`only` / `except` take registered keys: a resource key or a dashboard key, the
same key the menu and `ResourceRoutes::urlFor()` use.

For several mount points — `admin`, `business`, `production` — add a `zones` key,
one entry per zone; each inherits the values above it and overrides what it
names. The array key becomes the route-name prefix, so the same resource in two
zones gets two route names instead of two routes fighting over one.

```php
'zones' => [
    'admin' => ['prefix' => 'admin', 'middleware' => ['web', 'auth', 'can:admin']],
    'business' => ['prefix' => 'business', 'only' => ['orders']],
],
```

See [Resources](../panels/routing.md#zones) for zones and
[Routing](../panels/routing.md) for the rest.

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
[MCP Server & Tools](../boost/mcp-tools.md).

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

See [Audit Log](../core/audit.md) for setup, model usage, and pruning.

## Admin

The shell publishes one block, and it is the brand — everything else about it is
markup you write (see [The Admin Shell](../admin/overview.md)):

```php
// config/wire-admin.php
'brand' => [
    'name' => null,        // falls back to config('app.name')
    'logo' => null,        // the wide menu — a path under public/, or a URL
    'logo_dark' => null,   // the dark-theme variant of it
    'mark' => null,        // the 64-pixel rail — falls back to the app's initial
    'height' => 28,        // the logo's rendered height, in pixels
    'url' => null,         // where the brand links to — defaults to the panel root
],
```

Why there are two logo forms, and why the choice is made before the page paints:
[Branding And Theme](../admin/branding.md#the-logo-in-the-rail).

## Modules

Each ready-made module publishes a config file of its own, and the keys are
documented on the module's page rather than here — a module is a whole area, and
its options only make sense beside the screens they change:

| File | What it configures | Page |
| --- | --- | --- |
| `wire-module-users.php` | The user model, the resource, roles and teams, the profile screen | [Users](../modules/users.md) |
| `wire-module-auth.php` | Which screens the auth module registers, the routes it claims, and the four one-time-code flows | [Auth](../modules/auth.md) |
| `wire-module-settings.php` | The settings table, its cache, and the groups the screen shows | [Settings](../modules/settings.md) |
| `wire-module-notifications.php` | The bell, its panel, and the stored-notification table | [Notifications](../modules/notifications.md) |
| `wire-module-audit.php` | The audit screen over the trail `wire-core` records | [Audit](../modules/audit.md) |
| `wire-module-media.php` | Disks, conversions, accepted types, and the picker | [Media](../modules/media.md) |

## The Rest Of `wire-core`

Four keys in `config/wire-core.php` are declarations rather than settings, and
each one is covered where the thing it declares is explained:

| Key | What it holds | Page |
| --- | --- | --- |
| `resources` | The resource classes an application registers | [Resources → Registration](../panels/resources.md#registration) |
| `dashboards` | The dashboard classes, the same way | [Dashboards](../core/widgets/dashboards.md) |
| `discover` | Directories to find resources and dashboards in — `'resources' => [namespace => directory]`, off until named | [Resources](../panels/resources.md#discovering-them) |
| `tenancy` | `enabled` and the `column` a tenant scope is applied on | [Authorization](authorization.md) |

`config/wire-table.php` has one more: `preferences` picks the driver a table's
per-user column order, visibility and page size are stored in (`null`, `session`
or `database`, with `guest` naming the driver for a visitor who is not signed
in). See [Advanced Features](../table/advanced.md).
