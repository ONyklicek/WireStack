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
    'passkeys' => true,
    'delete_account' => false,   // off by default — see below
    'menu_item' => true,         // the profile link in the shell's user menu
],

'two_factor' => 'auto',   // 'auto' looks for Fortify, with its feature on
'passkeys' => 'auto',     // 'auto' looks for laravel/passkeys, with its feature on

'teams' => [
    'enabled' => 'auto',   // 'auto' follows permission.teams
    'model' => 'App\\Models\\Team',
    'relation' => 'teams',
    'label_attribute' => 'name',
    'session_key' => 'wire.team',
],                                               // [tl! focus:end]

'navigation' => [
    'group' => 'access',
    'label' => null,   // null uses the module's own group heading
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

## Who May Reach These Screens

**These screens require an ability, and that is new in 2.0.** The user edit form
sets other people's passwords and assigns their roles, so a screen open to
whoever the panel's `auth` middleware admitted is a screen that turns any
account into an administrator. Before 2.0 they shipped open, and the failure was
silent — the panel looked correct while serving user administration to everyone
who could sign in.

So they now fail closed. Each screen names an ability, it becomes Laravel's own
`can:` middleware on the route, and the button that leads there is hidden by the
same declaration:

```php
// config/wire-module-users.php — the shipped defaults [tl! focus:start]
'permissions' => [
    'users' => [
        'viewAny' => 'users.viewAny',
        'view' => 'users.view',
        'create' => 'users.create',
        'update' => 'users.update',
    ],

    'roles' => [
        'viewAny' => 'roles.viewAny',
        'view' => 'roles.view',
        'create' => 'roles.create',
        'update' => 'roles.update',
    ],
], // [tl! focus:end]
```

Nothing here re-implements an authorization check — `Gate` answers every one of
these, which is why a wildcard (`users.*`), a policy, and the super-admin bypass
in `nyoncode/laravel-permission-extended` all work without this module knowing
they exist. On that package a super-admin passes regardless.

**On an installation with no such ability defined, these screens answer 403.**
That is deliberate: a visible problem with an obvious fix is better than a silent
one. Define the abilities, grant them to a role, or open a screen again by naming
`null`:

```php
'permissions' => [
    'users' => [
        'viewAny' => 'users.viewAny',
        'view' => null,              // open to anyone the panel admitted [tl! focus]
        'create' => 'users.create',
        'update' => 'users.update',
    ],
],
```

An empty string counts as `null` rather than as an ability nobody can hold —
that is the shape an unset `.env` produces, and `can:` on it would deny everyone
with no way to grant it.

**The profile page is deliberately not on this list.** It is the signed-in
person's own account, resolved from `Auth::user()` rather than from the URL, so
an administrative ability in front of it would lock every user out of their own
password and two-factor settings.

`php artisan about` reports which way this installation is set, and the
installer says so at install time — an installation that opened these screens
should be able to see that it did.

### Roles Are Checked Where They Are Written, Too

The route guard is not the only lock. Assigning a role goes through a second
check at the pivot write itself, because the ways to reach a form are many and
not all of them are routes — a bulk action, a wizard step, an application's own
page composing `SyncsRoles`. A save that *changes* the roles requires the
`users.update` ability; a save that leaves them as they were does not, so fixing
a typo in somebody's name never strips their roles.

### A Changed Address Stops Being Verified

Fortify's `UpdateUserProfileInformation` nulls `email_verified_at` and mails a
fresh notification when somebody edits their address. This module replaces that
action with its own form, so it restates the rule rather than losing it —
otherwise a person could type an address they do not control and stay flagged
verified on it, and anything behind Laravel's `verified` middleware, or any
policy asking `hasVerifiedEmail()`, would then apply to an address nobody had
proven.

The rule lives on the **model**, not in a form hook, and that is deliberate:
three screens here write the column — the profile page, the admin edit form, the
create form — and `Form::afterSave()` holds exactly one closure, so a rule
installed there is one the next page to add a hook silently removes. An
application's own page, or a console command, would never have been covered.

It is narrow. It fires only when the address actually changed, and it stands
aside whenever the same save writes `email_verified_at` itself — a seeder, a
migration backfill, or an admin tool marking an address verified has said what
it wants:

```php
// Respected: the caller had an opinion.
$user->forceFill(['email' => $new, 'email_verified_at' => now()])->save();

// Cleared, and a fresh verification notification goes out.
$user->forceFill(['email' => $new])->save();
```

Turn it off where an application clears the flag in its own way:

```php
// config/wire-module-users.php
'reverify_on_email_change' => false, // [tl! focus]
```

## Adapting The Screens

`fields` maps three **column names**, and nothing more. It is there for a users
table whose columns were named before this module arrived — it answers "which
column holds the name", never "how many fields the name is":

```php
'fields' => ['name' => 'full_name', 'email' => 'login', 'password' => 'password'],
```

Everything else about the list, the form and the detail page is adjusted through
[hooks](../core/plugins/hooks.md#scoping-a-hook-to-one-component) scoped to the
key this module registered, `users`. **Subclassing `UserResource` does not
work**: a subclass keeps the parent's key, and the registry refuses two classes
on one key — which is [what makes a module adjustable rather than
forkable](../panels/modules.md#a-package-adds-it-does-not-overwrite).

### A First Name And A Last Name In Two Columns

The case the config cannot express, and the hooks can. Four steps, and the last
one is the only one this module has anything to do with.

**1. The columns.** An ordinary migration; keep or drop `name` as you like,
because after step 2 nothing reads it as a column:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->string('first_name')->after('id')->default('');        // [tl! focus:start]
    $table->string('last_name')->after('first_name')->default('');  // [tl! focus:end]
});

// Backfill before dropping anything: the halves of a name are not recoverable
// from a column that is already gone.
DB::table('users')->orderBy('id')->each(function (object $user): void {
    [$first, $last] = array_pad(explode(' ', (string) $user->name, 2), 2, '');

    DB::table('users')->where('id', $user->id)->update([
        'first_name' => $first,
        'last_name' => $last,
    ]);
});
```

**2. The model.** Both columns fillable, and an accessor so everything that only
*displays* a name keeps working — the shell's corner, the avatar's initials, the
audit log's actor column all read `$user->name` and never care where it came
from:

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected $fillable = ['first_name', 'last_name', 'email', 'password'];

protected function name(): Attribute                                      // [tl! focus:start]
{
    return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
}                                                                         // [tl! focus:end]
```

**3. The config line.** Point `fields.name` at the column the list should sort
on — a real column, because `sortable()` and the default sort become SQL, and an
accessor is not one:

```php
// config/wire-module-users.php
'fields' => ['name' => 'last_name', 'email' => 'email', 'password' => 'password'],
```

**4. The screens**, which is where the hooks come in:

```php
namespace App\Wire\Plugins;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Plugin\Contracts\Plugin;
use NyonCode\WireCore\Core\Plugin\Hooks\FormConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\InfolistConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableComposingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireForms\Components\Field;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WireTable\Columns\TextColumn;

final class SplitUserName implements Plugin
{
    public function getId(): string
    {
        return 'split-user-name';
    }

    public function register(PluginManager $manager): void
    {
        $manager->hook(Hook::FormConfiguring, function (FormConfiguringPayload $payload): FormConfiguringPayload {   // [tl! focus:start]
            $payload->schema = $this->splitName($payload->schema);

            return $payload;
        }, for: 'users');                                                                                            // [tl! focus:end]

        $manager->hook(Hook::TableComposing, function (TableComposingPayload $payload): TableComposingPayload {
            $payload->columns = array_map(
                fn (object $column): object => $column->getName() === UserResource::field('name')
                    // One column, both halves in it: the search reads either      [tl! focus:start]
                    // column, the sort stays on the real one underneath.
                    ? TextColumn::make(UserResource::field('name'))
                        ->label(__('Name'))
                        ->state(fn (Model $record): string => trim($record->first_name.' '.$record->last_name))
                        ->searchable(['first_name', 'last_name'])
                        ->sortable()                                            // [tl! focus:end]
                    : $column,
                $payload->columns,
            );

            return $payload;
        }, for: 'users');
    }

    public function boot(PluginManager $manager): void {}

    /**
     * The module's schema with its one name input replaced by two.
     *
     * Recursive, because the resource groups its fields into sections — the same
     * reason the profile page's own filter is.
     *
     * @param  array<int, mixed>  $schema
     * @return array<int, mixed>
     */
    private function splitName(array $schema): array
    {
        $name = UserResource::field('name');
        $out = [];

        foreach ($schema as $component) {
            if ($component instanceof LayoutComponent) {
                $out[] = $component->schema($this->splitName($component->getSchema()));

                continue;
            }

            if ($component instanceof Field && $component->getName() === $name) {
                $out[] = TextInput::make('first_name')->label(__('First name'))->required();   // [tl! focus:start]
                $out[] = TextInput::make('last_name')->label(__('Last name'))->required();     // [tl! focus:end]

                continue;
            }

            $out[] = $component;
        }

        return $out;
    }
}
```

Registered the way every application plugin is:

```php
// config/wire-core.php
'plugins' => [
    App\Wire\Plugins\SplitUserName::class,
],
```

The detail page is the same three lines with a third hook, and here the accessor
from step 2 does the work — an infolist only displays, so nothing is searched or
sorted:

```php
$manager->hook(Hook::InfolistConfiguring, function (InfolistConfiguringPayload $payload): InfolistConfiguringPayload {
    $payload->schema = array_map(
        fn (object $entry): object => $entry->getName() === UserResource::field('name')
            ? TextEntry::make('name')->label(__('Name'))   // the accessor, not a column [tl! focus]
            : $entry,
        $payload->schema,
    );

    return $payload;
}, for: 'users');
```

Three things are worth knowing before you run it:

- **`Hook::FormConfiguring` reaches the profile page too.** [Your Own
  Account](#your-own-account) composes this same resource form and takes two
  fields out of it, so both screens get the pair from one callback.
  `Hook::ExportConfiguring` does the same for what a download contains.
- **The accessor alone would not have been enough.** It fills the form and the
  detail page perfectly well, and then the list's `searchable()` and
  `defaultSort()` ask the database for a column that does not exist — which is
  why step 3 points the module at a real one.
- **Fortify and the permission packages never see the name**, so nothing else in
  the stack has an opinion about how many columns it is.

The wording on these screens is a published translation file and their markup a
published view — `wire-module-users::translations` and `…::views`, with what
each costs in [Theming → Localization](../start/theming.md#localization) and
[Overriding Views](../start/theming.md#overriding-views).

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
| Passkeys | `PasskeyManagement` | `profile.passkeys` **and** Fortify routes `Features::passkeys()` |
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
