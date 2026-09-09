# ADR 0030: The Hook Surface — Coverage Across the System, One Vocabulary

## Status

ACCEPTED — 2026-09-05, implemented the same day.

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

### 5. New hooks are typed-only (implemented — both new hooks are)

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

`infolist.configuring` is **not** shipped. It has no consumer yet, which is the
bar §6 sets, and shipping it to complete a grid is what this section exists to
prevent.

Named, specified, and **not shipped** until something asks: `navigation.building`,
`page.mounting`, `search.querying`, `export.configuring`, `widget.configuring`.
The bar is this repo's own and it has held all through V2: an extension point
with no consumer is an untested promise, and V2.6 found four live defects in
exactly the code that had never had one.

Macro coverage follows the same rule: make `Form` and `Infolist` macroable when a
module needs it, not to complete a grid.

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
