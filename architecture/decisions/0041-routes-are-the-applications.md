# ADR 0041: Every Package's Routes Are A Group The Application Places

## Status

ACCEPTED — 2026-09-27. Asked for by the repo owner, in steps: routes in
`routes/web.php` rather than registered by a provider, one mechanism for every
package, full support for middleware and permissions, `wire-panels.routes.enabled`
converted too, one global mechanism, not depending on tenancy — and finally
"udělal bych obě možnosti": both a route-file call and a config description.
Lands with `wire-module-tenants`, on `feat/navigation-urls-and-parents`.
Supersedes ADR 0026 §5 in part.

## Context

Pages reached the router four different ways. Resources and dashboards through
`Route::wireResources()` in the application's route file — or, with
`wire-panels.routes.enabled`, registered by core at boot through a
`RegistersPageRoutes` seam from `wire-panels.routes` config. And two modules
registered screens from their providers: `wire-module-auth` loaded
`routes/codes.php`, `wire-module-tenants` loaded `routes/tenants.php` with its
prefix and middleware in its own config.

A route a provider registers is a route the application did not write or place:
its middleware is a package config key, a `can:` on one screen needs another,
each package solved it differently, and nothing said which of them applied to an
application that does not use tenancy.

## Decision

1. **A package's routes are a route group.** A class implementing
   `Foundation\Routing\Contracts\ProvidesRoutes` — its key, the group
   attributes it starts from (`defaults()`), whether its route names are linked
   to (`fixesNames()`), and the routes, returned keyed — registered with
   `RouteGroups` from the package's provider. The groups today: `panel`,
   `zones`, `tenant-entry` (wire-panels), `auth-codes` (wire-module-auth),
   `tenants` (wire-module-tenants). **No provider registers a route.**
2. **The application places a group, one of two ways, through one class.**
   `WireRoutes` is the only thing that calls a group's `register()`:
   - `Route::wire('key', …options)` in `routes/web.php`, inside any group
     Laravel can build — so everything Laravel supports applies, and the order
     is the file's. Named arguments are the group's options.
   - An entry of `wire-core.routes.groups`, registered by the framework's one
     route file, `packages/core/routes/web.php`, which core loads at the end of
     its boot. An entry takes Laravel's group attributes (`prefix`, `domain`,
     `middleware`, `without_middleware`, `as`, `where`, `namespace`,
     `scope_bindings`), `can`, the group's own options, and `routes` — changes
     to single routes by key. An entry's key is its group unless `uses` names
     one; then the key is the zone. `false` or `'enabled' => false` skips it.
3. **Defaults are layered so a guard survives.** An entry is the group's own
   `defaults()` under `wire-core.routes.defaults` under the entry. The panel,
   zones and tenant groups start from `['web', 'auth']`, so an entry that names
   no middleware is guarded rather than public.
4. **Conflicts are refused, not resolved.** The same route name at two
   addresses — config and a route file both placing the unnamed panel — throws
   `RouteRegistrationException`; the same route placed over itself changes
   nothing and is allowed. A group whose names are linked to (mails, screens,
   `fortify.home`) refuses a `name()` group around it (`FixedRouteNames`).
5. **Nothing assumes tenancy.** `tenant` is an option of the `panel` group; a
   page implementing `RequiresTenant` is skipped in a group without `{tenant}`;
   the installer places the `tenants` group only when tenancy is on.
6. **The names that shipped stay.** `Route::wireResources()`,
   `Route::wireResource()` and `Route::wireZoneEntry()` are the same call to
   the `panel` / `zones` group. The unreleased `wireZones`, `wireTenants`,
   `wireAuthCodes`, `wireTenantEntry` are gone; `wire-panels.routes.enabled` and
   its zone keys, `ConfiguredRoutes` and `RegistersPageRoutes` are removed.
7. **The installer writes the call.** `Foundation\Setup\RoutesFile` decides
   whether `routes/web.php` already places a group (`wires()`), and each
   package's setup step also counts an entry of `wire-core.routes.groups`
   (`WireRoutes::configured()`).

## Consequences

- One mechanism, one vocabulary: a new package ships a `ProvidesRoutes` and a
  setup step, and gets both placements, the conflict checks and the keyed
  routes without writing any of it.
- Groups from config are matched before `routes/web.php`; an application with a
  catch-all under the same prefix places that group in its route file.
- An application that had `wire-panels.routes.enabled => true` moves its zones to
  `wire-core.routes.groups`; one using the code flows adds
  `Route::wire('auth-codes')` unless `wire:install` does (CHANGELOG, upgrade
  guide).
- Asset routes a bundle is served from (`Bundle::servedByRoute()`) are
  infrastructure rather than screens, and are not affected.
