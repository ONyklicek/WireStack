<?php

declare(strict_types=1);

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

];
