# ADR 0020: Application Owner Layer (`Resource` / `Page` / `Workspace`)

## Status

ACCEPTED — 2026-08-30.

Implemented and exercised on a real entity (workbench `InvoiceResource`, driven
in a browser by `workbench/scripts/verify-resource-pages.mjs`). Two things landed
differently from this ADR and from the V2.3 plan; both are recorded in
[`v2-progress.md`](../plans/v2-progress.md) §2:

- **Where it lives.** Not one package. Each contract ships with the type it
  names — identity and the registry in `wire-core`, `ProvidesResourceForm` in
  `wire-forms`, `ProvidesResourceInfolist` beside Infolists, and
  `ProvidesResourceTable` plus every page in the new top package `wire-panels`.
  A resource with a form and no list therefore installs no table package.
- **What shape it is.** Not one class with eight methods, and not one interface
  either: `AI_CODING_STANDARD.md` § Interfaces allows exactly one capability per
  interface, so identity and each surface are separate contracts a resource
  implements only where it has them.

Gate for **V2.3** (`architecture/plans/v2-master-plan.md`). Realizes ADR 0017
Application Surfaces (layer 4) and closes its gap #1 ("missing first-class
application owners"). Depends on V2.0 (`DataSource`, ADR 0019) and V2.1 (headless
`WithTable`/`Column` split) landing first.

## Context

Wire exposes strong **primitives** — `Form`, `Table`, `Action`, `Widget`,
`Modal` — but has **no first-class application owner** above them. Business
ownership currently ends inside a Livewire component that mixes `WithTable` /
`WithForms` / `WithWidgets` traits with hand-wired actions. There is no stable
layer for:

- `Resource` (a model + its list/create/edit/view/relations as one owner)
- `ListPage` / `EditPage` / `ViewPage`
- `RelationManager` (a base exists — see below)
- `Workspace` / `Dashboard` grouping

Verified today (2026-07-06):

- Host traits are the composition unit: `WithTable`
  (`packages/table/src/Concerns/WithTable.php`), `WithForms`
  (`packages/forms/src/Forms/WithForms.php`), `WithWidgets`
  (`packages/core/src/Widgets/Concerns/WithWidgets.php`).
- The **only existing owner** is `RelationManager`
  (`packages/table/src/RelationManagers/RelationManager.php`): an abstract
  `Livewire\Component` that `use WithTable`, forces the table query onto
  `ownerRecord->{relationship}()`, and adds persistence helpers
  (`createRelatedRecord`, `attachRelated`, `detachRelated`). It is the proof that
  an owner = **thin composition over primitives**, not a new engine.

ADR 0017 is explicit that this layer must exist but must **compose** primitives
without owning their internals, and warns (risk) that if `WithTable`/`Column`
aren't shrunk first (V2.1), a new owner layer just "glues onto the monoliths."

## Decision

Introduce an **Application Owner Layer** as a thin, additive composition tier
over the existing primitives and host traits — **not** a parallel runtime.

### 1. `Resource` — the integration owner

A `Resource` binds a **model (or `DataSource`)** and declares the surfaces and
capabilities for that business entity in one place:

```php
abstract class Resource
{
    public static function model(): string;                 // or dataSource()
    public function table(Table $table): Table;             // list surface
    public function form(Form $form): Form;                 // create/edit schema
    public function infolist(Infolist $list): Infolist;     // view surface (optional)

    /** @return array<class-string<RelationManager>> */
    public function relationManagers(): array;

    /** @return array<class-string<Page>> */
    public function pages(): array;                          // list/create/edit/view

    public function navigation(): NavigationItem;            // label/icon/group/sort
    public function policies(): void;                        // authorize* wiring
}
```

`Resource` owns **business ownership and cross-surface consistency** (the same
columns/labels/policies flow to list, edit, view, relations). It does **not**
re-implement query, save, or render — it delegates to `Table`/`Form`/`Infolist`.

### 2. `Page` owners — Livewire components composing host traits

`ListPage` / `EditPage` / `ViewPage` are Livewire components that reuse the
existing trait pattern (exactly like `RelationManager` does today):

- `ListPage` `use WithTable`, pulls its table from `Resource::table()`.
- `EditPage` / `CreatePage` `use WithForms`, pull schema from `Resource::form()`,
  persist through the existing forms runtime (`SaveHandler`, `Form::using`).
- `ViewPage` renders `Infolist` (read-only) from `Resource::infolist()`.

Pages are **standalone-usable** without a `Resource` too (a page can define its
own table/form), preserving the current low-ceremony usage.

### 3. `RelationManager` — promoted, not replaced

The existing `RelationManager` becomes a first-class member of this layer: a
`Resource` lists its relation managers; `EditPage`/`ViewPage` embed them. No
breaking change to the current `@livewire(PostsRelationManager::class, [...])`
usage.

### 4. `Workspace` / `Dashboard` — grouping owners

`Workspace` groups resources + dashboards + navigation for a business area
(`crm`, `sales`, …), and is the seam the future Domain Module axis (ADR 0017
layer 5, V2.6) plugs into. `Dashboard` composes `Widget`s via `WithWidgets`.

### 5. Registration & routing — light, opt-in

- Resources/pages **register** through a simple registry (config array +
  discovery), not a full Panel Builder. `boost`'s `ComponentScanner` pattern is
  the model for introspection.
- **Routing is opt-in.** A page is a Livewire component; the app may bind routes
  itself, or use a thin `Resource::routes()` helper. V2.3 does **not** ship a
  panel/shell/URL scheme — that is deliberately out of scope (candidate V3 Panel
  Builder).

## Boundary invariants

1. **Owners compose, never own internals** (ADR 0017 invariant B). `Resource` /
   `Page` call `Table`/`Form`/`Action`/`Widget`; they do not reach into query,
   state, or persistence engines.
2. **Primitives stay standalone.** Every primitive and host trait works exactly
   as in V1 without any owner. The layer is purely additive.
3. **Trait composition is the mechanism**, consistent with `RelationManager`.
   No new god-object; a `Page` is a Livewire component + existing host trait(s)
   + a small owner concern.
4. **Cross-surface consistency flows from `Resource`.** Columns, labels,
   policies, and (via V2.0) the `DataSource`/tenant scope are declared once and
   reused by list/edit/view/relations — the single owner point ADR 0017 invariant
   G expects.
5. **No routing/panel lock-in in V2.3.** The layer must be usable inside any host
   app's own routing/navigation.

## Migration (phased, additive)

- **V2.3.a** — `Resource` + `ListPage` + registry; a `Resource` composes an
  existing `Table`. Promote `RelationManager` under the layer (no signature
  change).
- **V2.3.b** — `EditPage`/`CreatePage`/`ViewPage` over `WithForms` + `Infolist`;
  `Resource::form()`/`infolist()` wiring; cross-surface policy flow.
- **V2.3.c** — `Workspace`/`Dashboard` grouping + navigation contract + `boost`
  introspection (`DescribeResource` tool) + docs.
- All additive: no deprecations required. Existing standalone components and
  `@livewire(RelationManager)` usage are untouched.

## Consequences

### Positive
- Wire gains the application-framework shape ADR 0017 targets; business ownership
  has a stable home instead of leaking into ad-hoc Livewire components.
- One declaration point per entity → consistent columns/labels/policies/tenant
  scope across list/edit/view/relations.
- The seam the Domain Module axis (V2.6) and a future Panel Builder (V3) plug
  into, without committing to either now.
- Reuses `DataSource` (V2.0) and the headless engine (V2.1) — owners inherit
  read-source flexibility and tenant scoping for free.

### Negative
- More classes/contracts; a second way to build a page (owner-driven vs
  standalone) that docs must disambiguate.
- Value is muted until V2.1 shrinks `WithTable`/`Column` — sequencing matters.

### Risks
- **Gluing onto monoliths** (ADR 0017 explicit risk): if `WithTable`/`Column`
  aren't headless first, owners inherit the monolith's blast radius. **Mitigation:
  hard-gate V2.3 behind V2.1.**
- **Scope creep into a Panel Builder.** Navigation/registration must stay minimal;
  URL scheme/shell is out of scope. **Mitigation: routing opt-in, no shell.**
- **Two-path confusion.** Standalone vs owner-driven must both stay first-class;
  don't deprecate standalone usage.

## Open questions — all closed

1. ~~Static declaration or instance owner?~~ **Hybrid**, and the reason turned out
   to be mechanical rather than stylistic: a menu asks for a label and
   `ResourceRegistry::forModel()` routes a model to its owner *before anything is
   instantiated*, so metadata cannot require an instance. Surfaces are instance
   methods because they compose a builder the caller already wired to its host.
2. ~~Does `Infolist` cover `ViewPage`?~~ **Yes.** `ViewPage` renders one and
   composes no host trait at all — read-only means no state to bind and nothing
   to submit.
3. ~~Non-Eloquent persistence?~~ **Through `Form::using()`**, declared in the
   resource's own `form()`; the pages are unchanged. A `DataSource` write
   contract stays out of scope (ADR 0019 open question #4).
4. ~~Registration?~~ **Config array** (`config('wire-core.resources')`), read by
   `ResourceRegistry::registerMany()`. Attribute discovery remains the opt-in
   second path and belongs with boost's `ComponentScanner`, not the registry.

## What the prototype found

The plan asked for one real entity before this was called finished, and that was
right — two defects survived a full unit suite and died in the browser:

- `ListPage` never bound the model the resource declares, while the form pages
  did. Every fixture had set `->model()` inside its own `table()`, so the page
  not doing it was invisible.
- `CreatePage` did not seed its state bag, so a select or a date picker
  entangled a path that was not there and silently never wrote.

## References

- `architecture/plans/v2-master-plan.md` — V2.3 gate; V2.1/V2.0 prerequisites.
- `architecture/decisions/0017-erp-crm-application-architecture.md` — layer 4
  (Application Surfaces), invariants B/G, migration Phase 3, gap #1.
- `architecture/decisions/0019-data-source-contract.md` — source/tenant scope the
  owner reuses.
- Existing owner: `packages/table/src/RelationManagers/RelationManager.php`.
- Host traits: `WithTable`, `WithForms`, `WithWidgets`.
