<?php

declare(strict_types=1);

use NyonCode\WireModuleTenants\Models\Tenant;

return [

    /*
    |--------------------------------------------------------------------------
    | The company model
    |--------------------------------------------------------------------------
    |
    | What a tenant is. The module's own `Tenant` over the `tenants` table it
    | migrates, or a model of the application's with the same columns. It
    | becomes `wire-core.tenancy.model` unless that is already set.
    |
    */
    'model' => Tenant::class,

    /*
    |--------------------------------------------------------------------------
    | Registering a company
    |--------------------------------------------------------------------------
    |
    | Who may create one: 'anyone' signed in (a SaaS), 'ability' — only with
    | `registration_ability` through the Gate (an internal system) — or false,
    | nobody through the screen. The person who registers it is its owner.
    |
    */
    'registration' => env('WIRE_TENANTS_REGISTRATION', 'anyone'),

    'registration_ability' => 'tenants.create',

    /*
    |--------------------------------------------------------------------------
    | Where a company's pages are
    |--------------------------------------------------------------------------
    |
    | The address of one company, with `{tenant}` where its slug goes — the
    | tenant zone's own address. Registering and accepting an invitation land
    | there. `//{tenant}.example.com` for a domain zone.
    |
    */
    'home' => 'app/{tenant}',

    /*
    |--------------------------------------------------------------------------
    | The module's own routes
    |--------------------------------------------------------------------------
    |
    | Registering a company and accepting an invitation happen outside any
    | company, so they are routes of their own: `{prefix}/register` and a
    | signed `{prefix}/invitations/{invitation}`.
    |
    */
    'routes' => [
        'prefix' => 'tenants',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    */
    'invitations' => [
        'expire_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Slugs a company may not take
    |--------------------------------------------------------------------------
    |
    | A slug is a URL segment, so a company called "Register" must not take a
    | segment the application's own routes answer.
    |
    */
    'reserved_slugs' => ['admin', 'api', 'app', 'login', 'logout', 'register', 'tenants'],

    'navigation' => [
        'group' => 'company',
        'sort' => 80,
    ],

];
