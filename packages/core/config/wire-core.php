<?php

declare(strict_types=1);

use NyonCode\WireCore\Audit\AuditEntry;
use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;

return [
    'notifications' => [
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),
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
