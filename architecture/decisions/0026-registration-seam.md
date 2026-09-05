# ADR 0026: One Registration Seam for Navigation, Routing and Search

## Status

ACCEPTED — 2026-09-04. All four phases implemented the same day.

Amends [ADR 0020](0020-application-owner-layer.md). Nothing in it is superseded:
§5 ("Routing is opt-in", "No routing/panel lock-in") stands, and this ADR exists
partly to keep it standing. What changes is *where the three surfaces above the
registries read from* — one seam instead of one seam and two direct reads.

Three things landed differently from the text below, and each is recorded where
it belongs:

- **`ConfiguresResourceRoutes` moved too**, as `Foundation\Routing\Contracts\ConfiguresRoutes`.
  §3 named only `ProvidesResourcePages` and `RoutePage`, which was an oversight
  rather than a decision: a `Dashboard` that may declare pages but not a prefix
  is an asymmetry with no argument behind it.
- **A fourth contract was needed**, `Registration\Contracts\HasRegistryKey`.
  `ResourceRoutes::for()` builds a route name from `$class::key()`, and once its
  parameter stopped being a resource there was nothing promising that method.
  `DescribesResource` and `Dashboard` have both declared it since before the
  catalogue existed; this only gives it one name, and `ProvidesPages` extends it —
  a page that cannot be addressed cannot be given a URL.
- **The workbench keeps its shell map**, against §26.c's "delete
  `$workspacePages`". Both literal search URLs are gone, which was the real
  defect. The shell's own scheme is not: `/previews/workspace/{key}` chooses what
  renders beside the sidebar, so taking the entry's URL there would navigate out
  of the shell and close the navigation behind it — the reason commit 51d7d5a
  gave, unchanged by any of this. The blade now falls back to `$item->getUrl()`,
  so an application without a shell writes no map at all.

One claim in §5 is weaker than written: the `route:cache` round trip is **not**
tested. `RouteCacheCommand` boots a fresh application from the testbench
skeleton, which never sees this config. What is tested is the property that
breaks it — `ConfiguredRoutesTest` invokes `bootResources()` directly, so moving
the call into an `$app->booted()` callback registers nothing and fails there.

## Context

`Workspace` and `NavigationSource` already are the "one global whole" this
question keeps arriving at. The direction was inverted deliberately
(`NavigationSource.php`): `Workspace` lives in `Core/` (L1) and `Widgets/` is L2,
so a menu could not be taught what a dashboard is. It knows a contract and the
classes handed back, whoever registers them decides what they are, and
`Workspace::registered()` refuses two sources claiming one key rather than
letting one entry take another's place.

That is a single global key namespace with an aggregator and an enforced
uniqueness rule. It works. **Only one of its three consumers uses it.**

```
Workspace       → NavigationSource[]              seam
ResourceRoutes  → app(ResourceRegistry)->all()    ResourceRoutes.php:69, :158
GlobalSearch    → ResourceRegistry (constructor)  GlobalSearch.php:45
```

`GlobalSearch`'s own docblock states the coupling as a feature — "Reads the
`ResourceRegistry` and nothing else about the application" — and on the day it
was written that was true and sufficient. It stopped being sufficient at V2.6
step 3, when `DashboardRegistry` became a second `NavigationSource`
(`DashboardRegistry.php:23`) and `bootModules()` began spreading a domain
module's `resources()` **and** `dashboards()` into two registries that only the
menu reads as one.

### What the drift already costs, measured

**Three of four menu entries cannot be routed.** `route:list` registers one of
the workbench's four navigation keys — `invoices`, the only resource declaring
`ProvidesResourcePages`. `overview` is a dashboard, and `ResourceRoutes::urls()`
does not read the registry it lives in. Recorded in commit 51d7d5a as the reason
the workspace sidebar keeps a hand-written key→URL map; that commit answered "can
the sidebar use `urls()` yet" with a correct *no*, and the reason it gave is this
ADR's subject.

**The URL gets written by hand a third time, in the reference implementation.**

```php
// workbench/app/Resources/InvoiceResource.php:119
url: '/previews/resource-view',      // a string; carries no record
// workbench/app/Resources/TaskResource.php:54
url: '/previews/workspace/tasks',
```

`ResourceRoutes::urlFor()` exists, is tested to both edge cases, and would answer
these — `GlobalSearchResult` already carries `resourceKey` and `recordKey`, which
is exactly its signature. It is not used because it is not reachable from where
the URL is needed: `GlobalSearchResult` is `wire-core`, `ResourceRoutes` is
`wire-panels`, and core may not see panels. When the helper goes unused by its
own author, that is a seam problem, not a discipline problem.

**A key collision is only enforced if someone renders a menu.** The uniqueness
rule lives in `Workspace::registered()`. An application that routes and searches
but draws its own navigation never calls it, so a resource and a dashboard
claiming one key register cleanly and collide later — at a URL prefix, silently.

### The question this answers

Closing the seam pulls toward "then it should all be one global thing", and that
sentence has two readings which must not be merged:

- **One registration seam and one key vocabulary.** Menu, router and palette read
  the same list. This is `CLAUDE.md` § Architectural Invariants applied
  literally — one canonical owner, no parallel mini-API for the same concept —
  and it is already half-built.
- **One `Panel` object** owning prefix, middleware, domain, layout, branding and
  auth. That is Filament's `Panel`, it is the V3 candidate ADR 0020 refused by
  name, and it costs the one property this framework has and Filament does not:
  routes are registered in the application's own group, so `route:cache`,
  ordering against the application's routes, and the surrounding
  `prefix`/`middleware`/`domain` all stay the application's.

This ADR decides the first and explicitly declines the second — while granting
what the second is usually wanted *for*. An application that never wants to open
`routes/web.php` does not need a `Panel`; it needs the three arguments it would
have typed there to be readable from config, which is §5. The difference is not
cosmetic: config that carries a prefix and a middleware list can be deleted and
replaced by one macro call, and a `Panel` that owns the layout and the login
route cannot.

## Decision

### 1. Generalize the existing seam; do not add a second one

`NavigationSource` is menu-specific in its name and its docblock only. Its
method already returns "the classes this source registered, keyed" — and
`Workspace::registered()` already documents it as "every class behind the menu …
**whatever kind it is**, including the ones that declare no entry".

Rename rather than duplicate:

```php
// packages/core/src/Foundation/Registration/Contracts/RegistrySource.php
interface RegistrySource
{
    /** @return array<string, class-string> Key => class, in registration order. */
    public function registeredClasses(): array;
}
```

Adding a parallel `RoutableSource` beside `NavigationSource` would give two
contracts answering one question and let them diverge inside one commit. Each
consumer keeps its own **capability** contract as the filter, which is where the
per-surface opt-in already lives and stays:

| Consumer | Reads | Filters by |
| --- | --- | --- |
| `Workspace` | `RegistrySource` | `ProvidesNavigation` |
| `ResourceRoutes` | `RegistrySource` | `ProvidesResourcePages` |
| `GlobalSearch` | `RegistrySource` | `GloballySearchable` |

Registered and listed stay two different questions, exactly as
`ProvidesNavigation` already says.

### 2. One aggregate, which owns the key namespace

The list of sources is assembled in `WireCoreServiceProvider::registerResources()`
and handed to `Workspace` (`:439`). Three consumers means that list must be one
binding, not three:

```php
// packages/core/src/Foundation/Registration/Catalog.php
final class Catalog
{
    /** @param array<int, RegistrySource> $sources */
    public function __construct(private array $sources) {}

    /** @return array<string, class-string> */
    public function all(): array;          // duplicate key => refused here
}
```

`Workspace` takes a `Catalog` instead of an array of sources and loses
`registered()`'s collision check to it. The rule does not change; **when** it
runs does — at the first read of the catalogue, which routing and search both
perform, instead of only when a menu is drawn.

`Catalog` in `Foundation/` (L0) is what lets `ResourceRegistry` (L1),
`DashboardRegistry` (L2) and `wire-panels` all see it without a layer exception
(ADR 0025). It is deliberately smaller than `Workspace`: it groups nothing,
sorts nothing and renders nothing.

### 3. The page declaration moves down; the URL convention does not

For a dashboard to be routable, `Dashboard` (L2, `wire-core`) must be able to say
which components render it — so the declaration has to be visible from core:

- **Moves to `packages/core/src/Foundation/Routing/`:**
  `ProvidesResourcePages` and `RoutePage`. Both are class-strings and a value
  object over `Foundation\Concerns\HasAuthorization`; neither names a table, a
  form or a page class. This is the same move commit e60332f made for
  `relationship()` — the lowest layer that can own it.
- **Stays in `wire-panels`:** `ResourceRoutes` — the `SHAPES` map, the
  `wire.{key}.{page}` naming, and the `Route::wireResources()` macros. A URL
  convention is a panel-layer opinion, and an application that installs only
  `wire-core` + `wire-forms` should not acquire one.

Consequence, stated plainly: routing still requires `wire-panels`. Only the
declaration moves.

`ProvidesResourcePages` should be renamed to match what it now covers (a
dashboard is not a resource) — `ProvidesPages`.

### 4. A URL resolver, as a soft seam

`NavigationItem` (L1) and `GlobalSearchResult` (`wire-core`) both need a URL and
neither may see `wire-panels`. Use the seam ADR 0025 already sanctions
(`HasLifecycle::resolveNotificationManagerClass()` is the precedent):

```php
// Foundation/Routing/Contracts/ResolvesPageUrls
public function urlFor(string $key, string $page = 'index', array $parameters = []): ?string;
```

`wire-core` binds a null implementation returning `null`; `wire-panels` rebinds
one delegating to `ResourceRoutes::urlFor()`. It is an adapter over the existing
method, not a reimplementation of it — the signature above is that method's.

Two things fall out and are the point of the whole ADR:

- `NavigationItem::getUrl()` becomes answerable, so the sidebar's hand-written
  map can go. A key with no route still answers `null`, and a menu entry without
  an href already renders — nothing regresses for an unrouted resource.
- `GlobalSearchResult` can default its `url` from `resourceKey` + `recordKey`,
  so `toGlobalSearchResult()` stops carrying a literal. An explicit `url:` still
  wins.

### 5. Self-registering routes, off by default

`Route::wireResources()` in the application's route file stays the reference
path and the escape hatch. Beside it, an application may hand that group's
arguments to config once and never open `routes/web.php` again:

```php
// packages/panels/config/wire-panels.php  (the package's first config file;
// every other package already has one)
'routes' => [
    'enabled' => false,          // nothing changes for anyone until this is true
    'prefix' => 'admin',
    'middleware' => ['web', 'auth'],
    'domain' => null,
    'only' => [],
    'except' => [],
],
```

This does not weaken ADR 0020 §5. "Routing is opt-in" is about *who decides*, not
about which file the decision is typed in: the prefix, the middleware and the
domain are still the application's, still the same three values, and the default
registers nothing. What it removes is the last hand-written line for the
application that wants the convention — which is most of them, and is the reason
the question keeps being asked.

**It must not silently register zero routes.** Auto-registration reads the
catalogue, which `WireCoreServiceProvider::bootResources()` fills. Provider boot
order across packages is `PackageManifest` order rather than composer dependency
order, so a `wire-panels` provider that booted first would register
nothing and look exactly like a resource that declares no pages. Rather than
depend on that order, invert it the way §4 does:

```php
// Foundation/Routing/Contracts/RegistersPageRoutes — bound only by wire-panels
public function register(): void;
```

`bootResources()` calls it immediately after populating the registries, through
`callAfterResolving`/`bound()` so an application without `wire-panels` resolves
nothing. The registry is full by construction, and there is no ordering to get
wrong.

**Three mechanics that have to be pinned by tests, not by reading.**

- **`route:cache`.** `RouteCacheCommand` boots a fresh application and snapshots
  `$router->getRoutes()`, so provider-registered routes are captured; at runtime
  `loadCachedRoutes()` replaces the collection from a `booted()` callback. The
  registration must therefore happen during `boot()` and never from a `booted()`
  callback of our own, or it is either discarded or applied twice depending on
  callback order. Assert the round trip — cache, then resolve a
  `wire.{key}.index` route — because both wrong versions still serve a request
  in local development.
- **Config and macro together.** Enabling the config *and* calling
  `Route::wireResources()` registers every page twice under one route name.
  Refuse it, the way `Catalog` refuses a duplicate key: the second registration
  of a name this framework owns is a mistake, and the fix is to delete one line,
  which nobody can do if nothing says so.
- **Route precedence moves.** Package providers boot before the application's
  own, so auto-registered routes are matched *before* everything in
  `routes/web.php`. An application with a catch-all under the same prefix
  currently wins and would stop winning. That is a real behaviour change for the
  application that opts in, and the reason `enabled` defaults to false rather
  than to "true when a prefix is set".

**Where the line is.** The config holds one route group's arguments and nothing
else. The moment it grows a layout, a brand, a login route, a navigation tree or
a second group, it has become the `Panel` §6 declines — and a second group is the
signal to go back to the macro, which composes with any number of them.

### 6. Out of scope, and staying out

No panel, no shell, no layout, no branding, no auth ownership. Prefix, middleware
and domain remain the application's route group. `Catalog` maps keys to classes;
`ResolvesPageUrls` maps keys to URLs. Neither holds a URL *shell*, which is the
line ADR 0020 §5 drew and this does not cross. The optional config above carries
one `Route::group()`'s arguments, not a shell that owns them.

Also not decided here: whether the ⌘K palette should list *pages* as well as
records. A dashboard has no records, so it gains nothing from `GloballySearchable`
today; "jump to Overview" is a separate feature and needs its own argument.

## Boundary invariants

1. **One seam per question.** A second source contract for the same key→class map
   is a defect, not an extension point.
2. **Registered ≠ listed ≠ routed ≠ searchable.** Four questions, one
   registration, three opt-in capability contracts.
3. **Keys are globally unique across sources**, enforced on first read of the
   catalogue rather than per surface.
4. **Core never names panels.** Every core→routing call goes through
   `ResolvesPageUrls`, whose default answer is `null`.
5. **An application that routes nothing keeps working.** Every URL in this design
   is nullable, and every consumer already renders without one.
6. **Config may hold a route group's arguments, never a shell.** Prefix,
   middleware, domain, `only`/`except` — and the day a seventh key is proposed,
   it is a `Panel` proposal and belongs in its own ADR.

## Migration

Additive first, then the two renames. Every phase keeps `composer test` and
`vendor/bin/pest --filter ModuleLayers` green on its own.

- **26.a** — `RegistrySource` + `Catalog` in `Foundation/Registration/`; both
  registries implement it; `Workspace` consumes `Catalog` and hands it the
  collision rule. `NavigationSource` is deleted rather than aliased
  (`CLAUDE.md`: consolidation over compatibility layers) — the 2.0 line has not
  shipped. No behaviour visible to an application changes.
- **26.b** — `ResourceRoutes::all()` and `GlobalSearch` read `Catalog`.
  `ProvidesPages` + `RoutePage` move to `Foundation/Routing/`. `Dashboard` may
  declare `pages()`, so `Route::wireResources()` routes one without `wire-panels`
  learning what a dashboard is.
- **26.c** — `ResolvesPageUrls`, its null binding and the panels binding;
  `NavigationItem::getUrl()`; `GlobalSearchResult`'s derived default. Delete the
  workbench's `$workspacePages` map and both literal `url:` strings — if either
  survives this phase, the phase did not land.
- **26.d** — `config/wire-panels.php` (the package's first), `hasConfig()` on its
  provider, `RegistersPageRoutes` and the double-registration refusal. Last
  because it is the only phase an application can see, and because the three
  mechanics in §5 are worth testing against a seam that already works.

## How it gets verified

Unit tests will not see the failure mode this ADR is about. A menu entry whose
href went dead, a route registered at a prefix another key already owns, and a
palette result that navigates nowhere all render valid markup.

- `ResourceRoutesTest`, `GlobalSearchTest` and the `Workspace` tests cover the
  seam swap and must gain a case for *a dashboard reached through the same
  helper*.
- The browser gate is where 26.c is actually checked:
  `verify-workspace-nav.mjs` (the sidebar's hrefs, after the map is deleted),
  `verify-resource-routes.mjs` (a routed non-resource), `verify-global-search.mjs`
  (a result that navigates to the record it names).
- `vendor/bin/pest --filter ModuleLayers` gates 26.a and 26.b: `Foundation/` may
  import nothing above itself, which is the whole reason `Catalog` lives there.
- 26.d needs a test no other phase does: `route:cache`, then resolve
  `wire.{key}.index` from the cached collection. Both broken versions —
  discarded, and registered twice — serve a request fine without it, which is
  how this class of bug reaches production.

## Consequences

- **Good.** The menu, the router and the palette gain a registered thing at the
  same moment. Today they gain it in three, and a dashboard reaches exactly one.
- **Good.** The hand-written key→URL map disappears from the application. It is
  currently required, was documented as required, and the reason it was required
  is removed rather than worked around.
- **Good.** The collision rule fires for applications that draw their own
  navigation — today they are the ones it cannot protect.
- **Trade-off.** An application with a latent key collision starts failing at
  route registration instead of never. That is the intent, and it is still a
  behaviour change that will surprise exactly one person once.
- **Trade-off.** `wire-core` grows a `Foundation/Registration/` module and a
  routing contract while `wire-core` is already the largest package (ADR 0025).
  Four small files against three duplicated reads.
- **Trade-off.** Two renames (`NavigationSource` → `RegistrySource`,
  `ProvidesResourcePages` → `ProvidesPages`) touch published names.
  Cheap now, not cheap after 2.0 ships — which is an argument for sequencing this
  before the release, not for skipping it.
- **Good.** An application that wants the convention writes `enabled => true`,
  a prefix and a middleware list, and stops maintaining a route file section that
  only ever grew by one line per resource.
- **Trade-off.** Auto-registered routes match before `routes/web.php`, because
  package providers boot first. For most applications that is invisible; for one
  with a catch-all under the same prefix it is a silent takeover, and the only
  defence is that opting in is deliberate.
- **Trade-off.** Two ways to register the same routes, which the docs must
  disambiguate — the same "two-path confusion" risk ADR 0020 named for
  standalone vs owner-driven pages, and answered the same way: both stay first
  class, the manual one stays the reference.
- **Neutral.** Nothing here makes routing less opt-in. `Route::wireResources()`
  is still a call an application makes, in a group it chose, and the config path
  is off until an application turns it on.

## Risks

- **Scope creep into a Panel Builder.** Every phase above adds a map, never an
  owner; the moment a `Catalog` acquires a prefix, a layout or middleware, it has
  become the thing ADR 0020 refused. Mitigation: invariant 5 and the review
  question "does this hold a URL, or a URL *shell*?".
- **`Catalog` becoming a second `Workspace`.** It must not grow grouping,
  ordering or visibility — those belong to the menu and already have an owner.
- **The config growing into a panel.** `routes.prefix` and `routes.middleware`
  are one `Route::group()`'s arguments; `routes.layout`, `routes.brand` or
  `routes.login` are a `Panel` arriving one key at a time. Mitigation: invariant
  6, and the fact that a second group is impossible to express — which is the
  constraint doing the work, not the discipline.
- **A soft seam that stays soft.** `ResolvesPageUrls` with only a null binding in
  core is untested by anything that renders a link. 26.c is not finished until a
  browser driver follows one.

## Open questions

1. **Names.** `Catalog` avoids collision with `ResourceRegistry` /
   `DashboardRegistry`, but "registry of registries" may read better as something
   else. Decide before 26.a; renaming after is cheap only until it is published.
2. **Where a standalone page registers.** A page that is neither a resource nor a
   dashboard has no registry today. A third `RegistrySource` is the obvious
   answer and is deliberately not decided here — the seam is what makes it a
   later, small change instead of a fourth direct read.
3. **Route name prefix.** `wire.{key}.{page}` is hardcoded; two installations in
   one application would collide. Out of scope, but it is the next thing to break
   after this lands — and §5's config makes it likelier, since an application
   that never writes a route file has no place to notice the collision.
4. **Which config file.** `wire-panels.php` is consistent (every other package
   owns one) and puts the routing switch in the package that owns the URL
   convention. The counter-argument is that `wire-core.php` already holds
   `resources` and `dashboards`, so an application would declare *what exists* in
   one file and *how it is reached* in another. Decide before 26.d.

## References

- `architecture/decisions/0020-application-owner-layer.md` — §5 routing opt-in,
  invariant 5, Q4 registration.
- `architecture/decisions/0025-core-module-layers.md` — the layer map, and the
  soft-seam precedent this reuses.
- `packages/core/src/Core/Resources/Contracts/NavigationSource.php` — the
  inversion this generalizes.
- `packages/core/src/Core/Resources/Workspace.php` — the collision rule, and
  `registered()`'s "whatever kind it is".
- `packages/panels/src/Routing/ResourceRoutes.php:69`, `:158` — the direct reads.
- `packages/core/src/GlobalSearch/GlobalSearch.php:45` — the second one.
- `packages/core/src/WireCoreServiceProvider.php:439`, `:481` — where the source
  list and the domain-module spread already treat the registries as one thing.
- Commit `51d7d5a` — why the sidebar keeps its own URL map, and what would have
  to change.
