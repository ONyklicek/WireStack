<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The User Model
    |--------------------------------------------------------------------------
    |
    | The application's, not the package's. A module that brought its own users
    | table would be unusable in every application that already has one — which
    | is all of them — so this points at what you already have.
    |
    */
    'model' => env('WIRE_USERS_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Fields
    |--------------------------------------------------------------------------
    |
    | Which columns the list and the form work with. Kept here rather than
    | guessed from the schema: a `users` table is the one table every
    | application has changed.
    |
    */
    'fields' => [
        'name' => 'name',
        'email' => 'email',
        'password' => 'password',
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    |
    | Role management appears when `nyoncode/laravel-permission-extended` is
    | installed and the user model carries its `HasRoles` trait. `auto` decides
    | that by looking; true and false answer it for you.
    |
    | That package, and not the `spatie/laravel-permission` it is built on: the
    | role screens here assume its wildcard matching, its super-admin gate and
    | its permission-change events, so a model on bare Spatie is deliberately not
    | detected. Set this to `true` to overrule that.
    |
    | Authorization itself never goes through this: every check in this stack is
    | `Gate::allows()`, which the package registers into. This switch is about
    | the management screens, not about who may see them.
    |
    */
    'roles' => env('WIRE_USERS_ROLES', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Avatars
    |--------------------------------------------------------------------------
    |
    | A picture for the person, stored as a path in a column on your own users
    | table. `auto` looks for the column and shows the upload where it exists,
    | which means an application that has not migrated one sees initials and no
    | broken field.
    |
    | The column is read through the HasAvatar contract in wire-core, so a value
    | that is already a URL — Gravatar, an identity provider — is drawn as it
    | stands and nothing here has to know that.
    |
    */
    'avatar' => [
        'enabled' => env('WIRE_USERS_AVATARS', 'auto'),
        'column' => 'avatar_path',
        'disk' => env('WIRE_USERS_AVATAR_DISK', 'public'),
        'directory' => 'avatars',
    ],

    /*
    |--------------------------------------------------------------------------
    | The Profile Page
    |--------------------------------------------------------------------------
    |
    | Which cards the signed-in user's own page carries. Each one is a component
    | in its own right — an application that wants the password card on a page
    | of its own mounts it there and turns it off here.
    |
    | `delete_account` is off by default, and deliberately: an admin panel where
    | the only administrator can remove themselves in two clicks is a support
    | ticket waiting to happen. Turn it on where accounts are self-service.
    |
    */
    'profile' => [
        'password' => true,
        'two_factor' => true,
        'delete_account' => env('WIRE_USERS_DELETE_ACCOUNT', false),

        // Whether the shell's user menu gets a link to this page. On, because
        // the alternative was every application writing the link into a layout
        // slot by hand — and a profile page nobody can find is a page nobody
        // uses. Off where the application puts the link somewhere of its own.
        'menu_item' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Laravel Fortify owns two-factor here — the secrets, the TOTP window, the
    | recovery codes, the challenge on the way in. What this module adds is the
    | card on the profile page that drives Fortify's own actions, so a panel
    | gets the feature without a second implementation of it.
    |
    | `auto` shows the card when Fortify is installed and its two-factor feature
    | is enabled; true and false answer for you. See docs/core/teams-and-2fa.md
    | for the four lines an application writes to switch it on.
    |
    */
    'two_factor' => env('WIRE_USERS_TWO_FACTOR', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Teams
    |--------------------------------------------------------------------------
    |
    | Roles and permissions scoped per team, which is the permission layer's own
    | teams feature rather than a second one written here — the same
    | `nyoncode/laravel-permission-extended` the roles above need, over the
    | Spatie registrar it inherits. `auto` follows `permission.teams`, so turning
    | it on there is what turns it on.
    |
    | What this module adds is the part Spatie has no opinion about: which team
    | the person is looking at right now, a switcher in the chrome to change it,
    | and the team column on the role screens.
    |
    */
    'teams' => [
        'enabled' => env('WIRE_USERS_TEAMS', 'auto'),

        // The application's own team model and the relation on the user that
        // reaches it. Nothing here ships a teams table: an application that has
        // teams already has both, and one that does not is not using this.
        'model' => env('WIRE_USERS_TEAM_MODEL', 'App\\Models\\Team'),
        'relation' => 'teams',

        // What a team is called in a switcher, and the session key the current
        // one is remembered under.
        'label_attribute' => 'name',
        'session_key' => 'wire.team',
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    |
    | The menu group the module's entries sit under, and where it sorts. An
    | application that wants them somewhere else redeclares the group under the
    | same key — the last declaration wins.
    |
    */
    'navigation' => [
        'group' => 'access',
        'label' => null,
        'icon' => 'outline:users',
        'sort' => 90,
    ],

];
