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
    */
    'routes' => [
        'enabled' => false,
        'prefix' => null,
        'middleware' => ['web'],
        'domain' => null,
        'only' => [],
        'except' => [],
    ],

];
