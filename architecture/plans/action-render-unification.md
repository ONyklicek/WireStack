# Action render unification — one button path, per-surface resolvers (analysis & plan)

**Goal.** Make the action layer extensible by **surface** instead of by
**class**. Today a new place that shows an action (the empty state, a command
palette, a panel header) needs a new Blade view, a new branch in a `render()`
method, and its own copy of the size map. After this plan it needs one small
`ResolvesActionClick` implementation and nothing else.

Two things force this rather than merely recommend it: `wire-core` currently
renders **`wire-table::` views** (against the dependency graph), and the fluent
API of `BulkAction` accepts calls that render nothing at all.

Written 2026-07-29, after `Table::emptyStateActions()` shipped — that feature is
what exposed the seam (see §2.2).

## 1. Current state (measured)

### 1.1 Who renders what

| Class | LOC | `render()` signature | View it renders |
| --- | --- | --- | --- |
| `Action` | 367 | `(?Model, ?ResolvesActionClick)` | `wire-core::actions.button` ✅ |
| `HeaderAction` | 58 | `()` | **`wire-table::tables.actions.header-action`** ⬅ |
| `BulkAction` | 53 | `()` | **`wire-table::tables.actions.bulk-action`** ⬅ |
| `ActionGroup` | 547 | `(Model, ?ResolvesActionClick)` | `wire-core::actions.group` ✅ |

`grep -rn "wire-table::" packages/core/src` returns exactly two hits — the two
marked rows (`HeaderAction.php:51`, `BulkAction.php:43`).

### 1.2 What each button surface actually supports

Read from the three views. ✅ = supported, ✕ = the fluent setter exists on
`BaseAction` and is silently ignored.

| Capability | `actions.button` (core) | `header-action` (table) | `bulk-action` (table) |
| --- | --- | --- | --- |
| Resolves state in PHP (`toButtonRenderArray`) | ✅ | ✕ (Blade calls getters) | ✕ |
| Memoised record-invariant render | ✅ | ✕ | ✕ |
| `url()` / renders as `<a>` | ✅ | ✅ | ✕ |
| Loading spinner + `loadingText()` | ✅ | ✅ | ✕ |
| `keyboardShortcut()` binding + `<kbd>` chip | ✅ | ✅ | ✕ |
| `badge()` | ✕ | ✅ | ✕ |
| `disabled()` | ✅ | ✕ | ✕ |
| `extraAttributes()` | ✅ | ✕ | ✕ |
| `iconPosition()` / `hideLabel()` | ✅ | ✕ | ✕ |
| Host click via `ResolvesActionClick` | ✅ | ✕ (hardcoded) | ✕ (hardcoded) |

### 1.3 Duplicated size/class maps

Canonical owner: `Foundation\Concerns\HasSize::getButtonSizeClasses()`
(`HasSize.php:95`), reached through `BaseAction::getButtonClasses()`.

| Site | Scale it encodes |
| --- | --- |
| `HasSize::getButtonSizeClasses()` ✅ canonical | `xs px-2 py-1` · `sm px-2.5 py-1.5` · `md px-3 py-2` · `lg px-4 py-2.5` |
| `header-action.blade.php:13` | `xs px-2 py-1` · `sm px-3 py-1.5` · `md px-4 py-2` · `lg px-5 py-2.5` ⬅ **differs** |
| `bulk-action.blade.php:9` | matches canonical padding, re-encoded by hand |
| `Actions\Concerns\HasButtonStyles` + `Concerns\HasButtonStyles` | two dead legacy copies (base string + own map); referenced **only** from `LegacyHasButtonStylesTest` / `DeprecatedAliasesTest` |

## 2. Why this is not tidiness

### 2.1 A standalone `wire-core` cannot render a header action

The package graph is `sortable → table → forms → core`. `HeaderAction` and
`BulkAction` live in core and render `wire-table::` views, so in a split-published
`nyoncode/wire-core` the first header action throws `View [wire-table::…] not
found`. This is the **same defect class** the 1.14.0 core↔forms decoupling
removed ("*a standalone core boots and references only its own dependencies*") —
these two sites were not part of that sweep.

### 2.2 The `emptyStateActions()` branch is the symptom

`Table::getEmptyStateActionsHtml()` has to write:

```php
$rendered = $action instanceof HeaderAction
    ? $action->render()                 // its own view, hardcoded host methods
    : $action->render(null, $click);    // canonical view + injected resolver
```

The branch exists **only** because the two kinds render through different paths.
`ResolvesActionClick` was introduced precisely so core "never hardcodes a
table/form Livewire method" (Rendering Rule 6), and one of three action kinds
uses it. Every future surface pays this tax again.

### 2.3 Dead fluent API

`BulkAction` inherits `HasKeyboardShortcut`, `HasLoadingState` and `HasBadge`
from `BaseAction`, and `bulk-action.blade.php` renders none of them:

```php
BulkAction::make('archive')
    ->keyboardShortcut('mod+d')   // type-checks, does nothing
    ->loadingText('Archiving…')   // type-checks, does nothing
    ->badge(3)                    // type-checks, does nothing
```

An API that accepts a call and silently drops it is worse than one that lacks
the method.

### 2.4 Rendering Rule 1

`header-action.blade.php` / `bulk-action.blade.php` call `getLabel()`,
`getIcon()`, `renderIconSvg()`, `getSize()` from the template. The standard says
PHP is the source of truth and Blade only echoes resolved state — which is what
`toButtonRenderArray()` is for, and it is also where the memoisation lives.

## 3. The canonical pattern already exists

Nothing new needs inventing; `Action` is already the reference implementation:

- **`RendersAsButton`** — the action resolves its whole render state in PHP
  (`toButtonRenderArray(?Model, ?ResolvesActionClick): array`).
- **`ResolvesActionClick`** — the *host* supplies the Livewire expression, so the
  view is host-agnostic. Three implementations exist already:
  `MountActionClickResolver` (core default, `mountAction(…)`),
  `TableActionClickResolver` and `EmptyStateActionClickResolver`.
- **One view** — `wire-core::actions.button` + the shared
  `actions.partials.button-content` (which already renders the `<kbd>` chip in
  markup byte-identical to the header-action view's).

The work is to bring the other two kinds onto it.

## 4. Design

**Kind decides semantics; surface decides the click and the chrome.**

1. `HeaderAction` and `BulkAction` implement `RendersAsButton`, i.e. gain
   `toButtonRenderArray()`. `BaseAction` grows the shared implementation
   (label/color/icon/size/tooltip/disabled/shortcut/loading/extraAttributes);
   `Action` keeps its subclass extras (url closure, quiet/solid, iconButton,
   divider) by overriding and merging.
2. `render(?Model $record = null, ?ResolvesActionClick $click = null): string` on
   all three, all going to `wire-core::actions.button`.
3. Two new resolvers in the **table** package, next to the two that exist:
   - `HeaderActionClickResolver` → `openHeaderActionModal` / `executeHeaderAction`
   - `BulkActionClickResolver` → `openBulkActionModal` / `executeBulkAction`
4. `toButtonRenderArray()` gains two keys the unified view needs:
   - `testId` — so the contract stays `action-{name}` / `header-action-{name}` /
     `bulk-action-{name}` (today the view hardcodes the `action-` prefix).
   - `badgeHtml` — `HasBadge` output, rendered after the content (today only the
     header view does this).
5. Delete `wire-table::tables.actions.header-action` + `bulk-action`, and both
   dead `HasButtonStyles` copies.

Net: **one view, three kinds, N surfaces.** A new surface = one resolver class.

## 5. Phases

Each phase is independently shippable and leaves the suites green.

**Phase 0 — pin the current markup.**
Golden-master tests capturing the exact rendered HTML of a header action and a
bulk action across the axes that will move: size xs/sm/md/lg, colour, outlined,
icon, tooltip, url, shortcut, loading, badge, hidden-by-authorization. These are
the byte-identity guard for Phases 2–3 (same discipline the render-engine plan
used for `renderCellFast`). Any intended delta is then a *deliberate* diff to
this file, not a surprise.

**Phase 1 — `BulkAction` onto the canonical path.**
The smallest kind and the one with the most missing behaviour, so the payoff is
immediate and the blast radius is one Blade `@foreach` (`index.blade.php:698`).
- `BulkActionClickResolver` (table).
- `BulkAction implements RendersAsButton`; `render(?Model, ?ResolvesActionClick)`.
- Bulk bar passes the resolver; `bulk-action.blade.php` deleted.
- New tests: shortcut binds, loading spinner gates on the bulk expression, badge
  renders, `data-testid="bulk-action-{name}"` unchanged.
- CDP: `verify-mobile-selection`, plus any driver touching the bulk bar.

**Phase 2 — `HeaderAction` onto the canonical path.**
- `HeaderActionClickResolver` (table).
- `HeaderAction implements RendersAsButton`; same signature.
- Toolbar (`index.blade.php:519`) passes the resolver; `header-action.blade.php`
  deleted.
- **Visual delta to decide here** — see §6.2.
- CDP: the action-modal drivers (`verify-nested-modal`, `verify-wizard-live`) and
  `verify-empty-state-actions`.

**Phase 3 — collapse the empty-state branch.**
`Table::getEmptyStateActionsHtml()` loses its `instanceof` branch and becomes a
plain `array_map` over `render(null, $click)`. `getMobileEmptyStateActionsHtml()`
keeps only the `withoutKeyboardShortcut()` clone. This phase is the proof the
refactor worked: it should *delete* code, not add it.

**Phase 4 — remove the dead style layer.**
Delete `Actions\Concerns\HasButtonStyles` and `Concerns\HasButtonStyles` with
their two legacy tests, leaving `HasSize::getButtonSizeClasses()` as the sole
owner. Check `LegacyHasButtonStylesTest` for any assertion that documents live
behaviour before deleting it.

**Phase 5 — document the seam.**
`AI_CODING_STANDARD.md#Rendering` gains the rule in one line ("an action renders
through the canonical button view; the host supplies the click via
`ResolvesActionClick` — never a hardcoded Livewire method in a view"), plus the
wire-core boost guideline and `AI_COMPONENT_CATALOG.md` (the click resolvers are
not catalogued today).

## 6. Backwards compatibility

### 6.1 Published views

`WireTableServiceProvider:49` calls `publishViews()`, so a consumer may have
published and edited `tables/actions/header-action.blade.php`. Deleting it is a
breaking change for them. Two options, decide before Phase 1:

- **(a) Delete now** — matches `AI_CHANGE_PROTOCOL` ("prefer consolidation over
  compatibility layers"), and the plan lands in a minor. Riskier for consumers.
- **(b) Keep as shims** — each view reduced to a one-line delegation to the
  canonical view, removed in v2.0 alongside the other `@deprecated` items.

Recommendation: **(b)**, because the cost is two files of one line each and the
repo already has a v2.0 removal window it uses for exactly this.

### 6.2 Visual delta on header buttons (the one real regression risk)

The header view's scale is one step wider than the canonical one:

| size | today (header) | after (canonical) |
| --- | --- | --- |
| sm | `px-3 py-1.5` | `px-2.5 py-1.5` |
| md | `px-4 py-2` | `px-3 py-2` |
| lg | `px-5 py-2.5` | `px-4 py-2.5` |

So toolbar buttons get slightly tighter and match row actions. Either:

- **accept it** — consistency across surfaces is the point of the refactor, and
  the delta is 2px; refresh the docs screenshots; or
- **keep it** — add a named scale to `HasSize` (e.g. `getButtonSizeClasses($size,
  $iconOnly, $scale = 'default'|'roomy')`) so the toolbar stays roomy *through the
  canonical owner* instead of a local `match`.

Do **not** resolve this by leaving the local map in place. Decide, write it down,
and let the Phase 0 golden master record the choice.

### 6.3 Contracts that must not move

- `data-testid`: `action-{name}` / `header-action-{name}` / `bulk-action-{name}`
  (documented in `docs/table/advanced.md` § Browser Testing Selectors and relied
  on by the CDP drivers).
- Livewire method names (`executeHeaderAction`, `openHeaderActionModal`,
  `executeBulkAction`, `openBulkActionModal`) — they move *into resolvers*, they
  do not change.
- `HeaderAction::render()` / `BulkAction::render()` stay callable with no
  arguments (the new parameters are optional), so `{{ $action }}` and
  `$action->render()` in consumer code keep working.

## 7. Out of scope

- `ActionGroup` (547 LOC) and its dropdown/lazy-menu machinery — it already takes
  a resolver and renders a core view; only its size/colour call sites would be
  touched, and only if §6.2 chooses the `$scale` option.
- `ModalFooterAction`, `ModalStep`, `ActionHalt` — different surfaces with their
  own views; they are candidates for the same treatment later, but each has its
  own semantics and none of them has the ownership inversion.
- Turning a modal's intrinsic submit/cancel buttons into `Action` objects — an
  explicitly separate initiative (`AI_CODING_STANDARD.md`, Modal Rule 4).
- Any change to the action **execution** pipeline. This plan is render-only.

## 8. Verification checklist

Per phase:

1. `composer test:core` → `composer test:table` → `composer test:forms` →
   `composer test:sortable` (core is the owner; everything is downstream).
2. `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`.
3. `composer lint` + `composer analyse`.
4. `composer coverage:verify` — every changed line covered; no floor drops.
5. The golden master from Phase 0 — an intended diff is reviewed and re-pinned,
   an unintended one is a bug.
6. CDP drivers named per phase; `npm run verify:drivers` before the final phase
   lands.
7. `npm run docs:refresh` only if §6.2 changes the rendered padding.
