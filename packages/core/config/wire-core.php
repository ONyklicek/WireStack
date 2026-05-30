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
        // implementing NyonCode\WireCore\Foundation\Icons\IconSet. Sets added
        // here take priority over the bundled defaults, so you can override
        // individual icons or ship an entirely different style.
        'sets' => [
            'default' => DefaultIconSet::class,
            // 'custom' => App\Wire\Icons\MyIconSet::class,
        ],

        // Directories of SVG files to auto-register as icons. The icon name is
        // the file name without extension (logo.svg => "logo"). This is the
        // simplest way to add custom icons — just drop SVGs in a folder.
        'paths' => [
            // resource_path('icons'),
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
