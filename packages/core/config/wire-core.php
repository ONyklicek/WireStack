<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;

return [
    'notifications' => [
        'default' => env('WIRE_NOTIFICATIONS_DRIVER', 'session'),
    ],

    'icons' => [
        'default_set' => 'default',
        'sets' => [
            'default' => DefaultIconSet::class,
        ],
    ],

    'colors' => [
        'palette' => [],
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
        'model' => NyonCode\WireCore\Audit\AuditEntry::class,

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
