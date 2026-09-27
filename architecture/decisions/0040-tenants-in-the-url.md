# ADR 0040: Tenants — In the URL, Isolated, With Teams Inside

## Status

PROPOSED — 2026-09-27. Requested by the repo owner after the Filament
comparison ("a podpora Multi-Tenant?"); the five open choices were settled with
the owner the same day (§ Decisions confirmed). Builds on ADR 0027 (zones),
0028 (no panel object), 0039 (clusters) and the V2.4 tenant scope. Nothing here
is implemented yet; §10 is the order it lands in.

## Decisions confirmed

| Question | Answer |
| --- | --- |
| Tenant in a path or a domain? | **Both** — one parameter, one middleware (§3) |
| Is a team the tenant? | **No. A tenant is a company; teams are departments or projects inside one.** A person may belong to company A only, and to three of its five projects (§6) |
| A stranger in another tenant's URL? | **404** (§4) |
| Creating a tenant and managing its members? | **In scope** (§8) |
| One database, or one per tenant? | **Both, behind one seam** (§2) |

## Context

What exists answers **whose rows**, and answers it well:

- `Core\Tenancy\Tenancy` + `TenantScope` + `BelongsToTenant` constrain every
  tenant-owned model, and constrain it to *nothing* when no tenant resolves —
  the fail-safe that makes a tenancy bug an empty page rather than a leak.
- `TenantResolver` is the application's answer to "which tenant is this"; the
  default `NullTenantResolver` answers nothing.
- A resource can sit on `routeDomain('{tenant}.example.com')`.

What does not exist is everything a person *sees* of tenancy — and one thing
exists in the wrong shape:

| Measured | Where | What it means |
| --- | --- | --- |
| No tenant segment in a path | `ResourceRoutes`, zones | `/app/{tenant}/orders` is written by hand, and then every framework link lacks the parameter |
| `urlFor()` answers `null` for a missing parameter | `ResourceRoutes::urlFor()` | under `{tenant}` the menu, `X::url()`, search results, breadcrumbs and cluster tabs all go unlinked, silently |
| Nothing checks membership | — | changing `/acme/` to `/globex/` in the address bar is the attack |
| No tenant switcher | `PageChrome::TOPBAR` | the region exists for exactly this |
| Isolation is one strategy, hard-wired | `TenantScope` | a customer whose contract requires a separate database cannot be served |
| Queued work has no tenant | — | a job dispatched inside tenant A runs with none, and the fail-safe returns no rows — correct, and useless |
| `wire-module-users` keeps one "current team" per session | `Support\Teams`, `SetCurrentTeam`, `TeamSwitcher` | teams are the permission layer's scope, not the data's; with a tenant above them the current team must belong to the current tenant, and today nothing knows there is one |
| Livewire updates lose route parameters | ADR 0027 | a tenant read from the route is gone on the second render, as a zone was |

Filament answers this with `$panel->tenant(Team::class)`. ADR 0028 §1b refuses
tenancy as panel configuration; the answer is routes, a middleware and seams,
the way zones were.

## Decision

### 1. One owner of "the current tenant": `CurrentTenant` (core)

`Core\Tenancy\CurrentTenant` — request-scoped (a scoped binding, so an Octane
request or a queued job starts empty): `get(): ?Model`, `key()`, and
`enter(Model)` / `leave()`, which also drive the isolation of §2.

The default `TenantResolver` becomes `CurrentTenantResolver`, answering
`CurrentTenant::key()`, so the existing `TenantScope` follows the current tenant
with no application code. An application binding its own resolver keeps it —
the explicit opt-out it is today.

`Tenancy::runAs(Model $tenant, Closure $callback)` enters a tenant for the length
of a callback and restores what was there. It is the one way a command, a seeder
or a test works inside a tenant.

### 2. Isolation is a strategy: rows, or a database

```php
// config/wire-core.php
'tenancy' => [
    'enabled' => true,
    'model' => App\Models\Company::class,
    'isolation' => 'column',          // 'column' | 'database' | a class name
    'column' => 'tenant_id',
    'database' => [
        'connection' => 'tenant',     // the connection a tenant's models use
        'name' => fn (Model $tenant) => 'tenant_'.$tenant->getKey(),
        'central' => 'mysql',         // where tenants, users and memberships live
    ],
],
```

`Core\Tenancy\Contracts\IsolatesTenants` — `enter(Model $tenant): void`,
`leave(): void`. `CurrentTenant::enter()` calls it; nothing else does.

- **`ColumnIsolation`** — today's behaviour, unchanged: `enter()` does nothing,
  `TenantScope` does the work through the resolver. The default.
- **`DatabaseIsolation`** — `enter()` points the `tenant` connection at the
  tenant's database (`database.name`, a closure or an attribute of the tenant)
  and purges the resolved connection; `leave()` resets it. Tenant-owned models
  use `BelongsToTenantDatabase` (`$connection = 'tenant'`) instead of the column
  trait; tenants, users and memberships stay on `central`. With no tenant
  entered, the `tenant` connection points at nothing and the first query
  throws — the same fail-safe direction as the empty scope, louder.
- **A class name** — an application's own, or a bridge to stancl/tenancy,
  which already owns the harder parts (database creation per driver, tenant
  caches, filesystem roots). The seam is deliberately the size of `enter`/`leave`
  so such a bridge is a dozen lines.

What database isolation also needs, and this ADR includes:

- `php artisan wire:tenants:migrate [--tenant=] [--fresh] [--seed]` — runs the
  application's `database/migrations/tenant` against each tenant's database
  inside `runAs()`; `wire:tenants:create {tenant}` creates one (MySQL,
  PostgreSQL and SQLite first; the statement per driver is the adapter's).
- Creating a tenant (§8) creates its database and migrates it, in a job.

Both strategies are covered by the same feature suite run twice — a strategy
the tests do not drive is a strategy that is not supported.

### 3. The tenant is a route parameter named `tenant`, in a path or a domain

Routes stay the application's (ADR 0026). A tenant zone is a group whose
prefix **or** domain carries `{tenant}`, with the middleware of §4:

```php
Route::name('app.')
    ->prefix('app/{tenant}')                 // or ->domain('{tenant}.example.com')
    ->middleware(['web', 'auth', 'wire.tenant'])
    ->group(fn () => Route::wireResources());
```

and from config, one key more on a zone:

```php
'zones' => [
    'app' => ['prefix' => 'app', 'tenant' => 'path'],          // app/{tenant}/…
    'portal' => ['domain' => 'example.com', 'tenant' => 'domain'], // {tenant}.example.com
],
```

The parameter has one name wherever it sits, so one middleware reads it. Docs
recommend the path (no wildcard DNS or certificate, two tenants in two tabs) and
document the domain's costs: wildcard DNS and TLS, `SESSION_DOMAIN`, and a
custom domain per tenant as a later, separate step.

### 4. `wire.tenant`: identify, authorize, enter, and make every link carry it

`Http\Middleware\IdentifyTenant` (wire-panels, alias `wire.tenant`), in order:

1. **Find** the tenant by the model's route key (`getRouteKeyName()` — a slug
   when the model says so), on the central connection and without the scope.
2. **Authorize** through the user: `HasTenants::canAccessTenant($tenant)`.
   Not a member, or no such tenant, is **404** — a 403 would confirm to a
   stranger that `globex` exists.
3. **Enter** it: `CurrentTenant::enter()` — the scope or the database follows.
4. **`URL::defaults(['tenant' => $tenant->getRouteKey()])`**, and forget the
   parameter on the route so no page's `mount()` receives it.

Step 4 is what makes the rest free: `urlFor()`, `X::url()`, the menu, search
results, breadcrumbs, cluster tabs, `route()` in application code and redirects
after save all point into the current tenant without one line of them changing.

**Livewire round trips** keep it: the middleware is registered with
`Livewire::addPersistentMiddleware()`, which re-runs it on every update against
a request rebuilt from the original path — so `{tenant}` is there again
(measured in `Mechanisms/PersistentMiddleware`). No page needs a public
property for the tenant, unlike the zone.

### 5. Membership is the user model's answer: `HasTenants`

```php
use NyonCode\WirePanels\Contracts\HasTenants;

class User extends Authenticatable implements HasTenants
{
    public function tenants(): iterable
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies()->whereKey($tenant->getKey())->exists();
    }

    public function defaultTenant(): ?Model
    {
        return $this->companies()->first();
    }
}
```

Beside `HasPreferredZone`, because it is a fact about the person.
`Concerns\InteractsWithTenants` implements all three over a `tenants`
belongs-to-many and a `tenant_user` pivot, which §8 migrates — the ordinary
case written once. A user model without the contract inside a tenant zone is
refused at the first request, naming the contract; "everyone may enter every
tenant" is never the default.

### 6. Teams live inside a tenant

A tenant is the company; a team is a department or a project **of one
company**. The owner's example is the shape to serve: a person in company A
only, and in three of A's five projects.

- **A team belongs to a tenant.** The team model uses `BelongsToTenant` (or
  lives in the tenant's database under database isolation), so the existing
  scope already narrows every team query to the current company — the switcher,
  the members screen, role assignment — with nothing added.
- **The current team is per tenant.** `Support\Teams::currentId()` stores and
  reads its session value under a key that includes the tenant
  (`wire.team.{tenant}`), and a stored team that does not belong to the current
  tenant is ignored. Switching company never lands you in a project of the
  other one.
- **The team switcher lists the teams of the current tenant the person belongs
  to** — the three of five — because it already asks the membership relation,
  and that relation is now scoped.
- **Roles.** The permission registrar keeps scoping roles by **team**, as the
  users module does today (roles per project). What a company needs above that
  — "admin of company A, and only of A" — is a role scoped to the **tenant**.
  `nyoncode/laravel-permission-extended` has global roles (team `null`), and a
  global role would follow the person into every company they belong to. So
  this ADR requires one addition to that package, not to this repository:
  **tenant-scoped roles** (a role assigned for a tenant, applying in all of that
  tenant's teams and nowhere else), resolved before team roles. Until it lands,
  a tenant-wide role is expressed by assigning it in each of the tenant's teams,
  which §8's membership screen does for you.

Data scoped by team as well as tenant (a project's own records) is possible
with the same mechanism — a `BelongsToTeam` scope over the current team — and is
left to the application until a case asks for it in the framework.

### 7. The switcher and the way in

- **Switcher.** A `PageChrome::TOPBAR` contribution from wire-panels, drawn in a
  tenant zone when the person has more than one tenant: the current company,
  and the others as links to **the same route with another `tenant`**, falling
  back to that tenant's entry when the current page is a record the other one
  does not have. The team switcher sits beside it and lists the current
  company's teams.
- **Entry.** `/app` — the zone without a tenant — redirects to
  `defaultTenant()`, then by the existing `PanelEntry` to the first page the
  person may open. No tenant at all goes to the registration of §8 when it is
  allowed, and to a page that says so when it is not.
- **Signing in** lands on `/app` when `fortify.home` says so, which `wire:install`
  writes for a tenant zone.

### 8. Creating and running a tenant (in scope)

Screens, so a module's: `wire-module-tenants` (depends on the stack, not on
`wire-module-users`; uses it when present for the member picker and roles).

- **Register a company.** `config('wire-module-tenants.registration')`:
  `'anyone'` (a signed-in person without a tenant may create one — SaaS),
  `'ability'` (only with `tenants.create` — an internal system), or `false`.
  Name and slug (validated against the route: unique, not a reserved segment),
  the creator becomes its owner. Under database isolation the database is
  created and migrated by a queued job, and the person lands on a waiting
  screen until it has finished.
- **Company profile.** Name, slug (changing it changes every URL, so it warns and
  keeps the old slug as a redirect for thirty days), owner transfer, deletion
  (soft, with a confirmation that names the company).
- **Members.** Invite by e-mail (a signed link, the auth module's one-time code
  flow when installed), remove, and assign the company's teams and roles. The
  last owner cannot be removed — the rule `AccountGuard` already follows for the
  last super-admin.

### 9. Queued work carries its tenant

A job dispatched inside a tenant records the tenant key in its payload
(`Queue::createPayloadUsing`), and a job middleware re-enters it with `runAs()`
before `handle()`. A job dispatched outside any tenant runs outside one, and the
fail-safe applies. Notifications, exports and imports — all queued — therefore
work in the tenant they were started in, under either isolation.

### 10. What stays exactly as it is

- `TenantScope`, `BelongsToTenant`, the fail-safe, the column config.
- An application with its own `TenantResolver` and no tenant zone: untouched.
- Zones: a tenant zone *is* a zone.
- Pins and recent pages stay per zone — they store registered keys, and
  "Invoices" is the same screen in every company.

### 11. Order of work

| # | Step | Package | Proves it |
| --- | --- | --- | --- |
| 1 | `CurrentTenant`, `CurrentTenantResolver`, `runAs()`, `IsolatesTenants` + `ColumnIsolation` | core | unit: scope follows the holder; outside `runAs` nothing; a reused container starts empty |
| 2 | `HasTenants`, `InteractsWithTenants`, `IdentifyTenant` (alias, persistent middleware), `'tenant' => 'path'\|'domain'` on zones | panels | feature: 404 for a stranger and an unknown slug; URL defaults reach every link; round trip keeps the tenant; parameter not passed to `mount()`; refused user model |
| 3 | Zone entry, the no-tenant page, the tenant switcher | panels | feature + driver `verify-tenants` (switch keeps the page, falls back on a record, survives `wire:navigate`, links stay in-tenant after a round trip) |
| 4 | Queued work carries the tenant | core | feature: a job dispatched in A runs in A under both isolations |
| 5 | `DatabaseIsolation`, `BelongsToTenantDatabase`, `wire:tenants:create`, `wire:tenants:migrate` | core | the step 2–4 feature suites re-run under `database` isolation (SQLite files per tenant) |
| 6 | Teams inside a tenant: per-tenant current team, scoped switcher | module-users | feature: switching company drops the other company's team; the switcher lists three of five |
| 7 | Tenant-scoped roles | `nyoncode/laravel-permission-extended` | that package's suite; then module-users uses them |
| 8 | `wire-module-tenants`: registration, profile, members, invitations | new module | feature + driver `verify-tenant-onboarding` |
| 9 | Workbench `app/{tenant}` zone over two seeded companies with projects; docs `docs/panels/tenancy.md` and `docs/modules/tenants.md` (EN/CS); `start/authorization.md` § Multi-tenancy rewritten around it; boost guideline and skill | all | the sweep, the docs gates, `composer verify:install` with `--tenancy` |

### 12. Deliberately not decided here

- **A custom domain per tenant** (`crm.acme.com`). A lookup table from host to
  tenant beside the `{tenant}` parameter; its own decision once the domain mode
  exists.
- **Per-tenant branding.** The theming plan's; `CurrentTenant` is what it reads.
- **Billing and plans.** Not a framework concern; `CurrentTenant` is the seam.
- **Tenant-aware menus.** Already possible through an entry's `visible()`.

## Consequences

- A tenant is one parameter, one middleware and one contract on the user model;
  every link the framework builds carries it without being told.
- Changing the tenant in the address bar is refused by construction, as a 404.
- The company and its projects are two levels with two jobs — isolation and
  permission scope — and neither is a second copy of the other.
- Rows or databases is a config value; the rest of the stack cannot tell which.
- A job that loses its tenant sees no rows; one that keeps it works — never a
  leak.
- It needs one change outside this repository: tenant-scoped roles in
  `laravel-permission-extended`.
- Nothing becomes panel configuration (ADR 0028 holds).
