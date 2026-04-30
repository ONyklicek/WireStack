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
];
