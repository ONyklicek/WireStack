---
order: 50
summary: Turning declared pages into real URLs — the macro, the URL shape, per-resource middleware, several zones over one set of resources, and the config path.
---

# Routing

The registry holds no URL shell and no route: routes stay the application's, in
its own group, with its own prefix and middleware. What the framework removes is
the repetition — four `Route::get()` lines per resource, and a hand-written
key→URL map beside them for the menu.

## How It Works

A resource says which pages render it — and so may anything else the application
registered, a dashboard included: the router reads the same catalogue the menu
does, so being routable is a matter of declaring pages rather than of being a
particular kind of thing.

```php
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;

public static function pages(): array   // [tl! focus:start]
{
    return [
        'index' => ListOrders::class,
        'create' => CreateOrder::class,
        'view' => ViewOrder::class,
        'edit' => RoutePage::make(EditOrder::class)->permission('orders.update'),
    ];
}   // [tl! focus:end]
```

and the application registers them **inside its own group**:

```php
// routes/web.php
Route::prefix('admin')
    ->middleware(['auth', 'verified'])
    ->domain(config('app.admin_domain'))
    ->group(function () {
        Route::wireResources();                        // [tl! focus]
        Route::wireResource(OrderResource::class);     // or one at a time
    });
```

The prefix, the middleware and the domain are yours — these are ordinary Laravel
routes registered in the group you called the macro in. A resource that declares
no pages is skipped, which is how an internal or nested resource stays unrouted;
naming one explicitly that declares none throws instead, because that is a
mistake rather than a choice.

## The URL shape

| Page kind | URL | Route name |
| --- | --- | --- |
| `index` | `{prefix}` | `wire.{key}.index` |
| `create` | `{prefix}/create` | `wire.{key}.create` |
| `view` | `{prefix}/{record}` | `wire.{key}.view` |
| `edit` | `{prefix}/{record}/edit` | `wire.{key}.edit` |
| anything else | `{prefix}/{kind}` | `wire.{key}.{kind}` |

`{prefix}` is the registered key, so the menu key and the URL agree without
either being repeated. `{record}` is a **key**, not a bound model: the pages resolve
their own record, which is what keeps a soft-delete scope, a tenant guard or a
non-Eloquent source the page's decision rather than the router's.

## Authorization, middleware and domains

`RoutePage::permission()` lands on the route as Laravel's own `can:` middleware.
Nothing here re-implements an authorization check — Gate answers it, exactly as
it does for actions, columns and widgets, so `spatie/laravel-permission` and
`nyoncode/laravel-permission-extended` keep working unchanged. A refusal happens
in the router, before the page renders or a query runs.

Per resource, `ConfiguresRoutes` adds the three things that belong to one
resource rather than to the whole group:

```php
public static function routeMiddleware(): array { return ['can:tenants.view']; }
public static function routeDomain(): ?string { return '{tenant}.example.com'; }
public static function routePrefix(): ?string { return 'billing/tenants'; }
```

The domain parameter reaches your `TenantResolver` like any other route
parameter. Tenancy itself stays where it is — a global scope over every query,
not a routing concern; see [Authorization](../start/authorization.md).

## Zones

Several mount points over one set of resources — `admin`, `business`,
`production`. A resource may be in one of them, in several, or in all: a zone
multiplies where a page is reachable, never how many times it is registered.

A zone is the **name** of the route group, and nothing else:

```php
Route::name('admin.')->prefix('admin')->middleware(['web','auth','can:admin'])
    ->group(fn () => Route::wireResources());                        // [tl! focus]

Route::name('business.')->prefix('business')->middleware(['web','auth','can:business'])
    ->group(fn () => Route::wireResources(only: ['orders']));        // [tl! focus]
```

```
admin.wire.orders.index      →  admin/orders
business.wire.orders.index   →  business/orders
```

The `name()` call is what keeps them apart. Omit it on the second group and both
zones register `wire.orders.index`, where the later one silently wins every
lookup — which is why the [config path](#registering-them-from-config-instead)
below is the safer way to declare zones: there the zone is an array key and
cannot be forgotten.

Which resources a zone contains is `only` / `except`, and nothing else — there is
no second list to keep in step with the routes.

**Linking inside a zone.** Every URL question is "where is this key *in this
zone*", so the zone travels with it:

```php
ResourceRoutes::urlFor('orders', zone: 'business');   // /business/orders
ResourceRoutes::urls(zone: 'business');               // only what business routes
app(Workspace::class)->navigation(zone: 'business');  // entries linked into business
```

A key the zone does not route answers `null` and renders without a link, exactly
as an unrouted resource does. When the menu should hold only what this zone can
actually reach, ask for that:

```php
app(Workspace::class)->navigation(zone: 'business', linkedOnly: true);
```

Opt-in rather than the rule, because the two reasons an entry has no URL are
different and `Workspace` cannot tell them apart: one may be routed in *another*
zone, another routed nowhere at all. And a shell with a URL scheme of its own has
every entry unlinked here and still wants all of them — it is the caller who
knows which case it is in. A group whose entries all drop away is absent rather
than an empty heading.

**The zone's landing page.** `/business` itself routes nothing unless something
claims it, and claiming it is one method — an empty prefix adds no segment, so
that page's `index` lands on the group's own path:

```php
final class BusinessOverview extends Dashboard implements ConfiguresRoutes, ProvidesPages
{
    public static function pages(): array { return ['index' => ShowBusinessOverview::class]; }

    public static function routePrefix(): ?string { return self::ROOT; }   // [tl! focus]
}
```

```
business.wire.business-overview.index   →  business
business.wire.orders.index              →  business/orders
```

Which zone lands where is `only` / `except`, like every other membership
question: give each zone its own dashboard and list it there. Two pages claiming
the root of **one** group is refused — Laravel keys routes by URI, so the second
would replace the first and take its route name with it, leaving a menu entry
that looks routed and silently links nowhere.

A zone that wants a destination rather than a page of its own writes an ordinary
redirect beside the group:

```php
Route::redirect('business', 'business/orders');
```

**Where the zone comes from.** `Zone::current()` reads it off the route being
rendered, and that is a **full-page-render** call:

```php
public ?string $zone = null;      // [tl! focus:start]

public function mount(): void
{
    $this->zone = Zone::current();
}                                 // [tl! focus:end]
```

`Route::currentRouteName()` answers `livewire.update` during a round trip, so a
component that asks again mid-update gets nothing — and a palette that searches
on every keystroke would link out of its zone while looking perfectly fine. Read
it once, keep it in a public property, and let Livewire carry it. The command
palette already does exactly this, so a palette in a zoned layout needs no
configuration.

## Registering them from config instead

The macro above stays the reference path. An application that wants the
convention and would rather not keep a route file for it hands the same group
arguments over once:

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,                    // [tl! focus]
    'prefix' => 'admin',
    'middleware' => ['web', 'auth'],
    'domain' => null,
    'only' => [],
    'except' => [],
],
```

Zones are a `zones` key, and the array key is the zone:

```php
'routes' => [
    'enabled' => true,
    'middleware' => ['web', 'auth'],          // inherited by every zone
    'zones' => [                              // [tl! focus:start]
        'admin' => [
            'prefix' => 'admin',
            'middleware' => ['web', 'auth', 'can:admin'],
        ],
        'business' => [
            'prefix' => 'business',
            'only' => ['orders', 'customers'],
        ],
    ],                                        // [tl! focus:end]
],
```

Each zone inherits the values outside `zones` and overrides what it names. **The
key becomes the route-name prefix**, which is the reason to prefer this over
hand-written groups rather than merely an alternative to them: in a route file
`->name('business.')` is a line someone omits, and omitting it makes one zone
take over the other's links silently. An array key cannot be omitted and cannot
repeat.

With no `zones` key it is one unnamed group, which is what a single-zone
application wants.

Off by default, and deliberately so: package providers boot before your own, so
these routes are matched **before** everything in `routes/web.php`. An
application with a catch-all under the same prefix wins today and would stop
winning, which is a decision to make rather than a default to inherit.

Enabling this *and* calling `Route::wireResources()` yourself would register
every page twice under one route name; that is refused rather than resolved, with
a message naming both lines you could delete.

## Linking to them

Nothing needs to write a URL by hand any more. A menu entry carries the URL of
its key's page, and a search result carries the URL of its record's:

```php
$item->getUrl();          // /admin/orders — filled by Workspace, null when unrouted
$result->url;             // /admin/orders/7 — from the key and the record key
```

Both come from `ResolvesPageUrls`, which `wire-panels` answers and `wire-core`
answers with `null` when no package owns routing. Null is a real answer: a menu
entry without an href still renders, and a resource that declares no pages is
deliberately unlinked. An entry or a result that names its own URL always wins —
an external link, or an application with a shell URL scheme of its own.

Reaching for it directly is the same call:

```php
ResourceRoutes::urlFor('orders');                          // /admin/orders
ResourceRoutes::urlFor('orders', 'edit', ['record' => 7]); // /admin/orders/7/edit
ResourceRoutes::urls();                                    // ['orders' => '/admin/orders', …]
```

A full-page Livewire component needs a layout, and the framework does not supply
one — set `livewire.component_layout` to your own.

## Routing API

```php
ResourceRoutes::all(array $only = [], array $except = []): array   // every declaring key
ResourceRoutes::for(string $class): array                          // one, or throws
ResourceRoutes::urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
ResourceRoutes::urls(string $page = 'index', ?string $zone = null): array

Zone::current(): ?string          // the zone of the page being rendered — full page renders only
Zone::of(?string $routeName): ?string
Zone::prefix(?string $zone): string
```

`urlFor()` answers `null` twice over: when nothing routes the key, and when the
route needs a parameter this call did not give — a resource on a `{tenant}`
domain, say. Both render as "no link" rather than taking a menu down.

From `wire-core`, reach it through `ResolvesPageUrls` instead, which `wire-panels`
answers and which answers `null` when no package owns routing. `RegistersPageRoutes`
is the other half of that seam: `wire-core` calls it once the registries are full,
which is the only moment [config-declared routes](#registering-them-from-config-instead)
can read a complete catalogue.

## Related

- [Pages](pages.md) — the components these routes reach
- [Navigation](navigation.md) — where a menu entry's URL comes from
- [Authorization](../start/authorization.md) — the gates `permission()` and `can:` consult
- [Configuration](../start/configuration.md) — the `wire-panels.routes` block in full
- [The Admin Shell](../admin/overview.md) — a layout for the pages these routes render
