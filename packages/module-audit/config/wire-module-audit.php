<?php

declare(strict_types=1);
use NyonCode\WireCore\Audit\AuditEntry;

return [

    /*
    |--------------------------------------------------------------------------
    | The Audit Model
    |--------------------------------------------------------------------------
    |
    | wire-core writes the trail and owns the table; this module only shows it.
    | Point this at your own model if you extended the entry — it has to extend
    | core's, because these screens read the trail through it: its casts, its
    | `user()` relation and its change diff. A class that is not one is refused
    | rather than rendered as an empty log.
    |
    | The table itself is core's migration, and core publishes it on demand:
    |
    |   php artisan vendor:publish --tag=wire-core::migrations
    |   php artisan migrate
    |
    */
    'model' => AuditEntry::class,

    /*
    |--------------------------------------------------------------------------
    | The Actor
    |--------------------------------------------------------------------------
    |
    | An entry stores the key of whoever was signed in; the screen shows a name.
    | The user model is core's setting (`wire-core.audit.user_model`) because
    | core defines the relation — what belongs here is which attribute of that
    | user reads as their name. The first one they actually have wins, so a
    | model with `name` and one with only `email` both work.
    |
    | An entry with no actor at all is not a gap: seeders, queued jobs and
    | console commands audit changes with no auth context, and those read as
    | "System".
    |
    */
    'actor' => [
        'attributes' => ['name', 'email'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Who May Read It
    |--------------------------------------------------------------------------
    |
    | An ability checked through `Gate::allows()`, or null for an open screen.
    | Null is the default the way every module here defaults: a permission this
    | package invented would lock the log out of every installation that has no
    | such ability. Name one and it guards the routes and hides the links that
    | lead to them, from this single line.
    |
    | An audit log is the screen most worth naming one for.
    |
    */
    'permission' => null,

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'group' => 'system',
        'label' => null,
        'icon' => 'outline:clipboard-document-list',
        'sort' => 95,
    ],

];
