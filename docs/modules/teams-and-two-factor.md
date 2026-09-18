---
order: 30
summary: Two-factor authentication and per-team roles, switched on in an application that already has this panel — what Fortify owns, what the permission layer owns, and the few lines that are yours.
---

# Teams and Two-Factor

Two features every admin panel is eventually asked for, and neither of them is
implemented here.

**Laravel Fortify owns two-factor.** The secret, the TOTP window, the recovery
codes, the challenge screen on the way in and the rate limiting around it are a
security surface with a maintained owner. **The permission layer owns teams** —
roles scoped to a team, the extra column on the pivot tables, the cache key that
has to include it.

The permission layer here is always `nyoncode/laravel-permission-extended`. It is
not an alternative to `spatie/laravel-permission` — it requires it and extends it
with wildcard matching, a super-admin gate, Blade directives and
permission-change events — and those are what this framework's role screens
assume. A model on bare Spatie is deliberately **not** detected, because a
management UI over half an authorization model is worse than none.

What both deliberately leave out is the screen. This module supplies the screen
and calls their actions, so a panel gets the feature without a second
implementation of it sitting next to the first.

```bash
composer require laravel/fortify                        # two-factor
composer require nyoncode/laravel-permission-extended   # roles and teams
```

## How It Works

**Detection, not configuration.** Both features are `auto` by default and both
`auto`s look for the thing itself:

| Feature | What `auto` checks | Where |
| --- | --- | --- |
| Two-factor | Fortify's `EnableTwoFactorAuthentication` class exists **and** `Features::enabled(Features::twoFactorAuthentication())` | `Support\TwoFactor` |
| Teams | `nyoncode/laravel-permission-extended` is installed, `config('permission.teams')` is true, the registrar class exists, and `teams.model` resolves | `Support\Teams` |

Two conditions rather than one, in both cases, and both were worth checking.
Fortify is a dependency of several things an application may have installed for
other reasons, and its two-factor feature is a line in `fortify.features` that
can simply be left out — installed-but-off has to read as off, or the card
appears and its buttons throw. Teams follows `permission.teams` rather than a
switch of its own, because that setting is what makes the feature *real*: it is
what puts the team column on the pivot tables and into the permission cache key.
A panel that disagreed with it would be showing a switcher over authorization
that is not scoped.

Spatie's `PermissionRegistrar` is still what the team id is set on, and that is
not a contradiction: the extended package adds behaviour to the user model and
inherits the registrar untouched, so it remains the one object that knows what a
permission read is scoped to.

**Two-factor has three states, not two.** Fortify writes the secret the moment
the QR code is generated, so a person who opened the panel and closed the tab has
one and is protected by nothing:

| State | Means | The card shows |
| --- | --- | --- |
| off | no secret | one button: turn it on |
| pending | a secret, not confirmed | the QR code, the setup key, six code boxes — and a way back out |
| on | `two_factor_confirmed_at` is set | recovery codes, and a way off |

A panel that modelled this as a boolean would strand every interrupted setup in
the middle state while the badge told them they were safe. The badge reads
"Not finished" there, and *Turn it off* is offered from it as well as from `on` —
a half-finished setup has to be leavable in both directions or it is a trap.

**The team scopes everything, so it is set before anything reads it.** With
`permission.teams` on, every role and permission lookup is scoped by whatever
team id the registrar was last told about — so a request that never tells it sees
the *previous* one, which in a queue worker or a long-lived process is somebody
else's. The module pushes `SetCurrentTeam` onto the `web` middleware group, and
onto the group rather than behind an alias a route opts into: a page that forgot
the alias would authorize against the wrong team while looking perfectly correct.

**Switching redirects.** The team scopes every permission read, so a page
composed *before* the switch — its menu, its actions, the rows a policy let
through — was built for the team you just left. A fresh request is the only
honest answer, and `TeamSwitcher` issues one.

**Membership is checked on the switch, not only in the list.** The switcher lists
the teams you belong to, and `Teams::switchTo()` re-checks membership before
storing anything. A `<select>` is markup, and markup is whatever reached the
browser.

**The screens follow the team, not only the authorization.** Spatie scopes what
a person *may* do to the current team; the user and role screens scope what they
*see* the same way — the members of the current team, the global roles and the
team's own — unless the person works across every team. What may be handed out
and whose account may be touched are two more rules on top, each with one owner:
`Support\RoleGrants` and `Support\AccountGuard`. The sections below take them in
turn.

**The current team falls back rather than failing.** The session names one; if it
names a team you are no longer in — or names nothing yet — the first team you
belong to is used. Both cases are ordinary, and neither should land a person on a
page scoped to nothing.

## Switching On Two-Factor

Install Fortify, run its migration, and enable the feature. Nothing else in this
panel changes:

```php
// config/fortify.php
use Laravel\Fortify\Features;

'features' => [
    Features::twoFactorAuthentication([   // [tl! focus:start]
        'confirm' => true,
        'confirmPassword' => true,
    ]),                                   // [tl! focus:end]
],
```

```php
// app/Models/User.php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;   // [tl! focus]

class User extends Authenticatable
{
    use TwoFactorAuthenticatable;               // [tl! focus]
}
```

The card appears on the profile page the moment both are true. `'confirm' => false`
is honoured too — the module reads it off Fortify's own feature options rather
than a copy of the setting — and a secret then *is* the whole of the setup, so
the pending state does not occur.

**The code is typed into the same field on both sides of the door.** The boxes on
this card are `wire-forms`' `OtpInput`, which is what the sign-in challenge
draws too — they advance themselves, take a pasted code apart, and post one
value. There is no second opinion here about what six digits look like.

**The challenge belongs to the other half.** This module owns the *management*
card — the QR code, the recovery codes, the switch. The screen that asks for a
code while somebody signs in is the auth module's, next to the login form, and
the mechanism under both is the same Fortify. That split is deliberate rather
than accidental: this module *detects* Fortify and works fine without it, and a
package whose only job is Fortify's screens *requires* it. See
[the auth module](auth.md).

### Answering for yourself

```php
// config/wire-module-users.php
'two_factor' => false,   // never show the card, whatever is installed [tl! focus]
```

```php
'profile' => [
    'two_factor' => false,   // keep it out of the profile, mount it elsewhere [tl! focus]
],
```

The second is the one to reach for when you want the card on a security page of
your own — `@livewire(\NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication::class)`
takes no arguments, because the account it works on is always the one signed in.

## Switching On Teams

Teams need three things from your application: the setting, a team model, and a
relation on the user that reaches it. This module ships none of them, because an
application that has teams already has all three.

`php artisan wire:install` does the setting and the naming. Its **Teams** step asks
whether roles are scoped to teams, and on yes publishes the config below if it is
not there yet, sets `teams` to `true` — in the file and in the running process —
writes `WIRE_USERS_TEAM_MODEL` and, if you name a different one, the relation. Its
**Roles & permissions** step comes after and runs `permission-extended:install`,
which publishes the migration, migrates it and puts `HasRoles` on your user model.
That order is the point: Spatie's migration reads `permission.teams` as it runs,
and the roles installer runs it, so switching teams on afterwards leaves pivot
tables with no team column. Tables already made without the column count as the
answer "no" and are not asked about again; switching teams on later is a migration
you write. The team model and the relation on the user are still yours to write.

```php
// config/permission.php — spatie/laravel-permission's own, published by the
// extended package's dependency
'teams' => true,                    // [tl! focus]
'team_foreign_key' => 'team_id',
```

```php
// config/wire-module-users.php
'teams' => [
    'enabled' => 'auto',            // follows permission.teams
    'model' => 'App\Models\Team',   // [tl! focus:start]
    'relation' => 'teams',
    'label_attribute' => 'name',    // [tl! focus:end]
    'session_key' => 'wire.team',
],
```

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\PermissionExtended\Traits\HasRoles;   // this one, never Spatie's [tl! focus]

class User extends Authenticatable
{
    use HasRoles;

    /** The relation `wire-module-users.teams.relation` names. */
    public function teams(): BelongsToMany     // [tl! focus:start]
    {
        return $this->belongsToMany(Team::class);
    }                                          // [tl! focus:end]
}
```

That is enough. A switcher appears in the top bar for anyone who belongs to more
than one team, the middleware scopes every permission read on every web request,
and `Gate::allows()` starts answering per team without a single call site
changing.

### Team managers and administrators

Two roles this module knows by name, and makes the first time they are given —
with the abilities of the user and role screens (`users.viewAny`, `users.view`,
`users.create`, `users.update` and the same four for `roles`, as configured),
exact names rather than `users.*`, because a granted wildcard is a name and
`can('users.viewAny')` asks a name:

```bash
# A team's manager: the members and roles of team 3, and no other team.
php artisan wire:assign-role mia@example.com --role=team-admin --team=3

# An administrator of every team at once: the same abilities, held globally.
php artisan wire:assign-role ada@example.com --role=admin --global
```

`team-admin` (`teams.admin_role`) is a global role given inside a team, so its
abilities count in that team only. `admin` (`admin_role`) given with `--global`
counts in every team — it sees every team's members and roles, and a role
screen treats it as somebody who works across teams. It is still not a
super-admin: it can do what the role carries, and loses what is taken from the
role. The defaults are written only when the role is made; edit the role
afterwards and they stay edited. `--global` takes no `--team`, and never the
super-admin, which is `--super-admin`.

A role given by name is found among the global roles and the team's own —
preferring the team's — and never among another team's roles.

### Roles in a team, and the super-admin above them

A role here belongs to a team: the permission package puts the team on every
assignment and only counts the ones of the current team. So an account in no team
cannot be given one, and a team it is not a member of is refused, because the
role would be stored where the switcher never offers it:

```bash
php artisan wire:assign-role jane@example.com --role=editor            # in her current team
php artisan wire:assign-role jane@example.com --role=editor --team=3   # in this one
```

**The super-admin is the exception, and never a role of a team.** It can do
everything, in every team, so the permission package's gate honours only a
*global* assignment of it — one made inside a team bypasses nothing. It is given
on purpose, from the command line, and needs no team, which is why the first
administrator of a new installation can be one:

```bash
php artisan wire:assign-role admin@example.com --super-admin
```

The roles select never offers it, `--role=super-admin` is refused, and
`--super-admin` takes no `--team`.

### Who sees whom on the users screen

With teams on, the users screen lists the **members of the current team** — the
list, the view and edit pages, and every action a row carries. An account of
another team opened by its URL is a 404, not a 403, so the answer does not say it
exists, and a forged key from another team finds nothing to delete. Switching
team switches the list; somebody who belongs to no team sees nobody.

Two kinds of person see every team's members: a super-admin, and an
administrator whose `users.viewAny` comes from a **global** role — one assigned
with `assignGlobalRole()`, whose permissions count in every team. The same
ability from a role of one team manages that team only. An account created by a
team's manager joins that team; one created by somebody who works across every
team joins none, because they have not said which.

### Who sees which roles

The roles screen follows the same line. A team's manager sees the **global roles**
— shared templates, to read and not to change — and **their own team's roles**,
to change; another team's roles are not in the list, not by URL (404), and not to
a forged row action. A role a team's manager creates belongs to their team; one
created by somebody who works across every team is global, and a manager in no
team creates none. The roles select on the user form offers the same roles.
Somebody who works across every team sees and changes every role — except the
two described in [Users](users.md#two-roles-these-screens-never-hand-out-casually).

### Building access from permission bundles

An account holds **any number of roles**, and its permissions are their union.
That is the way to compose access: make small roles that each carry one bundle
of permissions, and give a person as many as their work needs. Global roles suit
bundles best — defined once, usable in every team, and assigned per team:

```php
use Spatie\Permission\Models\Role;

// Defined once, globally (no team).
Role::create(['name' => 'bundle-invoices-read'])->givePermissionTo('invoices.view');
Role::create(['name' => 'bundle-invoices-write'])->givePermissionTo(['invoices.view', 'invoices.create']);
Role::create(['name' => 'bundle-reports'])->givePermissionTo('reports.view');
```

```bash
# Given per team — Olga reads invoices and reports in team 3, and nothing more there.
php artisan wire:assign-role olga@example.com --role=bundle-invoices-read --role=bundle-reports --team=3
```

The roles select on the user form takes several at once, and the rule against
escalation reads the union too: a team's manager who holds `invoices.view`
through one bundle and `reports.view` through another may give a bundle that
carries both. A bundle is never nested inside another role — each role carries
its own permissions, and a person collects roles.

### Where the switcher comes from

It is not in the shell's layout, and this module never edits that file. It
registers a view with the chrome registry in `wire-core`, and the shell renders
whatever is registered — the same seam the media picker uses at the other end of
the document:

```php
// packages/module-users/src/WireModuleUsersServiceProvider.php
use NyonCode\WireCore\Foundation\View\PageChrome;

protected function bootTeams(): void
{
    if (! Teams::enabled()) {
        return;                                          // [tl! focus:start]
    }

    $this->app->make(Kernel::class)->appendMiddlewareToGroup('web', SetCurrentTeam::class);

    $this->app->make(PageChrome::class)->add(
        'wire-module-users::team-switcher',
        PageChrome::TOPBAR,
    );                                                   // [tl! focus:end]
}
```

`PageChrome::TOPBAR` is the region for things that have to be **seen** — a team
switcher, a tenant picker, an environment badge. `PageChrome::BODY`, the default,
is for things that only have to **exist**: a modal something else opens, a
confirmation host. Your own application can register into either, and so can any
other package:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    app(PageChrome::class)->add('partials.environment-badge', PageChrome::TOPBAR); // [tl! focus]
}
```

## Passkeys On The Profile Page

The third thing an account can be secured with, and the newest: a passkey is the
platform's own credential — Touch ID, Windows Hello, a phone, a security key.
**Laravel owns all of it**, exactly as it owns two-factor: `laravel/passkeys`
behind Fortify's `Features::passkeys()` ships the WebAuthn ceremony, the
credential rows and the browser client. This module adds the card.

```php
// config/wire-module-users.php
'profile' => [
    'passkeys' => true,   // the card, where the feature is on [tl! focus]
],

'passkeys' => 'auto',     // 'auto' looks for the packages, with the feature on [tl! focus]
```

Two lines on the user model, and they are the ones whose absence is silent —
every route answers and the key that gets registered belongs to nobody, so the
card checks for them and says which are missing:

```php
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

class User extends Authenticatable implements PasskeyUser   // [tl! focus]
{
    use PasskeyAuthenticatable;                             // [tl! focus]
}
```

The card lists what the account has, takes a name for a new one and opens the
platform's dialog, and removes one through the package's own action — so
`PasskeyDeleted` fires for anything listening.

**The sign-in button belongs to the other half**, next to the login form, for the
same reason the two-factor challenge does. See [the auth
module](auth.md#passkeys) — including the local-development trap: browse
`localhost`, never `127.0.0.1`.

## Extended Example

Everything above, in one application: a users table with a photo, teams scoping
authorization, and two-factor available to anybody who wants it.

```php
// app/Models/User.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NyonCode\WireCore\Foundation\Contracts\HasAvatar;
use NyonCode\PermissionExtended\Traits\HasRoles;
use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;

class User extends Authenticatable implements HasAvatar
{
    use HasRoles;
    use InteractsWithAvatar;        // the photo, from the configured column [tl! focus:start]
    use TwoFactorAuthenticatable;   // the secret and the recovery codes, Fortify's [tl! focus:end]

    protected $fillable = ['name', 'email', 'password', 'avatar_path'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** What the top-bar switcher lists, and what Teams::switchTo() checks against. */
    public function teams(): BelongsToMany   // [tl! focus:start]
    {
        return $this->belongsToMany(Team::class);
    }                                        // [tl! focus:end]
}
```

```php
// config/wire-module-users.php
return [
    'model' => App\Models\User::class,

    'avatar' => [
        'enabled' => 'auto',           // the column below decides [tl! focus:start]
        'column' => 'avatar_path',
        'disk' => 'public',
    ],

    'two_factor' => 'auto',            // Fortify decides

    'teams' => [
        'enabled' => 'auto',           // permission.teams decides
        'model' => App\Models\Team::class,
        'relation' => 'teams',
    ],                                 // [tl! focus:end]

    'profile' => [
        'password' => true,
        'two_factor' => true,
        'delete_account' => false,
    ],
];
```

```php
// database/migrations/…_add_profile_columns_to_users_table.php
Schema::table('users', function (Blueprint $table): void {
    $table->string('avatar_path')->nullable();   // [tl! focus]
});
```

Nothing else. Run `php artisan about` and the panel reports which of the four
optional halves this installation actually got:

```text
  Wire Module Users .......................................................
  Avatars ......................................................... enabled
  Roles ........................................................... enabled
  Teams ........................................................... enabled
  Two-factor ...................................................... enabled
  User model ..................................... App\Models\User
```

## What Each Package Owns

| Concern | Owner | This module's part |
| --- | --- | --- |
| Password hashing, the `current_password` rule | Laravel | the card that asks, and keeping the session signed in after |
| Two-factor secrets, TOTP, recovery codes, the sign-in challenge | Fortify | the three-state card that drives Fortify's actions |
| Roles, permissions, team scoping, the permission cache, global roles and the super-admin gate | nyoncode/laravel-permission-extended (1.1+), over the spatie/laravel-permission it requires | the role screens, which team this request is in, what a screen shows of other teams, what a person may hand out (`RoleGrants`) and whose account they may touch (`AccountGuard`) |
| Login, registration, password reset, e-mail verification | Fortify or Breeze | nothing — see [the admin shell](../admin/overview.md) |
| Teams themselves: the table, the model, membership | your application | the switcher over what you already have |

### Changing Two-Factor Or Passkeys Asks For The Password Again

Fortify puts `password.confirm` on every route that turns two-factor off, reads
a recovery code or removes a passkey, and defaults that on. These cards drive
Fortify's own actions over Livewire, which reaches them *around* that middleware
— so the guard is restated on the card itself rather than lost. Somebody holding
a borrowed session cannot turn a second factor off, read the TOTP secret, or
delete a passkey without knowing the password it all protects.

It reads the same window Laravel's own `RequirePassword` writes —
`auth.password_confirmed_at`, against `auth.password_timeout` — so confirming a
password anywhere in the application satisfies every one of these, and the
timeout is configured in exactly one place:

```php
// config/auth.php
'password_timeout' => 10800, // seconds; Laravel's default is three hours [tl! focus]
```

Outside that window the buttons redirect to the confirmation screen and the
card says why rather than silently showing an empty panel.

## Related

- [The Users Module](users.md) — the profile page these cards live on
- [The Admin Shell](../admin/overview.md) — the auth frame, and where a login screen goes
- [Modules](../panels/modules.md) — what a module is, and how a package ships one
