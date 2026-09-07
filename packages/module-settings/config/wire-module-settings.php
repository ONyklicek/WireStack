<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Settings Groups
    |--------------------------------------------------------------------------
    |
    | What is configurable is the application's, so it declares it: one class per
    | group, implementing SettingsGroup, giving the group's storage name, its
    | heading and the form components it is edited with.
    |
    |   'groups' => [App\Settings\BrandingSettings::class],
    |
    | Three optional contracts sit beside that one, each opt-in so a group that
    | wants none stays four lines:
    |
    |   DescribesSettingsGroup    an icon, a description, where it sorts
    |   ProvidesSettingsDefaults  what the values are before anybody sets them
    |   GuardsSettingsGroup       an ability required to see and save this group
    |
    */
    'groups' => [],

    /*
    |--------------------------------------------------------------------------
    | Groups A Package Contributed
    |--------------------------------------------------------------------------
    |
    | A package that ships a feature may ship the tab that configures it, by
    | calling `SettingsRegistry::instance()->register(MailSettings::class)` from
    | its own service provider — the same way a module registers itself, rather
    | than ending its README with "now add this class to your config".
    |
    | Your own list above wins: a group you declare under the same storage name
    | replaces the contributed one, and sorts ahead of it among ties.
    |
    | Name a storage group here to drop a contributed tab entirely. That is the
    | reason a package may ship one at all — a package-shipped screen an
    | application cannot remove is what makes people stop installing them.
    |
    |   'except' => ['mail'],
    |
    */
    'except' => [],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'table' => env('WIRE_SETTINGS_TABLE', 'wire_settings'),

    /*
    |--------------------------------------------------------------------------
    | Permission
    |--------------------------------------------------------------------------
    |
    | The ability required to reach the settings screen. It becomes Laravel's own
    | `can:` middleware on both routes and hides the menu entry that leads to
    | them, from this single line — nothing here re-implements the check, so
    | Gate, spatie/laravel-permission and permission-extended all answer it the
    | way they answer every other check in this framework.
    |
    | Null is the default the way every module here defaults: a permission this
    | package invented would lock the screen out of every installation that has
    | no such ability. A settings screen is, after the audit log, the one most
    | worth naming one for — it is where an application's behaviour is changed
    | without a deploy.
    |
    | A single group may name an ability of its own on top of this, by
    | implementing GuardsSettingsGroup.
    |
    */
    'permission' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | A group is cached as one entry and the entry is dropped on write, because
    | settings are read on nearly every request and written almost never.
    |
    | The store matters more than it looks: leaving this null uses the default
    | one, and on an application whose default is `database` the read this cache
    | exists to avoid is simply replaced by a different query. Name the memory
    | store you already run and a settings read stops touching the database.
    |
    | Turning caching off is for debugging and for tests that assert against the
    | table directly; a production panel wants it on.
    |
    */
    'cache' => [
        'enabled' => env('WIRE_SETTINGS_CACHE', true),
        'store' => env('WIRE_SETTINGS_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | The menu group the module's entry sits under, and where it sorts. An
    | application that wants it somewhere else redeclares the group under the
    | same key — the last declaration wins.
    |
    */
    'navigation' => [
        'group' => 'system',
        'label' => null,
        'icon' => 'outline:cog-6-tooth',
        'sort' => 97,
    ],

];
