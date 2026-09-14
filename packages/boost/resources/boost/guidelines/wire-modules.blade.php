## wire-modules

Six ready-made areas, each its own composer package. A module is **not a new kind of thing** — it is a
plugin that names resources, dashboards and one menu heading, and everything downstream reads it through
the catalogue. `composer require` is the whole installation: the package's provider registers the module
with `PluginManager`, `WireCoreServiceProvider` spreads what it declares into the resource registry, the
dashboard registry and the navigation groups, and `Route::wireResources()`, the sidebar and the search
palette find the area without being told.

| Module | Package | What arrives | Needs |
| --- | --- | --- | --- |
| Users | `wire-module-users` | The user resource, its pages, a profile screen, roles where the app has them | A user model; `nyoncode/laravel-permission-extended` for the role surfaces |
| Auth | `wire-module-auth` | Sign-in, password reset, e-mail verification, the 2FA challenge, **Sign out**, one-time codes | `laravel/fortify` |
| Settings | `wire-module-settings` | A typed settings table, its cache, and the screen over it | A database connection |
| Notifications | `wire-module-notifications` | The history behind the notification bell, as a table | Laravel's `notifications` table |
| Audit | `wire-module-audit` | A screen for the trail `wire-core` already records | `HasAuditable` on the followed models |
| Media | `wire-module-media` | Uploads, folders, previews, and a picker for forms | A filesystem disk |

- **No module requires another**, and none of them requires the admin shell. Write code that works when
  only one of them is installed — `describe-module` reports which are actually registered here.
- **Every module is removable two ways**: `composer remove` takes the area out, and the module's own config
  drops or replaces what it contributed while the package stays. Where a module ships a screen over
  something the application already owns — a settings tab, a user resource — **the application's own
  declaration wins** over the contributed one. Never patch a module's classes in place to change a screen.
- **Permissions always go through `nyoncode/laravel-permission-extended`**, never bare
  `spatie/laravel-permission`. The wildcard matching, the super-admin gate and the permission-change events
  these screens assume live only in the extended package, so a user model carrying Spatie's `HasRoles`
  directly is deliberately not detected and the role and team surfaces stay off.
- **Fortify owns the security, `wire-module-auth` owns the screens.** Anything Fortify has an answer for
  stays Fortify's. One-time codes are the only authentication this stack owns: four switches under
  `wire-module-auth.codes`, all off by default.
- **Two-factor and teams in the users module are `auto` switches that look for the thing itself** — Fortify
  for 2FA, the permission package for teams — and stay off when it is absent. Do not force them on with a
  config flag the feature cannot honour.
- These are the packages whose views are **published for a consumer to open and edit**, so they use the
  `<x-wire::*>` Blade tags on purpose; they render once per page, not per row. That is the opposite of the
  rule for the render engine (`wire-core`/`forms`/`table`/`panels`/`sortable`), which must not depend on
  Blade components.
- **Writing your own module?** `packages/module-users` is the reference. Declare it like any other module;
  a package ships one by registering it from its provider instead of from the application's config.
- **`php artisan wire:user` makes an account; the installer's step makes only the first; `wire:assign-role
  <email> --role=… [--team=…]` gives roles to one that exists.** All three go
  through `WireModuleUsers\Support\Accounts` — the model (`wire-module-users.model`), the column
  names (`.fields`) and the roles are the *application's*, so never hard-code `users`, `name` or
  `email` in new code. `Accounts::superAdminRole()` is the name the permission gate checks; inventing
  one makes an administrator the gate does not recognise.
- **The super-admin is never a role among others.** It can do everything, in every team, so it is given
  only through `Accounts::makeSuperAdmin()` — a *global* assignment (`assignGlobalRole()`), which is all
  the permission gate honours with teams on — from `--super-admin` or the installer's confirmation.
  `Accounts::assign()` refuses it, `Roles::options()` never lists it, and a user form save keeps it
  rather than stripping it. Ask `$user->hasGlobalRole(Roles::superAdmin())`, never `hasRole()`.
- **With teams, a screen over people is scoped to the current team** through `Teams::scopeMembers()` —
  on the query (`modifyQueryUsing`), never a filter, so row actions resolve records through the same
  scope — and a record page finds its record the same way (`Concerns\ResolvesScopedRecord`, 404 outside).
  Whether somebody works across every team is `Teams::seesEveryTeam($ability)`: a super-admin, or the
  ability held through a *global* role (`hasGlobalPermission()`), never the same ability from a team role.
  Roles follow the same line through `Teams::scopeRoles()` (global roles + the current team's), a new
  role gets its team from `Teams::placeNewRole()`, and whether a role may be edited or deleted is
  `Roles::mayChange()` — never the super-admin role, the admin role (`wire-module-users.admin_role`)
  only for a super-admin.
- **Administrators are roles, not flags.** `team-admin` (given inside a team) and `admin` (given with
  `wire:assign-role --role=admin --global`) are made on first assignment with `Permissions::abilities()`
  — exact ability names, never `users.*`, which the permission layer reads as a name when granted.
  Resolve a role by name through `Accounts` (global or the team's own), never a bare `firstOrCreate`.
- **Nobody hands out more than they hold: `Support\RoleGrants`.** Any screen or action that writes a
  role's permissions or an account's roles offers `RoleGrants::mayGrantPermission()` /
  `mayGrantRole()` and writes `clampPermissions()` / `clampRoles()` — narrowing, not refusing, so
  what the actor may not change stays as it was. Never a local "is this allowed" beside it.
