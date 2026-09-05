# ADR 0029: Domain Modules as Installable Packages

## Status

PROPOSED — 2026-09-05. Requested by the repo owner ("možnost přidávat hotové
části jako balíčky"), with one constraint stated on top of it — **"balíček by měl
přidávat, ne přepisovat"** — which §3 turns into the invariant the rest hangs
from. Nothing implemented; sequenced in
[`plans/v3-optional-admin-and-module-packages.md`](../plans/v3-optional-admin-and-module-packages.md).

Extends [ADR 0014](0014-plugin-architecture.md) (plugin lifecycle) and the domain
axis of [ADR 0017](0017-erp-crm-application-architecture.md) layer 5, realised as
`DomainModule`. It adds no second registration system — the point is that one
already carries this and two of its edges are unwritten.

## Context

A "ready-made part" is a composer package that installs a whole business area:
`nyoncode/wire-module-users`, `…-settings`, `…-audit-viewer`. Measured against
the tree on 2026-09-05, three quarters of that is already true.

### What already works

`DomainModule` (`core/Core/Modules/DomainModule.php`) **is** a `Plugin`: it names
resources, dashboards and a navigation group, and `WireCoreServiceProvider::bootModules()`
(`:510`) spreads those into `ResourceRegistry`, `DashboardRegistry` and
`NavigationGroups`. Because all of them are `Catalog` sources, a module's
resources reach the menu, the router and the search palette from one declaration,
zones included. The package's own config, views, translations, migrations, assets
and install command are the toolkit's job and need nothing new.

### Edge 1 — a package can already self-register; the module page does not say so

`docs/core/plugins.md` **does** document this, under "Register Plugins From A
Package" — the correction to this ADR's first draft, which claimed it was
undocumented. What no page connected was the *module* to that path:
`docs/core/modules.md` documents exactly one, "list the class in
`config('wire-core.plugins')`", which is an application's path. A package cannot
edit an application's config, and does not have to:

```php
// packages/sortable/src/WireSortableServiceProvider.php:30
->registeredPackage(function ($packager) {
    $this->app->resolving(PluginManager::class, function (PluginManager $manager) {
        if (! $manager->has('sortable')) {
            $manager->register(new SortablePlugin);
        }
    });
})
```

The seam exists, it has a consumer, and it is the right one — `resolving` fires
before the singleton is handed out, so registration lands before
`PluginManager::boot()` and before `bootModules()` reads the list. What was
missing is that it is the way to ship a *module*, and the phase rule that makes
it work.

### Edge 2 — registering too late fails silently, and that is the trap

`PluginManager::register()` has no guard against `$this->booted`
(`core/Core/Plugin/PluginManager.php:56`, and `booted` is only read in `boot()`
at `:105`). A package provider that registers in **boot** instead of **register**
— the obvious mistake, and the one a package author makes first — gets:

- `register()` runs, so `$manager->has($id)` is true afterwards;
- `boot()` is never called on it, because the manager has already booted;
- `bootModules()` has already run, so its resources, dashboards and navigation
  group land nowhere.

No exception, no log line. The module is "installed", and the menu is empty.
Every diagnostic an author would reach for reports success.

### Edge 3 — adjusting a module's resource, without replacing it

`ResourceRegistry::register()` refuses two different classes on one key (`:60`),
and `Catalog::all()` refuses it again across sources. Both refusals are right and
both stay: the alternative is one registration silently taking another's routes,
in a menu and at a URL prefix.

The consequence is that the first thing an application wants from an installed
module — "the users list needs one more column" — cannot be done by subclassing
the module's resource, because a subclass keeps the parent's key and collides.
So the question this ADR has to answer is not *how to overwrite*, it is **what
the additive path is**, and whether it exists. Measured: two thirds of it does
(`PluginManager` hooks and macros, ADR 0014), and the third — declining a module
resource an application does not want — has nothing.

## Decision

### 1. `resolving(PluginManager::class)` is the documented way a package ships a module

No new API. `docs/core/modules.md` and `docs/cs/core/modules.md` gain a "shipping
a module as a package" section that states the phase (`register`, never `boot`),
the `has()` guard, and why both matter, and `docs/core/plugins.md` gains the
phase rule beside the pattern it already showed. The sortable provider is the
reference implementation and is named as one.

**Implemented 2026-09-05**, with the workbench standing in for a module package:
`BillingModule` now arrives through `WorkbenchServiceProvider`'s `resolving`
callback while `OperationsModule` stays in config, so both paths have a
consumer — and the dependency between them still resolves, because `resolving`
callbacks run before the `afterResolving` one that reads config.

### 2. Late registration throws

`PluginManager::register()` refuses a plugin once the manager has booted:

```php
throw PluginRegistrationException::registeredAfterBoot($id);
```

— alongside `alreadyRegistered()` and `missingDependency()`, which is the
existing vocabulary of that exception. The message names the phase to move the
call to, because the author's next question is exactly that.

**Implemented 2026-09-05.** Two things the implementation added to this decision:

- The guard runs **first**, before the duplicate check, and the test asserts the
  plugin is absent afterwards — a guard placed after the assignment would throw
  and still leave the plugin in the list.
- Config validation is the same failure one layer out, so it went with it:
  `WireCoreServiceProvider` refused nothing before, and a typo'd class name in
  `wire-core.plugins` was skipped in silence. Now a blank entry is skipped (a
  trailing comma), anything else that cannot be a plugin raises
  `notAPlugin()` naming which of the two mistakes it is, and a non-array value
  raises `invalidPluginList()` instead of a PHP error inside a provider.

This is chosen over the alternative of *accepting* a late plugin and booting it
immediately: booting it late would work for a plugin that only registers macros
and would still silently drop a module's declarations, so it would fix the
symptom for half the cases and hide the other half deeper.

### 3. A module adds. It never overwrites — and neither may the application

This is the invariant, and everything below is its consequence: **installing a
package may only add registrations.** No module may claim a key another
registration owns, no module may replace an application's class, and the
`Catalog` / `ResourceRegistry` collision refusals are not an obstacle to route
around — they are how the invariant is enforced. A `replace` map keyed by
resource class was drafted and is **rejected**: it makes "which class actually
serves `users`" depend on boot order and on a config file far from either
declaration, and it is the exact mechanism that turns an installed package into
something that can silently take a page over.

The additive paths, in the order an application should reach for them:

1. **Change the component, not the class.** A hook on the composed instance:
   `Hook::TableComposing` for a list, `Hook::FormConfiguring` for a form, each
   scoped with `for: '<key>'` to the module that registered it. One more column
   on a module's list is that hook, and it survives the module's next release,
   which a fork does not.

   **This is where the ADR was wrong when it was written**, and the correction is
   worth carrying: it named `table.configuring`, which fires inside the query
   service and steers the planner — a column added there is searched and sorted
   on and never rendered. The additive rule had no mechanism at all behind it for
   tables until [ADR 0030](0030-hook-surface.md) added one. An infolist, a
   dashboard, a menu and a page still have none; that ADR names them and holds
   them until something asks.
2. **Configure the module.** A module is a plugin, so `HasConfiguration` +
   `config('wire-core.plugins.config.{id}')` is already merged for it
   (`PluginManager::register()`). A module that expects to be adjusted ships the
   knobs; that is the module author's contract with its installers, not the
   framework's.
3. **Decline what you do not want, then add your own.** The missing piece:
   `DomainModule` gains no method, but the provider that spreads modules learns
   an application-side skip list —

   ```php
   // config/wire-core.php
   'modules' => [
       'except' => ['users.roles'],   // registered keys, not classes
   ],
   ```

   — read in `bootModules()`. An application that declines a module's resource
   registers its own, with its **own key**, and keeps every guarantee: no
   collision, no ambiguity about which class owns a URL, and a menu entry that is
   visibly the application's. The key changes, and that honesty is the feature.

Rejected alongside the `replace` map:

- **Container binding per resource class** — invisible in a menu, and a resource
  is addressed statically (`key()`, `pages()`), so half the surfaces would never
  resolve the binding.
- **Letting a later registration win** — the failure ADR 0026 moved the
  duplicate-key check into `Catalog` to prevent, arriving through the front door.

### 4. Discovery stays out

`v2-progress.md` §4 lists "opt-in module discovery" as waiting for a consumer.
It still is: a package registers itself through its own provider, which composer
already discovers. Scanning `App\Modules\` for classes would be a second answer
to a question the provider answers.

## Consequences

### Positive

- The first third-party module becomes writable without an application editing
  config, and without the framework growing a registry.
- The failure that costs the most time — installed, silent, empty — becomes an
  exception at boot.
- An application can adopt a module without forking it — through hooks, module
  config and a skip list — which is the difference between a package and a
  starter template. And it can do it without any registration ever being
  overwritten, so "which class serves this URL" stays answerable by reading one
  declaration.

### Negative / risks

- The guard is a **behaviour change**: an application registering plugins late
  starts throwing where it used to half-work. Measured: nothing in this repo
  registers after boot (`WireCoreServiceProvider` and `WireSortableServiceProvider`
  are the only registration sites). A downstream application might; it belongs in
  the upgrade notes.
- **The additive rule has a cost, and it is paid at the key.** An application
  that wants a substantially different resource does not inherit the module's
  URLs and menu position; it declines and registers its own. That is deliberate
  — but it means a module author must treat its resources as extensible
  (hooks, config) rather than as classes people will subclass.
- `modules.except` is a third place that decides what is registered. It is
  small, it is application-side only, and it removes rather than overrides —
  which is the one shape that cannot make a registration ambiguous. Ship it with
  the first module that needs it, not ahead of one.
