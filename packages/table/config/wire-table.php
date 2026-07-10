<?php

declare(strict_types=1);
use NyonCode\WireTable\Preferences\Drivers\DatabasePreferenceDriver;
use NyonCode\WireTable\Preferences\Drivers\NullPreferenceDriver;
use NyonCode\WireTable\Preferences\Drivers\SessionPreferenceDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Table Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'per_page' => 10,
        'per_page_options' => [10, 25, 50, 100],
        'searchable' => true,
        'sortable' => true,
        'hoverable' => true,
        'striped' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | TextInputColumn
    |--------------------------------------------------------------------------
    */
    'text_input' => [
        'save_on_blur' => true,
        'save_on_enter' => true,
        'live_validation' => false,
        'live_debounce' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Driver
    |--------------------------------------------------------------------------
    |
    | Default notification driver class.
    | Built-in options:
    |   - \NyonCode\WireCore\Notifications\Drivers\SessionDriver::class
    |   - \NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver::class
    |   - \NyonCode\WireCore\Notifications\Drivers\FlasherDriver::class
    |
    */
    'notification_driver' => null, // null = SessionDriver (default)

    /*
    |--------------------------------------------------------------------------
    | Per-user Table Preferences
    |--------------------------------------------------------------------------
    |
    | Where a table remembers each user's column layout when it opts in with
    | Table::rememberColumns('key'). 'default' is used for signed-in users,
    | 'guest' for unauthenticated visitors (so a database-backed default can
    | still fall back to per-session memory for guests).
    |
    | Built-in drivers:
    |   - null     : do not persist (column toggles last only for the request)
    |   - session  : store in the session (no migration needed)
    |   - database : store in the `table_preferences` table (publish + run the
    |                migration: vendor:publish --tag="wire-table::migrations")
    |
    | Point an alias at your own class to use a custom store.
    |
    */
    'preferences' => [
        'default' => env('WIRE_TABLE_PREFERENCES_DRIVER', 'null'),
        'guest' => env('WIRE_TABLE_PREFERENCES_GUEST_DRIVER', 'session'),
        'drivers' => [
            'null' => NullPreferenceDriver::class,
            'session' => SessionPreferenceDriver::class,
            'database' => DatabasePreferenceDriver::class,
        ],
    ],

];
