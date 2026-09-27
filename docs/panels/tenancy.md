---
order: 55
summary: A tenant in the URL — found by its slug, checked against the signed-in person, entered, and carried into every link the framework builds.
---

# Tenancy

A tenant is a company the application serves, and a tenant zone is a group of
pages that lives under one: `/app/acme/orders`, or `acme.example.com/orders`.
This page is the routing half — how the tenant gets from the URL into the
request. Which rows a tenant sees is the data half, and it is
[Authorization § Multi-tenancy](../start/authorization.md#multi-tenancy).

## How It Works

The tenant is a route parameter named `tenant`, in the prefix or in front of the
domain, and the `wire.tenant` middleware turns it into the current tenant. On
every request inside the zone it does four things, in this order:

1. **Finds** the tenant by the model's route key — `getRouteKeyName()`, a slug
   when the model says so — without the model's global scopes.
2. **Checks** the signed-in person: `HasTenants::canAccessTenant($tenant)`. A
   stranger, an unknown slug and a guest all get the same **404** — a 403 would
   tell whoever typed `/app/globex/` that globex exists.
3. **Enters** it (`CurrentTenant`), so every model using `BelongsToTenant`
   is scoped to it with nothing else written.
4. **Carries** it: `URL::defaults(['tenant' => …])`, so every URL built after
   this point — `urlFor()`, `OrderResource::url()`, the menu, search results,
   breadcrumbs, redirects after save, your own `route()` calls — points into the
   same tenant. The parameter is then taken off the route, so no page's
   `mount()` is handed one it did not ask for.

**A Livewire round trip keeps it.** The update request goes to
`livewire/update`, which has no `{tenant}` in it; the middleware is registered
as Livewire *persistent* middleware, so Livewire rebuilds the page's original
request and runs it again. The second render is scoped and linked exactly as the
first was.

**The traps.** The zone survives a round trip the same way, on every page
`Route::wireResources()` registered; a component on a route of your own keeps
the zone it read on mount and passes it ([Routing](routing.md#linking-to-them)).
And a user model that does not
implement `HasTenants` is refused with an exception on the first request, never
read as "may enter every tenant".

## A Tenant Zone

One option on the panel's route group does all of it — the parameter, the
middleware, and the zone's own address beside it. In a route file:

```php
Route::middleware(['web', 'auth'])->prefix('app')                    // or ->domain('example.com')
    ->group(fn () => Route::wire('panel', zone: 'app', tenant: 'path'));   // [tl! focus]
```

Or as an entry of `wire-core.routes.groups` ([Routing](routing.md#describing-the-groups-in-config)):

```php
// config/wire-core.php
'routes' => ['groups' => [                                                     // [tl! focus:start]
    'app' => ['uses' => 'panel', 'prefix' => 'app', 'tenant' => 'path'],          // app/{tenant}/…
    'portal' => ['uses' => 'panel', 'domain' => 'example.com', 'tenant' => 'domain'],   // {tenant}.example.com
]],                                                                            // [tl! focus:end]
```

Written out by hand it is the same group, with the parameter and the middleware
in it yourself:

```php
Route::name('app.')->prefix('app/{tenant}')->middleware(['web', 'auth', 'wire.tenant'])
    ->group(fn () => Route::wireResources());
```

**Nothing here assumes tenancy.** A zone without `tenant` is an ordinary zone,
and a page that only exists inside a company — the companies module's profile
and members — is skipped in it, so an application that does not use tenancy
never carries an address that could only answer 404.

A path needs nothing from DNS and lets a person keep two tenants open in two
tabs; a domain needs a wildcard DNS record and certificate, and a session
cookie domain that covers the subdomains (`SESSION_DOMAIN=.example.com`). A
value other than `path` or `domain`, or `domain` on a zone with no `domain`, is
refused when the routes are registered.

## The Zone's Own Address

Every page of a tenant zone lives under a company, so `/app` on its own would
be a 404 — and it is the address a person types and the one signing in lands
on. It answers instead: to the person's `getDefaultTenant()`, and from there to
the first page of that company they may open.

`tenant:` registers it by itself, at the prefix without the tenant or at the
domain's root, named `{zone}.wire.tenants` — which is also what a zone picker
offers for the zone. A tenant zone written out by hand places it as its own
group, saying where a company's address is:

```php
Route::name('app.')->middleware(['web', 'auth'])
    ->group(fn () => Route::wire('tenant-entry', uri: 'app', to: 'app/{tenant}'));   // [tl! focus]
```

`to` has `{tenant}` where the company's key goes — `//{tenant}.example.com` for a
domain zone.

Someone with no company at all gets `wire-panels.routes.tenant_entry.view` when
the application names one — the place to offer registering a company — and a
403 that says why when it does not. The [companies module](../modules/tenants.md)
names its own when the application has not.

## The Company Switcher

Inside a company, the top bar of any shell that draws `PageChrome::TOPBAR` gets
a switcher, from wire-panels. It lists the companies this person belongs to,
each linked to **the same page** in that company — except on a record's page,
where the link is the same resource's list: record 7 of one company is not a
record of another, and switching would land on a 404. Nothing is drawn outside
a company, or for someone with only one.

A company is named by `wire-core.tenancy.label` (`name`), or by its route key
when that is empty.

## The Tenant Model

```php
// config/wire-core.php
'tenancy' => [
    'enabled' => true,
    'model' => App\Models\Company::class,   // [tl! focus]
    'members_table' => 'tenant_user',
],
```

```php
class Company extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';   // /app/acme/… rather than /app/12/…
    }
}
```

## Who Belongs Where

The user model answers it, through `HasTenants`:

```php
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Contracts\HasTenants;

class User extends Authenticatable implements HasTenants
{
    use InteractsWithTenants;   // [tl! focus]
}
```

`InteractsWithTenants` reads membership from a many-to-many to the tenant model
over `members_table`, with Laravel's pivot column names — `user_id` and, for a
`Company`, `company_id`. A membership shaped otherwise overrides `tenants()`, or
implements the three methods itself:

| Method | Returns | Purpose |
| --- | --- | --- |
| `getTenants(): iterable` | `iterable<int, Model>` | The tenants this person belongs to — what a switcher offers |
| `canAccessTenant(Model $tenant): bool` | `bool` | Whether they may work in this one; asked on every request inside it |
| `getDefaultTenant(): ?Model` | `Model\|null` | Where the zone's own address sends them |

## Related

- [Authorization § Multi-tenancy](../start/authorization.md#multi-tenancy) — the scope, the fail-safe, `runAs()` for jobs and commands
- [Routing](routing.md) — zones, route names and `url()`
- [Navigation](navigation.md) — the menu whose links now carry the tenant
- [Companies](../modules/tenants.md) — registering a company, and inviting and managing its members
