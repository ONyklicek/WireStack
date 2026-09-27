# ADR 0040: Tenants in the URL

## Status

PROPOSED — 2026-09-27. Requested by the repo owner after the Filament
comparison ("a podpora Multi-Tenant?"). Builds on ADR 0027 (zones), 0028 (no
panel object), 0039 (clusters) and the V2.4 tenant scope. Nothing here is
implemented yet; §8 is the order it would land in.

## Context

What exists answers **whose rows**, and answers it well:

- `Core\Tenancy\Tenancy` + `TenantScope` + `BelongsToTenant` constrain every
  tenant-owned model, and constrain it to *nothing* when no tenant resolves —
  the fail-safe that makes a tenancy bug an empty page rather than a leak.
- `TenantResolver` is the application's answer to "which tenant is this". The
  default is `NullTenantResolver`, so the application writes the resolver,
  usually `auth()->user()?->tenant_id`.
- A resource can sit on `routeDomain('{tenant}.example.com')`, and the
  parameter reaches the resolver like any other.

What does not exist is everything a person *sees* of tenancy — and one thing
exists twice:

| Measured | Where | What it means |
| --- | --- | --- |
| No tenant segment in a path | `ResourceRoutes`, zones | `/app/{tenant}/orders` has to be written by hand, and then every link the framework builds is missing the parameter |
| `urlFor()` answers `null` for a route missing a parameter | `ResourceRoutes::urlFor()` | a menu under a `{tenant}` route renders as unlinked rows, silently — the same for `X::url()`, search results, breadcrumbs, cluster tabs |
| Nothing checks that a person belongs to the tenant in the URL | — | the resolver trusts whatever it is given; changing `/acme/` to `/globex/` in the address bar is the attack |
| No tenant switcher | `PageChrome::TOPBAR` has none | the region exists for exactly this and nothing fills it |
| **A second "current" already exists** | `wire-module-users` `Support\Teams`: session key, `SetCurrentTeam` middleware on `web`, a `TeamSwitcher` in the top bar | "which team am I in" (the permission layer's scope) and "which tenant am I in" (the data scope) are two answers, kept in two places, and nothing stops them disagreeing |
| Livewire updates lose route parameters | ADR 0027 | `Route::currentRouteName()` is `livewire.update` on a round trip — a tenant read from the route is gone on the second render, exactly as a zone is |

Filament answers this with `$panel->tenant(Team::class)` — tenancy as panel
configuration. ADR 0028 §1b refuses that shape here: a panel object carrying
tenancy is the drift that pulls branding, auth and middleware in after it. The
answer has to be routes, a middleware and a seam, the way zones were.

## Decision

### 1. One owner of "the current tenant": `CurrentTenant` (core)

`Core\Tenancy\CurrentTenant` — a request-scoped holder (scoped binding, so
Octane and queue workers start each request or job empty) with `set()`,
`get(): ?Model`, `key(): int|string|null` and `forget()`.

The default `TenantResolver` becomes `CurrentTenantResolver`, which answers
`CurrentTenant::key()`. So the moment something sets the current tenant, the
existing `TenantScope` scopes to it with no application code at all. An
application that binds its own resolver keeps it — binding one is the explicit
opt-out, as it is today.

Everything else that asks "which tenant" asks this object: the scope (through
the resolver), the switcher, the users module's team scope (§5), and code of
the application's own. Two holders of the same answer is the defect §5 removes,
not a pattern to repeat.

**Outside a request** the holder is empty and the scope therefore returns
nothing, by the V2.4 rule. `Tenancy::runAs(Model $tenant, Closure $callback)`
sets it for the length of a callback and restores what was there — the one way
a job, a command or a test works inside a tenant, rather than each one poking
the holder and forgetting to clear it.

### 2. The tenant is a route parameter named `tenant`, in a path or a domain

Routes stay the application's (ADR 0026). A tenant zone is a group whose
prefix or domain carries `{tenant}`, with the middleware of §3:

```php
Route::name('app.')
    ->prefix('app/{tenant}')
    ->middleware(['web', 'auth', 'wire.tenant'])
    ->group(fn () => Route::wireResources());
// app.wire.orders.index  →  /app/acme/orders
```

and from config, one key more on a zone:

```php
'zones' => [
    'app' => ['prefix' => 'app', 'tenant' => true],   // → app/{tenant}, wire.tenant added
],
```

`{tenant}.example.com` works the same way: the parameter has one name wherever
it sits, so one middleware reads it. A path is the default recommendation — it
needs no DNS and no cookie domain, and a person in two tenants can hold both in
two tabs.

### 3. `wire.tenant`: identify, authorize, and make every link carry it

`Http\Middleware\IdentifyTenant` (wire-panels, alias `wire.tenant`) does four
things, in this order:

1. **Find** the tenant by the model's route key (`config('wire-core.tenancy.model')`,
   `getRouteKeyName()` — a slug if the model says so), querying without the
   tenant scope, since the tenant model is not tenant-owned.
2. **Authorize** through the user: `HasTenants::canAccessTenant($tenant)`
   (§4). Not a member, or no such tenant, is a **404**, not a 403 — a 403 would
   confirm to a stranger that `globex` exists.
3. **Set** `CurrentTenant`.
4. **`URL::defaults(['tenant' => $tenant->getRouteKey()])`**, and forget the
   parameter on the route so no page's `mount()` receives it.

Step 4 is what makes the rest free. Laravel fills a missing `{tenant}` from the
defaults, so `urlFor()`, `X::url()`, the menu, search results, breadcrumbs,
cluster tabs, `route()` in application code and redirects after save all point
at the current tenant without one line of them changing — and `urlFor()`'s
"null when a parameter is missing" stops firing for the parameter everyone is
missing.

**Livewire round trips** would lose all of it — no `{tenant}` on
`livewire/update`. The middleware is registered with
`Livewire::addPersistentMiddleware()`, which re-runs it on every update against
the *original* route and its parameters. That is the Livewire-native answer to
the trap ADR 0027 had to solve by hand for zones, and it is the reason the
tenant, unlike the zone, needs no public property on every page.

### 4. Membership is the user model's answer: `HasTenants`

```php
use NyonCode\WirePanels\Contracts\HasTenants;

class User extends Authenticatable implements HasTenants
{
    public function tenants(): iterable            { return $this->teams; }
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->teams()->whereKey($tenant->getKey())->exists();
    }
    public function defaultTenant(): ?Model        { return $this->currentTeam ?? $this->teams->first(); }
}
```

On the user model, beside `HasPreferredZone` (ADR 0027 zone entry), because it
is a fact about the person. A user model that does not implement it inside a
tenant zone is refused at the first request with a message naming the
contract — never "every signed-in user may enter every tenant", which is the
silent default that would ship.

### 5. Teams become one reading of the current tenant, not a second one

When the users module's teams model **is** the tenancy model — the common case,
"a tenant is a team" — `Support\Teams::currentId()` answers
`CurrentTenant::key()` first and falls back to its session only outside a
tenant zone. `SetCurrentTeam` then scopes the permission registrar to the
tenant in the URL, so *whose data* and *which roles apply* cannot disagree. The
team switcher, in a tenant zone, becomes links to the same page in another
tenant (§6) instead of a session write that the next URL would contradict.

When they are different models — tenants are companies, teams are
departments inside one — both stay, and nothing here merges them. That case is
documented, not guessed.

### 6. The switcher and the way in

- **Switcher.** A `PageChrome::TOPBAR` contribution from wire-panels, drawn only
  in a tenant zone: the current tenant's name, and the person's other tenants
  (`HasTenants::tenants()`) as links to **the same route with another
  `tenant`** — falling back to that tenant's zone entry when the current page
  is a record the other tenant does not have (a record page would 404 there
  anyway, and landing on a 404 is not switching). The users module's team
  switcher defers to it inside a tenant zone rather than drawing a second one.
- **Entry.** `/app` — the zone without a tenant — redirects to
  `defaultTenant()` and from there, by the existing `PanelEntry`, to the first
  page the person may open. No tenant at all is a view that says so, publishable
  like the zone picker; registering a tenant from it is §9's.
- **Signing in** lands on `/app` when `fortify.home` says so, which is what
  `wire:install` would write for a tenant zone.

### 7. What stays exactly as it is

- `TenantScope`, `BelongsToTenant`, the fail-safe, the column config.
- An application with its own `TenantResolver` and no tenant zone: untouched.
- Zones: a tenant zone *is* a zone; `Zone::current()`, `linkedOnly`,
  memberships by `only`/`except`, the zone picker, all unchanged.
- Pins and recent pages stay per zone, not per tenant — they store registered
  keys, and "Invoices" is the same screen in every tenant.

### 8. Order of work

| # | Step | Package | Proves it |
| --- | --- | --- | --- |
| 1 | `CurrentTenant`, `CurrentTenantResolver` as default, `Tenancy::runAs()` | core | unit: scope follows the holder; jobs outside `runAs` see nothing; Octane-style reuse starts empty |
| 2 | `HasTenants`, `IdentifyTenant` (+ alias, persistent middleware), `'tenant' => true` on config zones | panels | feature: 404 for stranger and for unknown slug; URL defaults reach `urlFor`, `X::url()`, menu URLs; parameter not passed to `mount()`; refused user model without the contract |
| 3 | Zone entry and the no-tenant view | panels | feature: `/app` → default tenant → first page; nothing → the view |
| 4 | Switcher in `PageChrome::TOPBAR` | panels | feature + driver `verify-tenants`: switch keeps the page, falls back on a record page, survives `wire:navigate`, links stay in-tenant after a Livewire round trip |
| 5 | Teams read `CurrentTenant` when the models match; the team switcher defers | module-users | feature: roles scoped to the URL's tenant; session ignored inside a tenant zone; two-model case unchanged |
| 6 | Workbench `app/{tenant}` zone over two seeded teams; docs `docs/panels/tenancy.md` (EN/CS), `start/authorization.md` § Multi-tenancy rewritten around it; boost guideline | all | `verify-tenants` in the sweep; docs gates |

### 9. Deliberately not decided here

- **Tenant registration and a tenant profile page.** Screens, so a module's
  (`wire-module-users` owns teams already), and a separate decision about who
  may create a tenant.
- **Per-tenant branding.** The theming plan's (`architecture/plans/theming-and-customisation.md`);
  `CurrentTenant` is what it would read.
- **Tenant-aware menus.** Already possible — an entry's `visible()` closure can
  ask `CurrentTenant` — so nothing is added until a case shows it is not
  enough.
- **Database-per-tenant.** A connection switch is a different mechanism from a
  scope, and packages like stancl/tenancy own it; `CurrentTenant` is the seam
  such a bridge would set, and nothing here assumes one database.

## Consequences

- A tenant is one parameter, one middleware and one method on the user model;
  every link the framework builds carries it without being told.
- Changing the tenant in the address bar is refused by construction, as a 404.
- "Current team" and "current tenant" stop being two answers when they are the
  same thing, and stay two when they are not.
- A job that forgets `runAs()` sees no rows — loud in development, never a leak.
- Nothing becomes panel configuration: the tenant zone is a route group, as
  every other zone is (ADR 0028 holds).
