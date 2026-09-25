<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Self-Registering Routes
    |--------------------------------------------------------------------------
    |
    | `Route::wireResources()` in your own route file stays the reference path:
    | prefix, middleware and domain are arguments to the group you call it in,
    | and nothing here changes that. This is the same three arguments, handed
    | over once, for the application that wants the convention and would rather
    | never open `routes/web.php` for it.
    |
    | Off by default, and that is deliberate rather than cautious. Package
    | providers boot before your own, so these routes are matched BEFORE
    | everything in `routes/web.php` — an application with a catch-all under the
    | same prefix wins today and would stop winning. Opting in has to be a
    | decision someone made.
    |
    | Enabling this AND calling `Route::wireResources()` yourself registers every
    | page twice under one route name, and is refused rather than resolved.
    |
    | `only` / `except` take registered keys — a resource key or a dashboard key,
    | the same key the menu and `ResourceRoutes::urlFor()` address it by.
    |
    | ZONES. Several mount points over one set of resources — `admin`,
    | `business`, `production` — each its own group, and a resource may be in
    | one, several or all of them. Add a `zones` key and each entry becomes a
    | group of its own:
    |
    |   'zones' => [
    |       'admin' => [
    |           'prefix' => 'admin',
    |           'middleware' => ['web', 'auth', 'can:admin'],
    |           'only' => ['invoices', 'users'],
    |       ],
    |       'business' => [
    |           'prefix' => 'business',
    |           'middleware' => ['web', 'auth', 'can:business'],
    |           'except' => ['users'],
    |       ],
    |   ],
    |
    | The array key IS the zone: it becomes the route-name prefix, so the same
    | resource in two zones gets `admin.wire.invoices.index` and
    | `business.wire.invoices.index` rather than colliding. That is the reason to
    | prefer this over hand-written groups — in a route file the `->name()` call
    | is a line someone forgets, and forgetting it makes the second zone silently
    | take over the first's links. Here it cannot be forgotten or repeated.
    |
    | Keys outside a zone (`prefix`, `middleware`, `domain`, `only`, `except`)
    | are the defaults every zone inherits and overrides. With no `zones` key at
    | all they are one unnamed group, which is what a single-zone application
    | wants and what these values already do.
    |
    | ZONE ENTRY. With several zones, the address above them — `/` — is where
    | signing in should end. `zone_entry.uri` routes it (as `wire.zones`; a
    | route file calls `Route::wireZoneEntry('/')` instead), and point
    | `fortify.home` at it. It sends each person straight into a zone: their
    | own `HasPreferredZone::preferredZone()`, else `zone_entry.primary`, else
    | the only zone they may enter. Only a real choice shows `zone_entry.view`
    | (handed `$zones`, key => URL, and `$primary`); with no view it takes the
    | first zone they may enter. `?choose` always shows the picker — the link
    | a zone switcher's "all zones" entry wants.
    |
    | MIDDLEWARE. `auth` is in the default, and it is the one default here that
    | is a safety decision rather than a convenience. The pages this registers
    | are a resource's create, edit and delete screens; a group without `auth`
    | serves every one of them to anybody who knows the URL, and nothing about
    | the panel looks wrong while it does — which is why it is the default rather
    | than a line in the docs. An application whose panel is deliberately public,
    | or which guards it some other way, takes it out.
    |
    | Without `nyoncode/wire-module-auth` or another package answering Laravel's
    | `login` route, `auth` redirects to a route that does not exist. That is a
    | loud failure and the right one: the alternative is a quiet open door.
    |
    */
    'routes' => [
        'enabled' => false,
        'prefix' => null,
        'middleware' => ['web', 'auth'],
        'domain' => null,
        'only' => [],
        'except' => [],
        'zones' => [],
        'zone_entry' => [
            'uri' => null,
            'primary' => null,
            'view' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Pages of the application's own — classes extending
    | NyonCode\WirePanels\Pages\Page — registered the way resources are: each
    | is routed at its key by Route::wireResources(), listed in the menu and
    | shown by wire:resources. A folder of them can be discovered instead, with
    | config('wire-core.discover.pages').
    |
    |   'pages' => [
    |       App\Livewire\Pages\TaskBoard::class,
    |   ],
    |
    */
    'pages' => [],
];
