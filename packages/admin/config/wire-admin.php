<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Brand
    |--------------------------------------------------------------------------
    |
    | What sits at the top of the menu. Everything here is optional: with no
    | logo at all the shell draws the application's initial in a rounded square
    | beside its name, which is a brand rather than an empty corner.
    |
    | `logo` is drawn in the wide menu and `mark` in the collapsed rail, because
    | a wordmark squeezed into 64 pixels is unreadable rather than small. Give
    | only a logo and the rail falls back to the initial.
    |
    | Paths go through `asset()` unless they are already absolute, so
    | 'images/logo.svg' and 'https://cdn.example.com/logo.svg' both work.
    |
    */
    'brand' => [
        'name' => env('WIRE_ADMIN_BRAND'),

        'logo' => env('WIRE_ADMIN_LOGO'),

        'logo_dark' => env('WIRE_ADMIN_LOGO_DARK'),

        'mark' => env('WIRE_ADMIN_MARK'),

        /*
         * In pixels rather than as a Tailwind class: a class named here would
         * live in a config file, which is not a file Tailwind scans, so `h-9`
         * would silently never be compiled in the consuming application.
         */
        'height' => env('WIRE_ADMIN_LOGO_HEIGHT', 28),

        /*
         * Where the brand links. Null means the application root.
         */
        'url' => null,
    ],

];
