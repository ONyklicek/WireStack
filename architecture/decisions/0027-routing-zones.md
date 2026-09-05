# ADR 0027: Zones — Several Mount Points Over One Catalogue

## Status

ACCEPTED — 2026-09-05. Implemented the same day.

One thing landed differently and one trap was found only while writing the code:

- **`Zone::of()` is a regex, not a substring search**, and it has to be.
  `str_contains('livewire.update', 'wire.')` is **true** — so the obvious
  implementation reports a zone called `li` on precisely the request where there
  is none, which is every Livewire round trip. The name is matched anchored
  instead: an optional prefix, then `wire.`, then a key and a page and nothing
  else. Pinned by its own test.
- **The workbench driver runs one browser, navigated twice.** `openPage()` takes
  its DevTools port from a fixed default, so two calls inside one driver race
  each other's shutdown and die with "Received network error or non-101 status
  code" — a harness failure that reads like a page failure. Worth knowing before
  writing the next multi-page driver.

Extends [ADR 0026](0026-registration-seam.md) and stays inside the line it drew:
no `Panel`, no shell, no layout, no branding. It answers the question 0026's
invariant 6 and open question 3 were holding open — several URL spaces over one
registration — and answers it with **less** new machinery than either expected.

**Amends ADR 0026 invariant 6.** That invariant read "config may hold a route
group's arguments, never a shell", and §5 below reads it as "*route groups'*
arguments": the boundary is which keys an entry carries, not how many entries
there are. Nothing else in 0026 changes.

## Context

The requirement: an application wants `admin`, `business`, `production` …, each
with its own prefix, middleware and possibly domain. A resource **may** be in
several zones and may be in exactly one. Neither is the special case.

Three things were measured before designing anything, and two of them changed
what this ADR had to be.

### Disjoint zones already work, with nothing new

```php
Route::prefix('admin')->middleware(['web','auth','can:admin'])
    ->group(fn () => Route::wireResources(only: ['invoices','users']));

Route::prefix('production')->middleware(['web','auth','can:production'])
    ->group(fn () => Route::wireResources(only: ['batches']));
```

Each zone gets its prefix, middleware and domain, because these are ordinary
Laravel route groups — the property `Route::wireResources()` was built for. With
disjoint `only` sets no route name repeats, so nothing collides.

### Shared resources already route too — `Route::name()` does it

The collision this ADR was expected to be about is Laravel's to solve, and it
already has:

```php
Route::name('admin.')->prefix('admin')->group(fn () => Route::wireResources());
Route::name('business.')->prefix('business')->group(fn () => Route::wireResources());
```

```
admin.wire.invoices.index      →  admin/invoices
business.wire.invoices.index   →  business/invoices
```

Eight routes, eight distinct names, one resource. **So the route half needs no
code at all.** ADR 0026's open question 3 ("`wire.{key}.{page}` is hardcoded; two
installations would collide") was answered by a feature that was already there,
and asking for a `name` argument on the macro would have been a second way to say
what a route group says.

### What is actually broken

`ResourceRoutes::urlFor()` builds `"wire.{$key}.{$page}"` and cannot see the
group's name prefix. So every surface that links — the menu through
`ResolvesPageUrls`, the search palette through the URL it derives, and
`urls()` — answers with the **unprefixed** name. In a two-zone application that
name belongs to no route at all, so every link is `null`; in a mixed one it
belongs to whichever zone was registered without a name prefix, so links silently
leave the zone the user is in.

That is one method, and it is the whole gap.

### And the trap under the obvious fix

The obvious fix is to derive the zone from the current route: on
`business.wire.invoices.index` the prefix is `business.`, so a menu links inside
its own zone with nothing declared. Measured:

```
Route::currentRouteName()  during a Livewire update  →  "livewire.update"
```

It is right on the first render and wrong on every request after it. The search
palette runs on **every keystroke** and a sidebar re-renders on every navigation,
so the mechanism would fail exactly where it is used, while rendering perfectly.
`Livewire::originalPath()` does carry the page path through an update, but
turning a path back into a route name means matching a synthesised request
against the route collection on every call, per row, per keystroke.

## Decision

### 1. A zone is a route-name prefix. There is no Zone object

Nothing is registered, nothing is declared twice. A zone is the `name()` of the
route group an application already writes, and the string `'business.'` is its
whole identity. `Catalog` keeps one key namespace and keeps refusing duplicates:
what multiplies is not the registration but the **mounting**, and a mounting is
a route group.

This is the same judgment ADR 0026 made about menus — do not build a registry for
something a seam already answers — applied to the router itself.

### 2. `urlFor()` learns the zone; nothing else changes shape

```php
ResourceRoutes::urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
```

`$zone` is the route-name prefix, with or without its trailing dot. `null` means
the unprefixed name, which is what a single-zone application has and what every
existing call already asks for — so this is additive, and `wire.{key}.{page}`
stays exactly what it was.

`ResolvesPageUrls::urlFor()` takes the same fourth argument, and `urls()` takes
the zone it should answer for.

### 3. The zone is decided once, at page render, and travels in the snapshot

Not derived per request — the measurement above says that cannot work. Derived
**once**, where it is correct, and then carried:

```php
public ?string $zone = null;   // Livewire snapshots it

public function mount(): void
{
    $this->zone = Zone::current();   // Str::before(Route::currentRouteName(), 'wire.')
}
```

`Foundation\Routing\Zone::current()` reads the current route name and returns
everything before `wire.`, or `null` outside a wire route. It is documented as
**only valid during a full page render**, and the components that need a zone
hold a property rather than calling it again.

This is the rule `ResolvesOneRecord` already follows and for the same reason: the
identifier travels, the thing is resolved per request. A zone re-derived on a
Livewire update is the same class of mistake as a model cached across one.

### 4. Membership is what you routed

A zone does not declare which resources it contains. `only`/`except` on the macro
already say that, and asking for it a second time is the drift 0026 was written
about — the copy that disagrees.

So a menu in a zone shows an entry as a link when that zone routes its key, and
without an href when it does not — which is the behaviour an unrouted resource
already has. `Workspace` needs no membership list, only the zone to resolve URLs
in.

### 5. Config declares zones, and the earlier constraint was drawn wrong

ADR 0026 §5 made "a second group is impossible to express" the constraint that
kept config from becoming a panel, and this ADR's first draft repeated it. It
does not hold up. What separates a route group from a panel is **which keys** an
entry carries, not how many entries there are: one group with a `layout` is more
of a panel than ten groups without one. A list of
`{prefix, middleware, domain, only, except}` is still nothing but arguments to
`Route::group()`.

Amends ADR 0026 invariant 6 accordingly: config may hold *route groups'*
arguments; the seventh **key** is still a Panel proposal, the second **zone** is
not.

```php
// config/wire-panels.php
'routes' => [
    'enabled' => true,
    'zones' => [
        'admin' => [
            'prefix' => 'admin',
            'middleware' => ['web', 'auth', 'can:admin'],
            'only' => ['invoices', 'users'],
        ],
        'business' => [
            'prefix' => 'business',
            'middleware' => ['web', 'auth', 'can:business'],
            'except' => ['users'],
        ],
        'production' => [
            'prefix' => 'production',
            'middleware' => ['web', 'auth', 'can:production'],
            'domain' => 'ops.example.com',
            'only' => ['batches'],
        ],
    ],
],
```

**The array key is the zone**, and that is the reason this path is better than a
hand-written one rather than merely equal to it. §1 makes a zone a route-name
prefix, and in a route file that prefix is optional — forget `->name('business.')`
on the second group and both zones register `wire.invoices.index`, the later
silently winning every lookup. Here it cannot be forgotten and cannot repeat,
because it is an array key. The failure mode §Context measured is unreachable by
construction.

Zones and the single-group shape are the same mechanism: `routes` without a
`zones` key stays exactly what ADR 0026 shipped — one unnamed group, `null` zone,
`wire.{key}.{page}` — so nothing an application already wrote changes meaning.

What stays true from ADR 0026 §5, and matters more with several zones than with
one: these routes are registered from a provider, so they match **before**
everything in `routes/web.php`, and using config *and* `Route::wireResources()`
together is refused rather than merged.

### 6. Still out of scope

Layout, branding, a login route, per-zone navigation *groups*, a zone-aware
authorization vocabulary. A zone here is a prefix, a middleware stack, a domain
and a name — the four things a route group already is. The day one of them owns
a layout, it is the `Panel` ADR 0020 deferred, and it should arrive as that
rather than as a fifth method.

## Boundary invariants

1. **One catalogue, one key namespace.** A zone multiplies mount points, never
   registrations.
2. **A zone is a string.** If it acquires a class with declarations of its own,
   re-read §6 before adding the first one.
3. **Membership is derived from routes, never declared.**
4. **The zone travels; it is not re-derived.** `Zone::current()` is a
   full-page-render call and says so.
5. **Null is still a real answer.** Every URL in this design is nullable and
   every consumer renders without one.

## Migration

- **27.a** — `Zone::current()`; the `?string $zone` argument on
  `ResourceRoutes::urlFor()`/`urls()` and on `ResolvesPageUrls`. Additive: every
  existing call means what it meant.
- **27.b'** — `routes.zones` in `wire-panels.php`, and `ConfiguredRoutes`
  looping over it — the registrar already builds exactly one such group, so this
  is that method with the group arguments coming from an entry instead of from
  the top level. A `routes` block with no `zones` key keeps registering the one
  unnamed group it registers today, which is what makes this additive rather
  than a second path.
- **27.b** — `Workspace` takes a zone when resolving entry URLs;
  `GlobalSearchPalette` gains the persisted `$zone` and passes it to the search's
  URL resolution.
- **27.c** — a two-zone workbench preview over one shared resource, and a driver
  that follows a link inside a zone **after a Livewire update** — the one thing
  no unit test can see, and precisely where the rejected mechanism would have
  failed. Plus `previews/zones`, both zones in both menu modes, which is where
  open question 2 was decided.
- **27.d** — docs: the zone recipe in `docs/core/resources.md` § Routing, both
  locales, and a line in `configuration.md` saying zones need a route file.

## How it gets verified

The failure mode is a link that renders and points at the wrong zone, so the
gates that matter are the ones that follow one.

- Unit: `urlFor()` with and without a zone, an unknown zone answering `null`, and
  `urls()` scoped to a zone. Cheap and exhaustive.
- `Zone::current()` must be asserted to be `null`, not wrong, during a Livewire
  update — a test that would have caught the mechanism this ADR rejected.
- Browser: type in the palette (a Livewire request), select a result, and assert
  the landing path is inside the zone the page was in. Pest sees valid markup
  either way.

## Consequences

- **Good.** Zones cost one optional argument and one small helper. The route half
  was already there; this ADR mostly writes down that it was.
- **Good.** Shared and unique zones are the same mechanism — no mode, no flag.
- **Trade-off.** The zone is a stringly-typed prefix, so a typo is a `null` link
  rather than an error. That is the same failure an unrouted resource already
  has, and the alternative is a registry that has to be kept in step with the
  route file.
- **Good.** The config path makes the zone prefix unforgettable, which the route
  file cannot: an array key is unique by construction and a `->name()` call is
  a line someone omits.
- **Trade-off.** More zones means more routes matched ahead of `routes/web.php`.
  One catch-all under one zone's prefix is enough to notice it.
- **Trade-off.** Any component that renders a menu or a palette inside a zone
  must carry the property. Forgetting it means links leave the zone — the reason
  27.c's driver is not optional.

## Risks

- **Re-deriving the zone in a hot path.** Someone will call `Zone::current()`
  inside a render because it is shorter than passing the property, and it will
  work in the browser until the first Livewire update. The docblock has to be
  blunt, and the test in §How has to exist.
- **Zone creep.** `Zone` is a helper with one static method. A `Zone::make()`
  with `->layout()` beside it is the Panel arriving quietly; invariant 2.

## Open questions

1. ~~**Should config zones be able to name a landing page?**~~ **Closed: no key,
   because it was already expressible.** A landing page is a thing routed at the
   zone root, and `ConfiguresRoutes::routePrefix()` returning `''` adds no
   segment — so a dashboard declaring pages and an empty prefix lands its index
   on the group's own path. Verified before designing anything, the way the two
   findings in §Context were: `business.wire.ls-overview.index → business`.

   A `'landing'` key was rejected on three counts, any one of them sufficient.
   It would be **asymmetric** — zones written in a route file could not have it
   until the macro grew `Route::wireResources(landing: …)`, which is two ways to
   say one thing. It would **duplicate membership**: the zone already says
   `only: ['business-overview', …]`, and `landing:` says it again — the copy that
   drifts when one is edited. And it is **the first key that is not an argument
   to `Route::group()`**, which is where invariant 2 says to stop: once config
   names a page, naming a layout is one small step.

   Per-zone landings need nothing extra: two dashboards both declare the root and
   `only`/`except` decides which zone lands on which — the same list that decides
   everything else (§4).

   **What did have to change** is a refusal. Two pages claiming the root of one
   group is not a shadowing, it is a deletion: Laravel keys its route collection
   by method and URI, so the second registration replaces the first *and takes
   its route name with it*. Measured — `route('…ls-overview.index')` threw
   `RouteNotFoundException` for a route the same call had just registered, which
   reaches a user as a menu entry that silently stops linking anywhere.
   `ResourceRoutes::all()` now refuses it, naming both keys.

   `ConfiguresRoutes::ROOT` is the readable spelling of `''`. A constant on the
   contract that declares the method, not a new API: the weakness of this design
   was legibility, and nobody guesses a bare empty string.
2. ~~**Should a menu hide entries the zone does not route?**~~ **Closed, over the
   preview.** `previews/zones` renders both zones both ways, and the unfiltered
   `business` menu is three dead rows out of four — the shape commit 51d7d5a
   called a defect. So `navigation(zone: …, linkedOnly: true)` drops them, and it
   is **opt-in rather than the rule** for two reasons the preview also showed:
   `Workspace` cannot tell "routed in another zone" (`tasks`) from "routed
   nowhere" (`documents`, and the dashboard), and a shell with a URL scheme of
   its own has every entry unlinked here while wanting all of them.

   Checked against Filament, which does not have this question: resources are
   registered **per panel** (`$panel->resources([…])`), so one not in the panel is
   not in its catalogue and cannot reach its menu. The price is declaring a shared
   resource in every panel it appears in — exactly what one global catalogue was
   for. `linkedOnly` reaches the same menu while registering once. Worth noting
   that Filament's `getUrl(…, panel: 'marketing')` is the same shape as §2's
   `urlFor(…, zone: 'business')`, arrived at independently.
3. **Where a zone's default page lives.** `/business` itself routes nothing —
   every zone will want a landing page, and a dashboard is the obvious answer,
   but "which dashboard is this zone's" is membership by another name.

## References

- `architecture/decisions/0026-registration-seam.md` — the catalogue, the URL
  seam, invariant 6 and open question 3.
- `architecture/decisions/0020-application-owner-layer.md` — §5, and the `Panel`
  this still declines.
- `packages/panels/src/Routing/ResourceRoutes.php` — `urlFor()`, the one method
  that changes.
- `packages/panels/src/Resources/Concerns/ResolvesOneRecord.php` — the "the
  identifier travels" rule §3 reuses.
