# WireStack Architecture & Coding Standard

Binding standard for all code in this repository, human- or AI-authored. Where
this file and habit disagree, this file wins. `CLAUDE.md` routes here; read it
before writing code, not after.

## Philosophy

WireStack is built around **composition over inheritance**.

The framework is a collection of small, independent capabilities that combine.
Every component stays modular, reusable, extensible, and easy to test. When
multiple implementations are possible, choose the one that maximizes composition
and minimizes inheritance.

## General Principles

- Composition over inheritance.
- Interfaces before implementations.
- Small, reusable building blocks.
- Single Responsibility Principle.
- SOLID compliance.
- Convention over configuration.
- Explicit APIs over magic.
- Prefer readability over cleverness.
- Keep public APIs stable.

## Interfaces

Interfaces define contracts only. They describe capabilities, contain no
implementation, stay small, and expose only public methods.

```php
interface HasLabel
interface HasColor
interface HasIcon
interface HasState
interface CanBeDisabled
interface CanBeHidden
```

One interface represents exactly one capability. Never create large interfaces
containing unrelated methods.

## Traits

Traits provide reusable implementations. A trait implements a single capability,
stays cohesive and reusable, and avoids hidden side effects.

Naming:

```text
InteractsWithLabel
InteractsWithColor
InteractsWithState
CanBeDisabled
CanBeHidden
```

Traits stay lightweight. If a trait becomes difficult to understand, split it.
**Traits must not become mini-frameworks.**

## Business Logic

**Traits are not the place for business logic.** Business logic belongs in:

- Actions
- Services
- Managers
- Pipelines
- Support classes

A trait exposes a clean API and delegates the complex work.

Good:

```php
public function save(): void
{
    app(SaveRecord::class)->execute($this);
}
```

Bad:

```php
public function save(): void
{
    // 400 lines of logic
}
```

## Abstract Classes

Avoid abstract classes. Introduce one only when ALL of the following apply:

- shared protected state is required
- a shared lifecycle exists
- composition would make the code worse

Otherwise use interfaces and traits. Abstract classes should be rare.

## Classes

Classes stay small: under 300 lines, one responsibility, minimal dependencies.
Avoid "God Objects".

## Final Classes

Prefer `final class Service`. Make a class extendable only for a clear
architectural reason.

## Dependency Injection

Never instantiate services manually.

```php
new UserRepository();                    // bad

public function __construct(             // good
    private UserRepository $repository,
) {}

app(UserRepository::class)               // good
```

## Fluent API

Builder methods return `static`.

```php
$field->label('Name')->required()->disabled();
```

Every public fluent setter carries a one-line `/** … */` docblock summary.
`describe-component-api` (wire-boost) surfaces that summary to agents as the
method's fluent-API description via reflection, so a bare signature leaves the
agent guessing the method's purpose and vocabulary. A guard test
(`tests/Feature/FluentApiDocumentationTest`) fails if any chainable method
across the `TypeCatalog` is missing a summary.

## Naming

| Kind | Examples |
| --- | --- |
| Interfaces | `HasLabel`, `HasIcon`, `CanDelete`, `CanSort` |
| Traits | `InteractsWithLabel`, `InteractsWithIcon`, `CanDelete`, `CanSort` |
| Actions | `CreateUser`, `DeleteUser`, `SyncPermissions`, `GenerateColumns` |
| Services | `TranslationService`, `AssetManager`, `NavigationManager` |
| Managers | `PluginManager`, `ComponentManager`, `ThemeManager` |

## Directory Structure

```text
Contracts/      Interfaces
Concerns/       Traits
Actions/        Single-purpose actions
Services/       Stateless services
Managers/       Stateful coordinators
Support/        Helper classes
Enums/
Exceptions/
Builders/
Collections/
ValueObjects/
DTOs/
Events/
Listeners/
Pipelines/
Commands/
Policies/
```

## State

Avoid mutable shared state. Prefer immutable value objects. Configuration is
explicit.

## Static Methods

Avoid static methods. Allowed only for factories, named constructors, and pure
helpers. Never hide business logic behind a static call.

## Facades

Facades stay thin and forward to services. No business logic inside a facade.

## Exceptions

Throw meaningful exceptions. Never silently ignore failures. Prefer a dedicated
exception class over a generic `RuntimeException` where appropriate.

Concretely (see ADR 0022):

- Every exception lives in the owning package's `Exceptions/`, is `final`, and
  implements `NyonCode\WireCore\Foundation\Contracts\WireException` so a consumer
  can catch the whole stack in one clause.
- **Extend the SPL class the failure really is** — `InvalidArgumentException` for
  a bad argument, `RuntimeException` for a bad state. When replacing an existing
  throw, keep the base it already threw: the SPL class is published behaviour,
  and the two are siblings, so switching breaks every `catch`. A domain class
  therefore never spans two bases; split by base first, then by domain.
- Use static named constructors (`UnsafeSqlException::reservedIdentifier($id)`)
  rather than a subclass per message. Carry context a handler might want as
  readonly properties, not only interpolated into the message.
- Never return an error shape (`['error' => ...]`) from a domain or support
  class. Throw, and catch at the layer that can answer for it — the MCP tool
  boundary, the Livewire host. A layer that must not throw catches there, once.
- Wrap a foreign failure instead of flattening it: keep the original as
  `previous`.
- A `catch` that *decides* something (no auth context → deny; an untyped method
  that must be called to be identified) is a probe, not swallowing — but write
  the reason down. A `catch` that discards information the caller needed is a bug.

## Testing

Everything is testable. Avoid hidden dependencies, favor constructor injection,
keep units isolated.

Coverage is enforced, and the rule is **the code you write is covered**: CI fails
a change whose added or edited lines are not run by a test
(`scripts/verify-coverage.php`). The target is 100%; the repository does not
reach it yet, so each package is additionally held to a floor
(`scripts/coverage-floors.json`) that may only ever go up. This is the honest
version of the goal — a blanket `--min=100` today would fail every build and be
switched off within a week, which enforces nothing.

Write the test with the code. A line that genuinely cannot be reached gets a
reason, not a bare `@codeCoverageIgnore` — and note that the annotation does not
cover the `} catch (...) {` line itself, only the body, so an untestable
defensive catch is usually better deleted than annotated.

## Public API

Public APIs are stable. Changing a public method requires backward-compatibility
consideration. Internal implementation may change. Contracts stay stable.

## Performance

Avoid premature optimization. Optimize only when profiling shows a real
bottleneck. Prefer maintainability over micro-optimizations.

## Rendering

The rendering contract. All renderable components MUST follow these unless a doc
explicitly documents an exception.

1. **PHP is the source of truth.** Components hold all state, configuration and
   rendering logic. Blade views MUST NOT contain business logic, branch on domain
   state, query services, or mutate state.
2. **Every component owns exactly one view and renders itself.** Each renderable
   component defines one view/markup and drives its own render; there is no central
   view registry.
3. **Components implement `Htmlable`.** `{{ $component }}` must render without helpers.
4. **Blade is presentation only.** A view outputs HTML/attributes and renders children;
   it does not query, branch on business rules, or mutate.
5. **The core renderer MUST NOT depend on `<x-*>` components.** `<x-wire::*>` is the
   *consumer-facing* API; users may use it in their published views. Core partials emit
   PHP-resolved strings (see Icons). The framework must render with `<x-*>` disabled.
6. **Components are isolated.** A component knows only itself, its children, and its
   configuration — never the Livewire component, parent, routing, or HTTP request
   (host-specific behaviour is injected via a contract, e.g. `ResolvesActionClick`).

**The render-cost model is binding — it is *how* rule 2 is satisfied, not a tradeoff
against it.** Every `view()->render()`, every `<x-*>` Blade component, and every
`@include` is one *view render*; inside a per-row / per-cell / per-item loop that is
**N×View** — the framework's dominant cost. "A component renders itself" MUST be
implemented as **resolve the view once into a reusable skeleton and splice only
per-record state per row** — NOT as `view()->render()` per cell. Per-cell
`view()->render()` is a correct-but-slow implementation of rule 2 and is the anti-
pattern to eliminate (per-column Htmlable skeletons; see the plans below). Rule 2 and
speed are the *same* requirement: self-render, done once. Record-invariant markup MUST
be resolved once, never re-rendered per row.

**Reference implementation:** `Column::renderCellFast()` — resolve `tables.columns.text`
once into a skeleton with a content token, splice `e($state)` per row (measured
byte-identical to `renderCell()` and ~5× cheaper; one view render per column, not V×R).
It falls back to the full `renderCell()` when the skeleton cannot apply — a per-record
url/copy/description-closure (`isCellSkeletonable()`), or a subclass that overrides
`renderCell` with its own view (`supportsCellSkeleton()`). Any new fast path MUST carry
the same two guards: a **byte-identity test** vs the classic render across escaping /
edge-whitespace / unicode / html / empty content, and the **render-count fuse** proving
zero per-row view renders.

Pick the mechanism by *what varies per row*: **content columns** (structure fixed, only
the value changes) use the **skeleton splice** above; **state-driven columns**
(Badge/Icon/Boolean/Toggle/Select — markup is a function of a low-cardinality state) use
`renderViewCached()` — the **view render memoised by its data payload**, so rows sharing
a state reuse one render (O(distinct states), not O(rows)). Keying on the actual `$data`
keeps it byte-identical with no "pure function" assumption; its values MUST be
serialisable. **Interactive and state-branching cells are the boundary**
where the skeleton does not apply and per-cell render is kept: inline-edit
(`TextInputColumn` — per-record input value, `wire:key`, per-record Alpine commit
config) and Responsive / Split / Poll. Evaluate §7 per column-family, never blanket.

- Resolve record-invariant markup (spinner, check, chrome) once in its **canonical
  PHP owner** — `Foundation\View\Primitives`, `Foundation\Icons\IconManager` — and
  echo the cached string. NEVER wrap a canonical owner in a second cache: the owner
  *is* the resolve-once. (`IconManager::render()` already memoises every SVG.)
- Per-row work that is column/record-static (classes, resolved icons, view-name
  resolution) MUST be memoised per instance or hoisted into the render-once preamble
  (the `$columnMeta` pattern), never recomputed per cell.
- **Prefer cheap eager HTML over lazy deferral.** The default answer to an expensive
  surface is to make its render *cheap* (build-once skeleton, splice per row) and emit
  the full HTML eagerly — not to defer it to the client. Eager-cheap keeps the full
  DOM (accessibility, SEO, no-JS), adds no open latency, and needs no client-side
  re-render. Lazy/deferred rendering (e.g. `ActionGroup::lazyMenu()`) is an **opt-in**
  lever for the narrow *payload/DOM-size-bound* case only — many large menus over many
  rows, rarely opened — and it trades render cost for open latency, a JS dependency,
  and DOM that is absent until opened. Never reach for lazy to paper over a render that
  should simply be made cheap.

### Icons

Icons follow one pipeline — PHP produces the markup, Blade only consumes it:

```
icon()  →  IconManager::render()  →  SVG cache  →  <svg> string  →  Blade {!! … !!}
```

```blade
<td>
    {!! icon('outline:chevron-right', 'w-3 h-3') !!}
</td>
```

- **Every framework/core render path emits icons through the `icon()` helper**
  (`icon($name, $size = 'w-4 h-4', $class = '', $label = '', array $attributes = [])`) —
  a plain PHP function that returns the memoised `IconManager::render()` string. It is
  the Rule-1 "PHP source of truth": presentation is resolved in PHP, the template only
  echoes a string.
- Core render paths MUST NOT use, in order of wrongness:
  - a hand-written inline `<svg>` — breaks theming and the icon-set abstraction;
  - `<x-wire::icon>` — a Blade component = one view render per call;
  - a Blade **directive** (there is deliberately no `@icon` directive) — a directive is a
    template-compiler construct that puts size/class presentation in the view, against
    Rule 1. Call the `icon()` function instead.
- **Alpine/`data-*`-bound icons still come from PHP.** A binding that must live on the
  `<svg>` root (`x-show`, `::class`, `wire:*`) is passed as the `$attributes` argument —
  `{!! icon('clipboard', 'w-4 h-4', 'text-gray-400', '', ['x-show' => '!copied']) !!}` —
  which `IconManager`/`ResolvedIcon` forward onto the root. No inline `<svg>`, no component.
- `<x-wire::icon>` remains the **external, consumer-facing API** only (published views,
  app code). It is registered for consumers; the framework itself never renders through it.

**State-driven components memoise the whole view.** A display component whose markup is a
pure function of a low-cardinality, serialisable state (icon/boolean/badge entries, the
table's Badge/Icon/Boolean columns) renders its view **once per distinct state**, not once
per row, via the canonical `Foundation\Concerns\HasViewRenderCache` (core) /
`HasView::renderViewCached` (table). The component declares a `renderCacheSignature()` that
captures all render-affecting state and returns `null` whenever the markup carries
per-record identity (record key, statePath, action wiring) or is content-driven (unique per
row — plain text) — those must not be shared.

**Guard it.** Per-row render cost is fuse-tested with a wildcard view composer
(`View::composer('*')` counts every view render, incl. `@include`/`<x-*>`). A change
that adds a per-row `@include`, component, or `view()->render()` MUST NOT regress the
fuse. See `architecture/plans/render-engine-htmlable-first.md` and
`render-optimization-audit-2026-07-17.md`.

### Modals

A modal is a **Htmlable value object**, not a `<x-*>` dependency. This reconciles the
two Rule-5s: the modal *implements `Htmlable` and owns exactly one Blade view* (Modal
Best Practices Rule 5), and the framework renders it by echoing the object — no `<x-*>`
in a core render path (Rendering Rule 5).

- The framework renders modals with **`{{ new Modals\Html\Modal(...) }}`** /
  `Confirmation` / `SlideOver`. Each object owns one shell view (`modals.modal`,
  `modals.confirmation`, `modals.slide-over`), consumes a `Modals\Support\*Style` value
  object for its layout classes, and carries `wireModel` (→ `@entangle`), `wireClick`,
  and body/footer as either a pre-rendered `string`/`Htmlable` or a partial to
  `@include` (`bodyView`/`bodyData`, `footerView`/`footerData`).
- Three parallel families under `Modals\`: **`Html\*`** = the Htmlable *render* objects
  (this section); **`Modals\Modal` etc.** = modal *config* (`ModalContract`, fluent,
  `toArray()`); **`Modals\View\*Component`** = the Blade components. Do not conflate them.
- **`<x-wire-modals::modal>` / `<x-wire::modal>` stay the consumer-facing API** — users
  may use them in their own views (Rendering Rule 5). Their view is the *same shell*, so
  there is one source of truth for markup; the shell reads `wire:model`/slots off the
  attribute bag on the component path and takes explicit `wireModel`/`body`/`footer` on
  the object path (`?? $attributes`, short-circuit). The framework itself never uses the
  component.
- **Presentation logic lives in the `*Style` value object**, never the component/object
  (per *Business Logic*). Extract before adding a new modal surface.
- **Modal Rule 4 (footer actions via the Action API).** *Additional* footer actions use
  `->modalFooterActions([ModalFooterAction::make(...)])` and render through the Action API
  (`modal-host-footer-action`) on **every** modal surface — the general modal, slide-over,
  **and the confirmation**. A modal's *intrinsic* primary submit + secondary cancel are
  data-driven buttons in the shell (label/color/`wireClick`), the same on all surfaces and
  the same as the Filament confirm/cancel pattern — not arbitrary hardcoded `<button>`s.
  Extend a footer with `ModalFooterAction`, never an ad-hoc `<button>`. (Turning the
  intrinsic primary buttons themselves into first-class `Action` objects is a separate,
  larger initiative — out of scope for the modal render sweep.)

Verify modal changes with the CDP drivers (`verify-nested-modal`, `verify-modal-layering`,
`verify-modal-mobile`, `verify-wizard-live`, `verify-confirmation-object`) — the modal
stack is the most Alpine/Livewire-fragile subsystem; green PHP tests are not enough. See
`architecture/plans/rule5-framework-wide-modal-sweep.md`.

## Documentation

Public classes should be self-explanatory. Document public APIs, extension
points, and contracts. Avoid documenting obvious code.

## AI Code Generation Rules

When generating code for WireStack:

- Prefer interfaces over abstract classes.
- Provide reusable traits for interface implementations.
- Split responsibilities aggressively.
- Keep methods small.
- Keep classes focused.
- Never generate God Objects.
- Never place complex business logic inside traits.
- Prefer composition over inheritance.
- Use dependency injection.
- Prefer final classes.
- Follow Laravel coding conventions.
- Produce clean, maintainable, extensible code.

If multiple solutions exist, always choose the one that results in the most
modular and composable architecture.
