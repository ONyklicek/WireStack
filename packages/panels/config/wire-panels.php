<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Where The Zone Addresses Lead
    |--------------------------------------------------------------------------
    |
    | Nothing here registers a route. The panel's pages are the `panel` route
    | group, placed like every group (ADR 0041): `Route::wire('panel', zone:
    | 'admin')` — or the `Route::wireResources()` it always was — in your route
    | file, or an entry of `wire-core.routes.groups`, where every Laravel group
    | attribute and the panel's own options (`zone`, `tenant`, `only`,
    | `except`) are described. What stays here is where two addresses lead.
    |
    | ZONE ENTRY. With several zones, the address above them — the `zones`
    | group, named `wire.zones` — is where signing in should end; point
    | `fortify.home` at it. It sends each person straight into a zone: their
    | own `HasPreferredZone::preferredZone()`, else `zone_entry.primary`, else
    | the only zone they may enter. Only a real choice shows `zone_entry.view`
    | (handed `$zones`, key => URL, and `$primary`); with no view it takes the
    | first zone they may enter. `?choose` always shows the picker — the link
    | a zone switcher's "all zones" entry wants.
    |
    | TENANT ENTRY. A tenant zone's bare address (`app`, without a company)
    | sends a person to their default company. One with no company at all gets
    | `tenant_entry.view` — the place to offer registering one — or a 403
    | saying why when it is null. ADR 0040.
    |
    | MIDDLEWARE belongs to the group that places the panel, and `auth` in it is
    | a safety decision rather than a convenience: the pages are a resource's
    | create, edit and delete screens, and a group without `auth` serves them to
    | anybody who knows the URL while nothing about the panel looks wrong.
    |
    */
    'routes' => [
        'zone_entry' => [
            'primary' => null,
            'view' => null,
        ],

        'tenant_entry' => [
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
