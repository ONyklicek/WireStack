# Rule 5 framework-wide — modal component sweep (analysis & plan)

**Goal.** Rendering standard Rule 5: *"The framework MUST NOT depend on `<x-*>`
components. The core renderer MUST work without them."* The icon sweep
(2026-07-18) removed the last leaf `<x-*>` dependency from the **per-row data
render path**. This plan covers the remaining structural `<x-*>` usages — the
modals — and decides whether/how to bring the framework to literal full
compliance.

## 1. Current state (measured)

`grep -rE "<x-wire[a-z-]*::" packages/*/resources/views` (framework render paths):

| Component | Count | Real usage? |
| --- | --- | --- |
| `<x-wire::icon>` | 1 | Only the **component definition** (`foundation/icon.blade.php`) — the consumer API. **0 real usages.** ✅ |
| `<x-wire::empty-state>`, `<x-wire::callout>` | 2 | **Comment-only** (docblock mentions). The markup lives in `partials/empty-state.blade.php` / `callout.blade.php`; both the PHP owner (`Foundation\Schema\EmptyState`/`Callout`) and the `<x-*>` tag delegate to that partial. **Already compliant.** ✅ |
| `<x-wire-modals::modal / confirmation / slide-over>` + alias `<x-wire::modal>` | **9** | **Real structural usages** across 4 files. ⬅ the remaining work |

The 9 modal usages:

| File | confirmation | modal | slide-over |
| --- | --- | --- | --- |
| `core/actions/modal-host.blade.php` | self-closing | slot body+footer | slot body+footer |
| `forms/partials/select-option-modals.blade.php` | — | slot body+footer ×2 | — |
| `table/partials/action-modal.blade.php` | self-closing | slot body+footer | slot body+footer (wizard) |
| `table/partials/halt-modal.blade.php` | slot body | — | — |

## 2. Why modals are NOT icons

Icons were a clean sweep because they are **leaf primitives on the per-row hot
path** — a string helper (`icon()`) trivially replaces them with a measurable
win. Modals are the opposite on every axis:

- **Structural + slotted.** They use the default `$slot` (body) and a named
  `<x-slot:footer>`. `@include` has no slots.
- **Logic-rich class.** `ModalComponent` = 15 constructor props + 8 presentation
  methods (`widthClass`, `mobileVariant`, `containerClasses`,
  `panelVariantClasses`, `transitionClasses`, `bodyVariantClasses`,
  `iconBgClass`, …). `ConfirmationComponent`/`SlideOverComponent` similar.
- **O(1) per page**, not per row → **zero perf argument** (the whole reason the
  icon sweep paid off does not apply).
- **Heavily tested + CDP-fragile.** The modal stacking rewrite (live frame-stack,
  stable show-flag vs Alpine morph-reset) is verified by an 8/8 CDP driver; the
  action-modal + wizard stack rides on it.

## 3. Rule 5 — what does it actually govern?

> "The **core renderer** MUST work without them."

The core **data renderer** — rows, cells, fields, entries — already renders with
**zero `<x-*>`** after the icon sweep. A table renders every row without touching
a modal component; modals are a **separate action-UI subsystem**, reached only
when an action opens one. So two honest readings:

- **(Literal)** *any* `<x-*>` in *any* framework view is a dependency → convert
  the modals too.
- **(Scoped)** Rule 5 governs the core data-render path + leaf primitives; that
  path is now clean. Structural, slotted, O(1)-per-page UI (modals) is a
  legitimate use of the framework's own Blade components.

## 4. The canonical pattern already exists (empty-state / callout)

`empty-state` and `callout` show the **right** shape and are the template for a
principled fix:

- The markup lives in **one canonical partial** (`partials/empty-state.blade.php`).
- The **PHP owner** (`Foundation\Schema\EmptyState`) renders that partial.
- The **`<x-wire::empty-state>` tag** is a *thin consumer wrapper* over the same
  partial.

So the framework depends on the **partial**, not the component; the `<x-*>` tag
stays available for consumers (Rule 5 explicitly allows *users* to use `<x-*>`).

Modals do **not** follow this: the shell markup + presentation logic live **inside
the component class**, and the framework reaches the component. That is the gap.

## 5. Plan — Option A (literal compliance, via the empty-state pattern)

Bring modals to the empty-state shape: extract the shell into a canonical partial
+ a style object, make the framework `@include` it, and keep `<x-wire-modals::*>`
as thin consumer wrappers.

**Phase 0 — extract the shell (no behaviour change). ✅ DONE (2026-07-18) — all
three modals.** Each got a `Modals\Support\*Style` value object (presentation logic
off the component, unit-tested) and a shell-ified view (`$style->…()` +
`@isset($bodyView) @include(...) @else {{ $slot }}` for body **and** footer), so each
renders identically via the component *and* via a slot-less `@include`; each component
reduced to props + `style()` + `render()`.
- ✅ **modal** → `ModalStyle` (8 methods, test 8/8), `modals.modal` shell-ified.
- ✅ **confirmation** → `ConfirmationStyle` (width/icon/submit-button, test 3/3),
  `modals.confirmation` shell-ified (body slot → `bodyView`-or-slot).
- ✅ **slide-over** → `SlideOverStyle` (position/panel/width-wrapper/4×translate/width,
  test 5/5), `modals.slide-over` shell-ified.

Verified byte/behaviour-faithful with **call sites untouched**: core **1629** / table
**1283** green; phpstan + pint clean; CDP modal-mobile (variants), modal-layering
**13/13**, nested-modal **8/8** (incl. the confirmation flow), wizard-live **14/14**
(action-modal body/footer through the slots). The three shells now accept
`bodyView`/`footerView`, so **Phase 2 (call-site → `@include`) is unlocked.**
- New `Modals\Support\ModalStyle` value object: move the 8 presentation methods
  off `ModalComponent` (constructed from the same props). Unit-test it 1:1
  against the current component output.
- New canonical partial `wire-core::modals.shell` taking `['style' => ModalStyle,
  'bodyView' => string, 'bodyData' => array, 'footerView' => ?string,
  'footerData' => array, ...props]` — renders header/body/footer via
  `@include($bodyView, $bodyData)` instead of `$slot`. All Alpine/Livewire modal
  machinery (open/close, transitions, z-index, click-away, escape, mobile
  variants) moves here verbatim from the component view.
- Re-point `ModalComponent`/`Confirmation`/`SlideOver` `render()` to the shell
  partial (component becomes the thin wrapper — the empty-state shape). **Suites
  + CDP stay green with no call-site change yet.** ← proves the shell is faithful.

**Phase 1 — confirmation call sites. ✅ DONE (2026-07-18) — as Htmlable objects.**
Direction change: after the *Modal Best Practices* were supplied, Phase 2 was
chosen to be **Htmlable modal objects** (`{{ $modal }}`) rather than `@include`,
because Modal Rule 5 requires the modal to *implement Htmlable and own one view*
while the general Rendering Rule 5 forbids a `<x-*>` dependency — a Htmlable value
object echoed with `{{ }}` satisfies both (Filament-style). Confirmation is the
first modal migrated to it.
- New `Modals\Confirmation implements Htmlable` — owns the `modals.confirmation`
  view, consumes `ConfirmationStyle`, carries `wireModel`/`wireClick`/`body`.
- The shell now takes `wireModel`/`wireClick` (and `body`/`bodyView`) explicitly,
  falling back to `$attributes`/`$slot` (`??`, short-circuit) so the consumer
  `<x-wire-modals::confirmation>` tag still renders the same markup byte-for-byte.
- All **3** confirmation call sites converted to `{{ new Confirmation(...) }}`:
  `core/actions/modal-host.blade.php`, `table/…/action-modal.blade.php`,
  `table/…/halt-modal.blade.php` (Form body passed as the Htmlable `body`).
- Verified: core **1631** / table **1283** green; phpstan + pint clean;
  `ConfirmationObjectTest` (Htmlable + live Livewire render: dialog, forwarded
  `wire:click`, body, no `<x-*>`); **CDP `verify-confirmation-object` 8/8** — real
  DeleteAction confirmation opens, confirm carries `wire:click="submitActionModal"`,
  cancel closes, re-opens, confirm fires the action, zero Alpine errors.

**Phase 2 — modal + slide-over call sites (Htmlable objects). IN PROGRESS.**
Objects live in **`Modals\Html\`** (render objects) — distinct from `Modals\*`
(config, `ModalContract`) and `Modals\View\*Component` (Blade components).
`Modals\Confirmation` was moved here too (→ `Modals\Html\Confirmation`) for
consistency. Body/footer accept a pre-rendered `body`/`footer` (string/Htmlable)
OR a partial to `@include` (`bodyView`/`bodyData`, `footerView`/`footerData`) — the
latter lets a call site keep its body/footer partial and hand it the current view
scope via `get_defined_vars()`.
- ✅ **modal-host** (core action modal — highest blast radius) → `Modals\Html\Modal`
  + `Modals\Html\SlideOver`; body/footer keep `modal-host-body`/`modal-host-footer`
  partials via `bodyView`/`bodyData: get_defined_vars()`. `modal.blade` /
  `slide-over.blade` gained the `wireModel` preamble (`@entangle($modelBinding)`,
  `?? $attributes` fallback) + `$body` support. Verified: core **1633** / table
  **1283**; `ModalHtmlObjectTest`; **CDP wizard-live 14/14, nested-modal 8/8,
  modal-layering 13/13, modal-mobile ✓, confirmation-object 8/8**.
- ✅ **action-modal** (table) slide-over + modal → `Modals\Html\SlideOver` +
  `Modals\Html\Modal`. Inline wizard body/footer extracted **verbatim** to partials
  (`action-modal-body`, `action-modal-slideover-footer`, `action-modal-modal-footer`),
  fed via `bodyView`/`footerView` + `get_defined_vars()`.
- ✅ **select-option-modals** (forms) → 2 × `Modals\Html\Modal`; inline form
  body/footer extracted to mode-parameterised partials (`select-option-modal-body`,
  `select-option-modal-footer`).

**Zero `<x-wire-modals::*>` / `<x-wire::modal>` remain** in any framework render
path (only comments). Verified: core **1633** / forms **884** / table **1283**;
phpstan + pint clean; CDP wizard-live **14/14** (incl. the select create-option
modal), nested-modal **8/8**, modal-layering **13/13**, modal-mobile ✓,
confirmation-object **8/8**.

**Phase 3 — components as consumer API + docs. ✅ DONE (2026-07-18).**
- `Modals\View\*Component` (`<x-wire-modals::*>`) **kept as the consumer API**
  (Rendering Rule 5 lets users use `<x-*>`). The framework no longer uses them; they
  render the *same shell* as the Html objects — one source of markup, two entry points
  (component with `$attributes`/`$slot`, object with explicit data). Not made to delegate
  to the objects (a Blade class-component `render()` can't cleanly reach its slots, and
  there is no markup duplication to remove).
- `AI_CODING_STANDARD.md` → `### Modals` + boost `wire-core` guideline document the
  three families (`Html\*` render / `Modals\*` config / `View\*Component`) and the
  Htmlable-object pattern.
- **Coverage:** all six new files 100% (`Html\*` + `Support\*Style`); floors held
  (core → 94.1%, table → 85.2%).

**Tidy-ups done (2026-07-18):**
- **Modal Rule 4:** the confirmation now renders *additional* footer actions
  (`->modalFooterActions([ModalFooterAction…])`) through the Action API
  (`modal-host-footer-action`, before/after position), consistent with the general modal.
  The intrinsic primary submit + secondary cancel stay data-driven shell buttons by
  design (Filament pattern); turning THOSE into `Action` objects is a separate initiative.
  Standard note corrected accordingly.
- **`verify-select-floating`** was a **stale driver**, not a regression: it expected a
  mobile bottom sheet, but `field-select-floating` is a `->searchable()` select and
  searchable selects deliberately **float** on mobile (the sheet path is
  `verify-select-sheet`, 8/8). Driver assertions corrected → **8/8**. (`matchMedia` reads
  the emulated 390px correctly; the panel is `absolute` by design.)

**Risk:** HIGH (Livewire/Alpine modal stack). **Effort:** LARGE (multi-session).
**Perf benefit:** none (O(1)/page). **Payoff:** literal framework-wide Rule 5.
Every phase gated on the modal/action/wizard suites **and** CDP.

## 6. Plan — Option B (scoped Rule 5 + codify)

Accept the scoped reading and make it explicit in `AI_CODING_STANDARD.md`:

- Rule 5 governs the **core data-render path** (per-row/cell/field/entry) + leaf
  primitives — now fully `<x-*>`-free.
- Structural, slotted, O(1)-per-page UI (modals) is a **sanctioned use** of the
  framework's own Blade components; the component model (`$slot`/`<x-slot>`) is
  the right tool there. Consumers and the framework may use them.
- Cross-reference this plan. **Cheap, zero risk. Does not achieve literal
  framework-wide compliance.**

## 7. Recommendation

The empty-state/callout precedent proves Option A is *doable the right way* (shell
partial + thin wrapper), so literal compliance is achievable without losing the
consumer `<x-*>` API. But the payoff is **letter-only** (no perf, O(1)/page) and
the risk sits on the most fragile, most-tested subsystem in the codebase.

**Recommended:** decide by appetite.
- If the goal is a *provably* `<x-*>`-free framework: **Option A**, phased, Phase 0
  first (faithful shell extraction, zero call-site change) to de-risk before
  touching call sites — bail to Option B if Phase 0 shows the shell can't match
  byte/behaviour.
- If the goal is a clean, defensible standard now: **Option B** — the core data
  renderer is already compliant; codify the modal exception and stop.

Either way, **the per-row data render path — what Rule 5 most cares about — is
already 100% `<x-*>`-free.** The open question is only the modal subsystem.
