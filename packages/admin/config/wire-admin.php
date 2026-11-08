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
         * Where the brand links. Null means the admin's own address (the
         * `wire.home` route at the panel's prefix), or the application root
         * where the panel is not routed.
         */
        'url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | `navigation` is the menu's shape from `lg` up: 'sidebar' — a column
    | beside the page — or 'top', a bar under the header. Below `lg` it is the
    | drawer either way. One layout can differ from the rest with
    | `<x-wire-admin::layout navigation="top">`.
    |
    */
    'layout' => [
        'navigation' => env('WIRE_ADMIN_NAVIGATION', 'sidebar'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | `filter` puts a text field over the menu that hides the rows whose label
    | does not match, keeping the groups and submenus around the ones that do.
    | `auto` shows it once the menu holds `filter_threshold` entries or more —
    | a filter over six rows is a field in the way. `always` and `never` do
    | what they say. It is not a second search: ⌘K searches records and
    | commands, this narrows the list you are looking at.
    |
    | `pins` lets a person pin a row: a bookmark on every row that is a page,
    | and a "Pinned" section above the groups. `recent` records the pages a
    | person opens and lists the last five in a "Recent" section. Each is
    | switched on its own, and each needs somewhere to keep it — the
    | `wire-core.preferences` driver; over `null` neither is drawn. Off,
    | nothing is written either: no pin, no visit.
    |
    */
    'navigation' => [
        'filter' => env('WIRE_ADMIN_NAV_FILTER', 'auto'),

        'filter_threshold' => 12,

        'pins' => env('WIRE_ADMIN_NAV_PINS', true),

        'recent' => env('WIRE_ADMIN_NAV_RECENT', true),
    ],

];
