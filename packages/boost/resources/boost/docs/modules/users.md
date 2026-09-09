---
order: 20
summary: A ready-made users area, installed as a package — the user resource, its pages, and role management wherever the application has roles.
---

# The Users Module

A [module](../panels/modules.md) that arrives as a composer package: install it and an
application has a users area, without listing a class or editing a config file.

```bash
composer require nyoncode/wire-module-users
php artisan wire-module-users:install
```

It is also the reference implementation of the packaging path — self-registration
through the plugin manager, a manifest that names its resources, and a menu group
of its own.

## How It Works

**Over your user model, never its own.** A package that brought a users table
would be unusable in every application that already has one, which is all of
them. `config('wire-module-users.model')` points at `App\Models\User` by
default, and the three columns it touches (`name`, `email`, `password`) are
configuration too, because `users` is the one table every application has
changed.

**The password is the part that has to be handled, not just displayed.** The hash
is removed from the form state rather than trusted to be hidden — an application
model without `$hidden` would otherwise send a bcrypt hash into the Livewire
snapshot and write it back re-hashed on the next save. An empty field means *keep
the current password*; a filled one is hashed on the way to the database.

**Roles appear only where they exist.** With
`nyoncode/laravel-permission-extended` installed **and** the user model carrying
**its** `HasRoles` trait, the module registers a role resource and adds a roles
field to the user form. Both conditions are checked: the package can be installed
while the model never took the trait, and every role save would then fail at its
last step.

That package is the permission layer of this stack — not an alternative to the
`spatie/laravel-permission` it requires and extends, but the wildcard matching,
super-admin gate and permission-change events these screens assume. A model on
bare Spatie is deliberately not detected; `'roles' => true` overrules the look.

**Permissions are a checklist, not a multi-select.** The two answer different
questions: a multi-select shows what you have *chosen* and hides the rest, which
is right for the two or three roles on a user, while a permission list is the
opposite — the question is always "what else is there", over a hundred names
nobody has memorised. So every permission is on the page, with a search box, a
Select all / Deselect all pair, and **groups by the resource the permission is
about**, taken from the segment before the first dot (`invoices.view`,
`invoices.*` → *invoices*).

Grouping happens only where it separates something. One group is the same list
with a heading over it, and an application whose permissions are not dotted —
`view invoices`, the other common convention — would get one group per
permission, so both cases render flat. A name with no resource in it is not
dropped: it gathers under one heading, because a permission missing from the form
is a permission nobody can grant.

**Roles are written after the record exists.** They live in a pivot table, so
they are stripped from the data on the way to `save()` and synced afterwards —
the only order that works for a *new* user, who has no id until then.

**Authorization is never this module's.** Every check in this stack is
`Gate::allows()`, which both permission packages register themselves into, so a
wildcard (`invoices.*`) and a super-admin bypass work without the module knowing
they exist. What it manages is the data; who may manage it is your policy.

**Everything optional is detected, never assumed.** Roles, avatars, two-factor
and teams each have an `auto` setting that looks for the thing itself rather than
for a flag you remembered to set — a role class *and* the trait on your model, a
column on your users table, Fortify with its feature switched on,
`permission.teams`. Where the thing is absent the surface is absent too, rather
than present and broken. `php artisan about` names all four, and so does the
installer, because a photo upload that never appears because a column is missing
is indistinguishable from a broken package until something says so.

**Avatars are a column you own.** `wire-module-users.avatar.column`
(`avatar_path` by default) holds a path on `wire-module-users.avatar.disk`, and
the shell draws it through the `HasAvatar` contract in `wire-core` — never
through this package. So the top bar shows a face without knowing where it came
from, and an application resolving Gravatar or an identity provider implements
the same interface instead of taking this module's trait. A stored value that is
*already* a URL is passed through untouched, which is what makes that work.

**The password moved off the profile.** It has its own card, which asks for the
current password first. The field with "leave empty to keep the current
password" under it is an administrator's control, and on the profile page the
administrator is the account holder — so the two screens cannot share it. An
admin editing somebody else has no current password to give, which is the whole
difference.

## Configuration

```php
// config/wire-module-users.php
'model' => 'App\Models\User',

'fields' => [
    'name' => 'name',
    'email' => 'email',
    'password' => 'password',
],

'roles' => 'auto',        // 'auto' looks; true and false answer for you [tl! focus]

'avatar' => [                                    // [tl! focus:start]
    'enabled' => 'auto',   // 'auto' looks for the column below
    'column' => 'avatar_path',
    'disk' => 'public',
    'directory' => 'avatars',
],

'profile' => [
    'password' => true,
    'two_factor' => true,
    'delete_account' => false,   // off by default — see below
],

'two_factor' => 'auto',   // 'auto' looks for Fortify, with its feature on

'teams' => [
    'enabled' => 'auto',   // 'auto' follows permission.teams
    'model' => 'App\\Models\\Team',
    'relation' => 'teams',
    'label_attribute' => 'name',
    'session_key' => 'wire.team',
],                                               // [tl! focus:end]

'navigation' => [
    'group' => 'access',
    'icon' => 'outline:users',
    'sort' => 90,
],
```

Everything in the focused block is optional, and every `auto` above answers
"no" cleanly. An application that configures none of it gets the users area it
already had.

## What You Get

| Screen | Notes |
| --- | --- |
| Users list | Name and e-mail searchable and sortable, a photo where there is one, roles column where roles exist. View, Edit and Delete per row; New user in the toolbar |
| Create user | Password required once, roles assigned on save |
| Edit user | Password optional — empty keeps the current one; roles seeded from the record |
| View user | Read-only, headed by the person's own name: photo, name, e-mail and the roles they hold |
| Roles list, create, edit | Only where roles exist. View, Edit and Delete per row; permissions are a searchable checklist grouped by resource, edited as names, so a wildcard is just a name |
| View role | Read-only, headed by the role's own name. What the role *is* on top, what it *grants* underneath — and where the permission layer matches wildcards, `invoices.*` is listed apart from the permissions it covers |

**Every button follows what the application routed.** A row action is hidden
where its page is not routed, rather than rendered as a link that 404s — an
application may mount the list and nothing else, and the honest answer to "there
is no edit page" is no Edit. Where it *is* routed with a permission
(`RoutePage::make(EditUser::class)->permission('users.update')`), the button
reads the ability off that same declaration, so a hidden button and a guarded
route cannot drift apart.

**You cannot delete yourself from the users list.** Not politeness: an
administrator who removes their own row is signed out mid-request into an
application they can no longer reach, and if they were the only one, nobody can.
Closing your own account is [the profile page's](#your-own-account) business,
where it asks twice and takes a password.

## Your Own Account

`EditProfile` is the signed-in user's own page, and it is more than one form. Each
card below the first is a Livewire component in its own right — a separate
question, a separate button, a separate thing that can fail.

That is not a layout preference. One form with three sections has to explain what
happened when the middle one does not validate, and *"your name saved but your
password did not"* is not a message a profile page should ever produce.

| Card | Component | Shown when |
| --- | --- | --- |
| Profile information | `EditProfile` itself | always — the resource's own form, minus roles and password |
| Update password | `UpdatePassword` | `profile.password` |
| Two-factor authentication | `TwoFactorAuthentication` | `profile.two_factor` **and** Fortify is installed |
| Delete account | `DeleteAccount` | `profile.delete_account` — **off by default** |

**The record is the signed-in user, never a route parameter.** A profile page
that took an id would be an edit page with a friendlier name, and the first time
somebody changed the number in the URL it would be an account takeover.

**Roles are removed from the schema**, not merely ignored on save. Somebody
editing their own account must not be able to add themselves to a role, and a
field that is rendered and then discarded is one refactor away from being a field
that is rendered and then honoured.

**Changing a password keeps you signed in.** Laravel's `AuthenticateSession`
middleware compares the session's copy of the password hash against the user's on
every request, so a change that does not move that copy along signs you out on
the very next click — on exactly the applications that enable the middleware,
which are the ones that care most. The card moves it.

**Deleting your own account is off by default**, and that is a decision rather
than caution: in an admin panel the person on this page is usually staff, and an
administrator who can remove themselves in two clicks is a support ticket. Where
accounts are self-service, turn it on. It asks twice — a dialog you have to open,
and the account's own password typed into it — and what "delete" means stays your
model's, so a soft-deleting `users` table soft-deletes.

It routes with everything else — `Route::wireResources()` gives it
`{prefix}/users/profile`, named `wire.users.profile` — and it is declared **before**
`view` in `pages()` on purpose: an unknown page key routes at `{prefix}/{name}`,
so `users/profile` and `users/{record}` are the same URL shape, and declared
after it "profile" would be looked up as a user's key and 404.

### Mounting a card on its own

Each one is a plain Livewire component with no arguments, because the record it
works on is always whoever is signed in. An application that wants the password
card on a settings page of its own puts it there and turns it off here:

```php
// config/wire-module-users.php
'profile' => [
    'password' => false,   // [tl! focus]
],
```

```blade
{{-- resources/views/settings/security.blade.php --}}
<x-wire-admin::layout title="Security">
    <div class="mx-auto max-w-3xl space-y-4">
        @livewire(\NyonCode\WireModuleUsers\Livewire\UpdatePassword::class)      {{-- [tl! focus:start] --}}
        @livewire(\NyonCode\WireModuleUsers\Livewire\TwoFactorAuthentication::class) {{-- [tl! focus:end] --}}
    </div>
</x-wire-admin::layout>
```

## Giving Your Users a Photo

Add a nullable string column to your own users table and the upload appears —
that is the whole of the `auto` detection:

```php
// database/migrations/…_add_avatar_to_users_table.php
Schema::table('users', function (Blueprint $table): void {
    $table->string('avatar_path')->nullable();   // [tl! focus]
});
```

Then say the model has a face. The contract is `wire-core`'s and the reading of
it is this module's, which is the split that lets the shell draw an avatar
without knowing this package exists:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use NyonCode\WireCore\Foundation\Contracts\HasAvatar;                 // [tl! focus:start]
use NyonCode\WireModuleUsers\Concerns\InteractsWithAvatar;

class User extends Authenticatable implements HasAvatar
{
    use InteractsWithAvatar;                                            // [tl! focus:end]

    protected $fillable = ['name', 'email', 'password', 'avatar_path'];
}
```

That is enough for three surfaces at once: a round upload control on the profile,
a face in the users list, and the picture in the top bar in place of the initial.

**Your own answer instead.** Implement `getAvatarUrl()` yourself and skip the
trait — Gravatar, an identity provider, a second table. The chrome asks the
interface:

```php
class User extends Authenticatable implements HasAvatar
{
    public function getAvatarUrl(): ?string                              // [tl! focus:start]
    {
        return 'https://www.gravatar.com/avatar/'.md5(strtolower($this->email));
    }                                                                    // [tl! focus:end]
}
```

Returning `null` is a real answer, not a failure: it is what a person who has
uploaded nothing looks like, and every surface falls back to their initial.

## Related

- [Teams and Two-Factor](teams-and-two-factor.md) — turning both on, and what each package owns
- [Modules](../panels/modules.md) — what a module is, and how a package ships one
- [The Admin Shell](../admin/overview.md) — the frame these pages render in
- [Resources](../panels/resources.md) — the contracts the module's resources implement
