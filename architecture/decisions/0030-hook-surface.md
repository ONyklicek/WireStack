# ADR 0030: The Hook Surface — Coverage Across the System, One Vocabulary

## Status

ACCEPTED — 2026-09-05, implemented the same day. **Amended twice on 2026-09-08:
§6's ratchet was spent, deliberately — first the six remaining hooks, then three
more in forms and table plus the macro coverage §6 had parked.** Eighteen names,
eleven of them typed-only. See
[§6 as amended](#6-coverage-grows-by-named-consumer-never-by-symmetry) — the rule
still reads the way it did, because the reasoning is still right and the decision
to override it was the repo owner's, taken in the open. What that section now
records is which of the six arrived with a consumer and which arrived on the
owner's judgement, so the next person can tell the two apart.

One measurement changed the decision after it was written, and it is the reason
this ADR is worth re-reading rather than skimming: **`table.configuring` cannot
add a column to a table.** The context table below said it could. It runs inside
`TableQueryService`, on the arrays the planner is about to consume, so a column
added there is searched and sorted on and never rendered — which means ADR 0029's
additive promise had *no* mechanism behind it for tables, not a partial one. It
was found by the first test that rendered a page rather than asserting on a
query, and the fix is `Hook::TableComposing`, dispatched once on the composed
instance in `WithTable::getTable()`.

Originally PROPOSED — 2026-09-05. Requested by the repo owner: *"systém háčků by tedy měl
být rozmanitý, aby se uživatelům dobře pracovalo napříč systémem."* Nothing
implemented.

It follows from [ADR 0029](0029-modules-as-installable-packages.md) §3 rather
than sitting beside it. That ADR ruled that an installed package may only **add**,
never overwrite, and pointed applications at hooks as the way to adjust what a
module ships. That turns the hook surface into load-bearing structure: **an
additive rule is only as good as the interception it leaves behind.** Where a
hook is missing, "adjust it, do not replace it" is advice with nowhere to go, and
the pressure returns as a request to overwrite.

Extends [ADR 0014](0014-plugin-architecture.md), which introduced hooks and named
seven of them.

## Context — measured 2026-09-05

### Seven names, three dispatch sites, one axis

| Hook | Dispatched in |
| --- | --- |
| `table.configuring`, `table.querying`, `table.queried` | `table/Services/TableQueryService.php:88, 212, 363` |
| `form.saving`, `form.saved` | `forms/Forms/Runtime/SaveHandler.php:68, 112` |
| `action.executing`, `action.executed` | `core/Actions/Concerns/InteractsWithActions.php:546, 716` |

All three sites are the **runtime data path**: query, save, execute. Nothing
intercepts composition anywhere except tables.

### What has no interception at all

`grep PluginManager` over `core/Infolists`, `core/Widgets`, `core/GlobalSearch`,
`core/Core/Resources` and all of `packages/panels` returns **nothing**. So of the
surfaces a domain module actually ships:

| Surface | Can a third party change it without owning the class? |
| --- | --- |
| a table's columns/filters | **it looked like yes** — `table.configuring`. Measured: no. See the status note; it steers the planner, not the table |
| a table's query | **yes** — `table.querying`, query pipes |
| an action's execution | **yes** — `action.executing/executed` |
| a form's **save** | **yes** — `form.saving/saved` |
| a form's **schema** | **no** — there is no `form.configuring` |
| an infolist | **no** |
| a dashboard / widgets | **no** |
| the menu | **no** |
| a resource page (mount, record resolution) | **no** |
| global search | **no** |

That asymmetry is the whole finding: "add a column to the users module's list" is
writable today; "add a field to its form" is not, and the two requests arrive
together.

### Macros are asymmetric too

`use Macroable` appears in exactly two classes: `table/Table.php` and
`core/Actions/BaseAction.php`. `Form`, `Infolist`, `Column`, `Field`, `Filter`,
`Dashboard` and `Workspace` are not macroable, so the second-best extension path
is missing everywhere the first one is.

### Three mechanisms already overlap, and nothing says which is which

Hooks are not the only interception in the repo. There are Laravel **events**
(`TableSearching/Searched`, `TableFiltering/Filtered`, `TableRefreshed`,
`CellUpdating/Updated`, `RecordCreated/Updated/Deleted`, `ActionExecuting/Executed`)
and **fluent callbacks** (`modifyQueryUsing`, `beforeSave`, `afterSave`,
`authorizeUsing`). `InteractsWithActions` fires the `action.executing` **hook**
and the `ActionExecuting` **event** at the same moment, ten lines apart.

Adding hook names without drawing a line between the mechanisms is how a user
ends up reading three chapters to find out which of them can change a value.

### Payloads carry no identity

`TableConfiguringPayload` is `(object $table, array $columns, array $filters)`;
the action payloads carry `?object $component`. `Table::getLivewireComponent()`
returns `mixed`. So a callback that wants to touch **one** module's list has to
duck-type its way to an answer, and every plugin callback runs for every table in
the application. Three modules installed means the same `if` written three times,
by hand, against untyped objects.

### Two dispatches per site, and names are bare strings

Every lifecycle point runs `runHook()` and `runTypedHook()` back to back — a
deliberate 2.x BC arrangement documented at length in `PluginManager`. It also
means every *new* hook would cost two dispatches and two payload shapes. Names
are string literals at both the registering and the dispatching end, with no
canonical vocabulary — while `Foundation/Enums/` is exactly where this repo puts
those.

## Decision

### 1. Four mechanisms, one rule each. Diversity is coverage, not choice

Chosen by **who holds the reference to the component**:

| You… | Use | Mutating |
| --- | --- | --- |
| build the component | fluent API / callbacks (`modifyQueryUsing`, `beforeSave`) | yes |
| want new vocabulary on a class you did not write, applied where you build | **macro** | yes |
| must change a component you never see — a module's page, every table in an app | **hook** | yes |
| only need to know it happened | **event** | **never** |

Where a hook and an event exist for one moment (`action.executing`), the event is
the observation half: audit, telemetry, metrics. Documented as this table, in one
place, because the current cost is not that the mechanisms exist — it is that
nothing says which one answers a given question.

### 2. Every payload names its host

**Implemented.** `Core/Plugin/HookTarget.php`, on all nine payloads as a trailing
`?HookTarget $target = null`, with `HasHookTarget` as the contract the dispatcher
reads and `IdentifiesHookTarget` as the one a host implements to be addressable
by its registered key — which every `wire-panels` resource page now does through
`BelongsToResource`.

*2026-09-08: fifteen payloads, and `DashboardPage` implements
`IdentifiesHookTarget` too, answering with the key of the dashboard it shows. Two
of the six new hooks belong to no component and are therefore targeted by
something else: `navigation.building` by the **zone** the menu is built for, and
`search.querying` by the searched resource's catalogue key. Both build the
`HookTarget` directly rather than through `for()`, because there is no host to ask
— which is the case that method's signature could not express.*

A shared, typed target on every hook payload:

```php
public readonly HookTarget $target;   // registered key (when there is one), host component class, surface
```

`Foundation/` (L0), so table, forms, panels and core surfaces can all name it.
This is the difference between a hook you can write for a module and a hook you
can only write for the whole application.

### 3. Registration may be scoped

**Implemented** as a fourth argument on `hook()`, matched in one place
(`PluginManager::outOfScope()`) for both dispatchers:

```php
$manager->hook(Hook::FormConfiguring, $callback, for: 'users');
```

Filtered at dispatch against the target's key or component class. Without it,
scoping is an `if` at the top of every callback — hand-written, untyped, and
repeated once per module. With it, the common case is declarative and the
uncommon one still writes the `if`.

### 4. `Hook` enum in `Foundation/Enums/`, accepted anywhere a name is (implemented)

`hook()`, `runHook()`, `runTypedHook()` and `hasHook()` take `Hook|string`. The
string stays valid — that is the BC promise and the escape hatch for a hook a
package defines for itself — but the shipped vocabulary becomes discoverable,
typo-proof and greppable, which is what `Foundation/Enums/` already does for
size, color, placement and alignment.

### 5. New hooks are typed-only (implemented — all eleven new hooks are)

The array dispatch is **frozen at the seven legacy names**. A new hook ships one
payload class and one dispatch. Doubling every new lifecycle point to keep a
deprecated shape symmetric would pay BC for callbacks that cannot exist yet.

### 6. Coverage grows by named consumer, never by symmetry

Ship, in this order, each with a consumer that exercises it:

1. **`form.configuring`** — the missing half of `table.configuring`, and the
   reason ADR 0029's additive path currently stops at forms.
2. **`infolist.configuring`** — the same for read-only surfaces.

**Implemented 2026-09-05**, with one addition measurement forced:
**`table.composing`** had to ship alongside `form.configuring`, because the hook
this ADR assumed already covered tables does not (see Status). Both are typed
only, both dispatch once at the memoized composition point — `Form::getConfig()`
and `WithTable::getTable()` — and both are exercised by a test that renders:
`packages/panels/tests/Unit/ScopedHookTest.php` adds a column to one resource
page's list through the key its resource registered under, and asserts the page
beside it is untouched.

`infolist.configuring` was **not** shipped on 2026-09-05. It had no consumer, which
is the bar this section sets, and shipping it to complete a grid is what the
section exists to prevent.

#### Amendment, 2026-09-08 — the remaining six shipped

Three days and five shipped module packages later, the bar had moved under one of them
and the owner spent the ratchet on the rest. Both halves of that are worth
recording, because they are different kinds of decision:

**`infolist.configuring` met the bar as written.** Four of the module packages —
`wire-module-users`, `-audit`, `-media`, `-notifications` — ship five resources
with a detail page between them: `UserResource`, `RoleResource`, `AuditResource`,
`MediaResource`, `NotificationResource`, all rendered by
`WirePanels\Resources\Pages\ViewPage`. So "add a field to the users form" was
writable and "add a row to its detail" was not, on the same resources, which is
precisely the asymmetry this ADR was written about.

**The other five shipped on the owner's call, not on a measured consumer.**
`navigation.building`, `page.mounting`, `search.querying`, `export.configuring`
and `widget.configuring` were requested together, as coverage: *"zlepšení systému
hooks napříč celým systémem"*. That overrides §6, and the override is the
decision — not a re-reading of the rule. Two things were done to keep the cost
honest rather than to argue the rule away:

1. **Every one arrived with a test that exercises it through the surface it
   belongs to**, not through the dispatcher: a rendered page for the infolist,
   widget and page hooks, a real builder for search and export, both `navigation()`
   and `items()` for the menu. The bar §6 defends is "an extension point with no
   consumer is an untested promise"; a test is not a consumer, but it is the half
   of one that catches a promise that never worked.
2. **Each dispatch site was placed by what it can actually change**, which is the
   `table.configuring` lesson applied five more times. Three of the six moved
   after the first measurement: `widget.configuring` before key stamping rather
   than after (a widget added late gets no key, or a borrowed one),
   `export.configuring` into `buildTableExport()` rather than `exportTable()` (or
   the queued file would have been a second, uncovered export), and
   `navigation.building` into `entries()` rather than `navigation()` (or the
   grouped menu and the flat one would disagree about what is in the menu).

Two designs were **rejected** during implementation, and both were rejected by a
measurement rather than by taste. `page.mounting` was going to write a title back
to the page: `$title` is protected on `BelongsToResource`, Livewire's snapshot
carries public properties only, and a page mounts once — so the heading would have
been right on the first paint and gone on every update after, which is the failure
`$breadcrumbZone` on that same trait already exists to avoid. The payload's title
is read-only; what a callback changes is the page's public surface, where a
seeded form state bag does survive.

`search.querying` cost the other: the catalogue key was going to
travel as a fourth argument to `GlobalSearch::searchResource()`. That method is a
documented override point, and PHP rejects a subclass declaring fewer parameters
— so the added argument was a fatal error at class-declaration time in every
application that had ever overridden it. It is looked up from the catalogue
inside the payload closure instead, which costs nothing when no callback is
registered.

**What did not ship in that pass:** macro coverage. See the second amendment
below — it shipped a few hours later, on the same call.

**The residual risk is the one §6 named**, and it is now real rather than
hypothetical: five of these have no consumer outside their own tests. If one of
them turns out to be dispatched at the wrong moment, nothing in the repository
will notice. The mitigation is the same as the diagnosis — the first module or
application that uses one should be treated as the measurement, and the dispatch
site is expected to move if it disagrees.

#### Second amendment, 2026-09-08 — forms and table, measured on their own

The first amendment shipped coverage *across* the system and left the two oldest
packages as they were. Measuring them afterwards found four things, and the owner
took all four.

**One of them this ADR had just created.** `export.configuring` shipped hours
earlier and `import.configuring` did not — and the two are declared the same way,
as an `ExportAction` and an `ImportAction` in one `headerActions()`. That is the
sharpest kind of asymmetry there is: not one the code grew into, one this document
put there. It cost a single dispatch, because unlike its counterpart the import
needed no composition point invented for it — `RunImportJob` mounts the host and
calls `importTable()`, so streamed and queued were already one path.

**One was a real hole rather than an asymmetry.** An inline cell edit is a save,
and it was the only write path in the table with nothing that could change it:
`CellUpdating` and `CellUpdated` are Laravel **events**, which by §1's own rule may
watch a value change and never alter it. A form save had `form.saving`; the cell
beside it had neither. `Hook::CellUpdating` dispatches inside
`CellEditPipeline::commit()` — the one point the inline editor and the fill handle
both funnel through — after the column's permission, conflict and validation
checks, so a callback narrows and cannot widen. It can also refuse: a `refusal` on
the payload returns `CellEditOutcome::rejected()`, which is a shape the pipeline
already had.

**One was an axis, not a point.** Forms could be intercepted on the way *out* and
not on the way *in*. `Hook::FormFilling` sits in `Form::fill()` and deliberately
not in `getInitialState()`: an edit page calls both, so a hook on each would fire
twice per page.

**And the macro rule was spent.** `Form`, `Column`, `Field` and `Filter` are now
`Macroable`, joining `Table` and `BaseAction`. §6 said "when a module needs it, not
to complete a grid", and no module asked — this is the owner overriding that, the
same way and in the same session as the five hooks above. `Infolist` is
deliberately **not** macroable: it is a renderer with four setters, and nothing has
wanted vocabulary on it.

Rejected during this pass, again by measurement: narrowing `TableQueryService`'s
`$pluginManager` to one hook name. It also feeds the **query pipes**, which are a
registry on the manager and not a hook at all, so a name-scoped resolve would have
silently emptied them whenever `table.configuring` had no callback. Each hook block
got its own named guard instead, and `resolvePluginManager()` stayed for the pipes.

### The guard became a class

Eight typed dispatch sites need the same three steps: is a `PluginManager` bound,
is anything listening, and only then pay for a payload. Two sites wrote it out by
hand and six more were about to, so it is `Core/Plugin/HookDispatch.php` and the
two originals delegate to it — the extract-and-delegate move
`AI_CODING_STANDARD.md` § Adapters requires, not a second copy.

The payload arrives as a **closure**, which is the part that matters: building one
means reading a table's columns, a menu's entries or a dashboard's widgets, and an
application that installs no plugin should pay for none of it. `Form::configuredSchema()`
gained the `hasHook()` check it never had by moving.

`HookDispatch::manager()` is the legacy half, added in the second amendment. The
seven double-dispatched names cannot use `typed()` — what each site does with the
two results genuinely differs — so it hands back the manager instead of inventing
a shape that fits none of the four call sites. What it *does* add is the
`hasHook()` short-circuit they never had: `TableQueryService`, `SaveHandler` and
`InteractsWithActions` used to build both payloads whenever a manager was bound,
which on `table.configuring` is once per table per render in an application that
registered nothing.

That optimisation has a measured receipt. It dropped `wire-module-settings` below
its coverage floor, because `SettingsPage::hookKey()` had only ever been exercised
*incidentally* — `Form::configuredSchema()` built a `HookTarget` on every form
config whether or not anything was listening, and building one asks the host for
its key. The method now has a test of its own, which is where a public method's
coverage should have come from in the first place.

One trap the class documents rather than allows: **null means nobody listened, not
"nothing changed"**. A caller folding the two together with `?? $original` would
silently restore its own value whenever a callback emptied the array — and
emptying it is a legitimate answer, which is how a filter that removes every
column would have looked like a no-op.

## Consequences

### Positive

- The additive rule in ADR 0029 becomes writable across the system instead of on
  tables only — which is what makes "install a module and adjust it" true.
- One table answers "which mechanism do I reach for", instead of three chapters.
- A hook can address one module. Today it addresses all of them.

### Negative / risks

- **Hot paths.** `table.configuring` runs per render; a scoped callback must be
  rejected without invoking it. The `$hooks === []` early return already exists
  and the scope check must stay on that side of the invocation. Benchmarks in
  `packages/table/tests/Benchmarks` are the gate.
- **`HookTarget` touches every existing payload.** Additive (a new readonly
  property), but it is a public shape under the 2.x promise, so it is a
  constructor argument with a default, not a reordering.
- **Hook soup.** The mitigation is §6 and nothing else: no hook without a
  consumer. If this ADR is ever cited to justify a hook "for completeness", it is
  being misread.
