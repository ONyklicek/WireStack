<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The Frame
    |--------------------------------------------------------------------------
    |
    | Which layout the signed-out screens render inside. They ship no frame of
    | their own, deliberately: a login page that does not look like the panel it
    | leads into is the one screen where a mismatch is noticed, and the shell
    | already owns a frame for exactly this — `wire-admin::auth-layout`, the head
    | and the card with none of the navigation.
    |
    | `auto` uses the shell's frame when the shell is installed. An application
    | with a frame of its own names it here instead:
    |
    |     'layout' => 'components.layouts.guest',
    |
    | With neither — no shell, no name — rendering a screen raises an exception
    | that says this, rather than a missing-component error one layer down.
    |
    */
    'layout' => env('WIRE_AUTH_LAYOUT', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | The Screens
    |--------------------------------------------------------------------------
    |
    | Whether this package answers Fortify's view callbacks. Fortify is headless
    | — it ships the routes, the credential check, the throttling, the reset
    | tokens and the two-factor challenge, and asks the application for the
    | markup — so this is the whole of what the package does on the way in.
    |
    | Which screens exist is Fortify's question, not this one: registration,
    | password reset, email verification and two-factor are its `features`, and
    | a screen for a feature that is off is never routed to. Turn this off to
    | keep the package for its user-menu entry alone, or while migrating from
    | views you already had.
    |
    | An application that wants six of the seven registers its own in a provider
    | of its own — application providers boot after package ones, so the last
    | `Fortify::loginView()` wins and it is yours.
    |
    */
    'views' => env('WIRE_AUTH_VIEWS', true),

    /*
    |--------------------------------------------------------------------------
    | The Way Out
    |--------------------------------------------------------------------------
    |
    | Whether to put "Sign out" in the shell's user menu. It posts to Fortify's
    | own logout route, so the session invalidation and the token regeneration
    | stay where they are maintained.
    |
    | On by default because the alternative was every application hand-writing
    | the same form into a layout slot: an admin whose menu has no way out reads
    | as unfinished, and it is not a decision anyone was making deliberately.
    |
    */
    'user_menu' => env('WIRE_AUTH_USER_MENU', true),

];
