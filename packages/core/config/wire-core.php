<?php

declare(strict_types=1);

use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;

return [
    'notifications' => [
        /*
        | Which driver delivers a notification.
        |
        |   session  — flash + Livewire dispatch (the default, transient)
        |   livewire — dispatch only
        |   flasher  — hand off to a Flasher toast
        |   database — write it down; survives the request that raised it
        |   null     — deliver nothing
        |
        | A list picks several at once, which is usually what you want:
        | ['session', 'database'] shows the toast now and keeps it in the bell
        | for a user who was looking elsewhere.
        */
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),

        'database' => [
            // Laravel's own notifications shape, so an application that already
            // has that table can point this at it and read both through its own
            // Notifiable::notifications() relation.
            'table' => env('WIRE_NOTIFICATIONS_TABLE', 'wire_notifications'),
        ],
    ],

    'icons' => [
        // Name of the base/fallback set. DefaultIconSet (the full Heroicons
        // collection) is always registered as the base set.
        'default_set' => 'default',

        // Icon sets registered with the IconManager. Each value must be a class
        // implementing NyonCode\WireCore\Foundation\Icons\IconSet.
        //
        // The bundled 'default' set (Heroicons) is the base set and its icons are
        // used with bare names (e.g. "pencil", "user"). EVERY other set's key is a
        // REQUIRED prefix: its icons are addressed as "prefix:name" (e.g.
        // "lucide:home"), so the two never collide and resolution is deterministic.
        // Registering a non-default set without a string prefix throws.
        //
        // Sets that also implement ProvidesIconMetadata may ship stroke-based or
        // non-20x20 icons (Lucide, Feather, Heroicons outline, …) and they render
        // correctly alongside the default solid set.
        // Besides the unprefixed solid 'default' set, the framework also bundles
        // the Heroicons outline variant (24x24, stroke), always available under
        // the "outline:" prefix (e.g. "outline:x-mark"). Use outline for larger UI
        // chrome (close buttons, toolbars, pagination, empty states) and the solid
        // set for small accents. List a set below only to add a third-party set or
        // to override the "outline" prefix with a different set.
        'sets' => [
            'default' => DefaultIconSet::class,
            // 'lucide' => App\Wire\Icons\LucideIconSet::class,   // → "lucide:home"
            // 'custom' => App\Wire\Icons\MyIconSet::class,       // → "custom:logo"
        ],

        // Directories of SVG files to auto-register as icons. The icon name is
        // the file name without extension (logo.svg => "logo"). Use a string key
        // as a name prefix to namespace a folder and avoid file-name collisions
        // (e.g. 'brand' => resource_path('icons/brand') => "brand-logo"). Each
        // file keeps its own viewBox and fill/stroke styling.
        'paths' => [
            // resource_path('icons'),
            // 'brand' => resource_path('icons/brand'),
        ],

        // When true, an unknown icon name logs a warning (and still renders the
        // fallback placeholder). Handy in development to catch typos.
        'warn_missing' => env('WIRE_ICONS_WARN_MISSING', false),
    ],

    // Colors
    'colors' => [
        'palette' => [],
    ],

    // Plugins
    // #TODO add auto discover
    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Resource classes this application owns, as a plain list. A resource binds
    | one entity to the surfaces it exposes and implements at least
    | NyonCode\WireCore\Core\Resources\Contracts\DescribesResource.
    |
    | The key lives here rather than in a component package because identity is
    | the half that needs no surface: a resource with only a form declares it
    | through wire-forms and never installs a table package.
    |
    |   'resources' => [
    |       App\Resources\OrderResource::class,
    |   ],
    |
    */
    'resources' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboards
    |--------------------------------------------------------------------------
    |
    | Dashboard classes this application owns, as a plain list. A dashboard
    | declares a page's worth of widgets and extends
    | NyonCode\WireCore\Widgets\Dashboard; `php artisan wire:dashboard Sales`
    | generates one.
    |
    | Registered separately from resources because they are different things
    | that a menu happens to list side by side — the menu, the router and the
    | search palette all read both registries through the same Catalog, and
    | neither registry knows about the other.
    |
    |   'dashboards' => [
    |       App\Dashboards\SalesDashboard::class,
    |   ],
    |
    */
    'dashboards' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | Off by default: most applications have one tenant, and scoping them would
    | be a WHERE clause bought for nothing. Once on it is strict — every model
    | using BelongsToTenant is constrained, and when no tenant resolves, it is
    | constrained to NOTHING rather than to everything.
    |
    | Bind your own resolver; the default answers null, which with tenancy on
    | means an empty page until you do:
    |
    |   app()->bind(TenantResolver::class, fn () => new class implements TenantResolver {
    |       public function resolve(): int|string|null
    |       {
    |           return auth()->user()?->tenant_id;
    |       }
    |   });
    |
    */
    'tenancy' => [
        'enabled' => env('WIRE_TENANCY', false),
        'column' => env('WIRE_TENANCY_COLUMN', 'tenant_id'),
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

    /*
    |--------------------------------------------------------------------------
    | Mobile
    |--------------------------------------------------------------------------
    |
    | How floating panels (action-group menus, dropdowns, select/date/tag
    | pickers, table filter & column-toggle panels) and action modals behave on
    | small screens. These are the GLOBAL DEFAULTS — every component can override
    | them per-instance:
    |
    |   Sheet on/off (sheet vs. classic floating dropdown):
    |     ->sheetOnMobile(true|false)          fields, filters, ActionGroup, Table
    |     :sheet-on-mobile="false"             <x-wire::dropdown>
    |
    |   Breakpoint (where the sheet/full-screen kicks in):
    |     ->mobileBreakpoint('sm'|'md'|'lg')   fields, filters, ActionGroup, Table,
    |                                          action modals (HasModal)
    |     :breakpoint="'md'"                   <x-wire::dropdown>
    |
    | Notes: searchable Select/SelectFilter default to floating (search stays
    | usable); an explicit ->sheetOnMobile() still wins. Sheets add safe-area
    | padding, a drag-to-dismiss grabber and a focus trap automatically.
    |
    */
    'mobile' => [
        // Default: present floating panels as a bottom sheet on mobile.
        // false = classic trigger-anchored floating panel everywhere.
        'sheet' => env('WIRE_MOBILE_SHEET', true),

        // Default breakpoint below which panels present as a mobile sheet:
        //   'sm' (< 640px, phones — default)
        //   'md' (< 768px, incl. small tablets)
        //   'lg' (< 1024px, incl. tablet portrait)
        // From the breakpoint up, the classic desktop floating panel is used.
        'breakpoint' => env('WIRE_MOBILE_BREAKPOINT', 'sm'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    |
    | Configuration for the audit logging system. Records who changed what,
    | when, with old/new value diffs. Integrates with the event system.
    |
    */
    'audit' => [
        // Global on/off switch for audit logging
        'enabled' => env('WIRE_AUDIT_ENABLED', true),

        // Custom AuditEntry model (must extend NyonCode\WireCore\Audit\AuditEntry)
        'model' => AuditEntry::class,

        // User model for the user() relationship on AuditEntry
        'user_model' => env('WIRE_AUDIT_USER_MODEL', 'App\\Models\\User'),

        // Which event types to log (null = all)
        // Available: 'created', 'updated', 'deleted', 'bulk_action', 'cell_updated'
        'events' => null,

        // Columns to never log (applied globally, in addition to per-model exclusions)
        'exclude_columns' => [
            'password',
            'remember_token',
        ],

        // Auto-prune entries older than N days (null = no pruning)
        'retention_days' => null,
    ],
];
