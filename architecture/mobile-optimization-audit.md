# Mobile / Tablet Optimization Audit

Date: 2026-07-03 · Branch: 1.7.2 · Scope: all rendered UI surfaces across
`core`, `forms`, `table`, `sortable`.

## TL;DR

Mobile/tablet support is **more mature than it looks** — the recent complaint
("mobile optimization doesn't work well") traced to a single regression in the
action-modal (`slideOverOnMobile` became an awkward side-panel instead of a
bottom-sheet, and collided with `slideOver`). That is **fixed and browser-
verified**. The remaining items below are polish, not structural gaps. Build on
the existing responsive primitives; do not add parallel ones.

## What already exists (reuse, don't duplicate)

| Surface | Capability | Owner / entry point |
|---|---|---|
| Table | horizontal scroll (default) + **card/stacked mode with row actions** | `Table::stackedOnMobile(bp)`; `index.blade.php:778` (card header renders actions at `:831`) |
| Columns | per-breakpoint visibility, separate mobile/desktop content | `HasResponsive` trait; `onlyOnMobile/Desktop`, `visibleFrom`, `hiddenFrom`, `mobileDisplayUsing`, `mobileBreakpoint` |
| Pagination | compact mobile mode | `pagination.blade.php` (`hidden sm:flex` / `sm:hidden`) |
| Modals | bottom-sheet / full-screen / desktop slide-over, composable | `HasModal::slideOverOnMobile/fullScreenOnMobile/slideOver`; `ModalComponent`, `SlideOverComponent` |
| Floating panels | `flip()` + `shift({padding:8})` keep dropdowns in-viewport | `packages/core/resources/js/dropdown.js` (`$float`) |
| Form layouts | responsive section/grid/fieldset/tabs/wizard | `forms|core/resources/views/{layouts,schema}/*` |
| Selection bar / bulk actions | stacks on mobile | `index.blade.php:387` (`flex-col sm:flex-row`) |

## Findings (calibrated, evidence-based)

Severity: **P1** = likely visible on a real phone · **P2** = edge/landscape ·
**P3** = design/consistency enhancement.

### P1

1. ~~**`stackedOnMobile` card mode omits row actions.**~~ **Not a defect** — on
   re-inspection the mobile card header already renders the row's actions
   (`index.blade.php:831–837`, `@if($hasActions)`). The original finding was
   drawn from `:778–782` alone. No change needed.

2. ~~**Date/time picker panel has no height cap.**~~ **DONE (2026-07-03).** The
   shared `$float` primitive now always runs the `size()` middleware, capping a
   floating panel to `Math.min(availableHeight, naturalMax)` with
   `overflow-y: auto`, so a tall panel (calendar, long option list) scrolls
   inside itself on a short (landscape) viewport instead of spilling off-screen.
   The cap only ever *shrinks* — a panel's own `max-h-*` class (e.g.
   column-toggle's `max-h-80`) is captured first and preserved. One change in
   `packages/core/resources/js/dropdown.js` covers every floating surface;
   browser-verified via `workbench/scripts/verify-float-height.mjs` (5/5).

### P2

3. ~~**Touch targets below ~44px.**~~ **DONE (2026-07-03).** The canonical icon
   button size (`HasSize::getButtonSizeClasses(iconOnly: true)`) now emits a
   mobile-first responsive pad — `p-2.5 sm:p-1.5` for the default size (~40px on
   a phone, back to the compact desktop size from `sm` up) — so **every** icon
   action button (row/header/bulk actions, action-group triggers, `ButtonColumn`)
   gets a proper tap target in one change, desktop unchanged. The modal and
   slide-over **close** buttons (modal chrome, not routed through `HasSize`) were
   enlarged in-view (`p-2.5 sm:p-1.5`; a `-m-1.5 p-1.5` hit-area on the slide-over
   close). Browser-verified (`verify-phase2-mobile.mjs`: mobile 10px vs desktop
   6px). **Portability note:** a `@media (pointer: coarse)` / `pointer-coarse:`
   approach was rejected — that variant is Tailwind-4-only and ADR 0005 keeps the
   shared classes compatible with Tailwind 3. The mobile-first `base → sm:` split
   is portable across both.

4. ~~**Grid-based form fields don't reflow.**~~ **Partly DONE (2026-07-03).**
   `checkbox-list` columns now reflow (`grid-cols-1 sm:grid-cols-N`, 4→`grid-cols-2
   sm:grid-cols-4`) and inline radio **cards** stack on mobile
   (`grid-cols-1 sm:grid-cols-none sm:grid-flow-col sm:auto-cols-fr`) instead of
   crushing into one row; both browser-verified. **Left as-is on purpose:**
   `key-value` (an inherently 2-column key/value table — stacking hurts more than
   helps, matches Filament) and `repeater` (its nested fields already reflow via
   the responsive schema grid layouts).

5. **Editor toolbars get tall, not scrollable.** `rich`/`markdown`/`tiptap`
   toolbars use `flex flex-wrap` (`rich-editor.blade.php:77`) so they *wrap*
   (no horizontal overflow — good) but a full button set becomes 2–3 rows on a
   phone. → Optional: single-row `overflow-x-auto` toolbar on mobile.

### P3 (design consistency — Nova/Filament bias)

6. **Extend the bottom-sheet pattern to floating dropdowns.** **DONE — capability
   + every floating surface migrated (2026-07-03).** The shared `$float` primitive
   (`dropdown.js`) accepts `sheetOnMobile: true` (+ `sheetBreakpoint`, default
   640): below the breakpoint it **skips Floating UI** (watching `matchMedia`,
   re-evaluated on crossing) so the panel's own `max-sm:` bottom-sheet classes
   take over; from `sm` up it floats next to the trigger exactly as before.
   Migrated surfaces (each browser-verified 8/8 — full-width bottom sheet +
   dimming backdrop on a phone, unchanged floating panel on desktop):
   - **action-group menu** — reference (`actions/group.blade.php` +
     `ActionGroup::getDropdownConfig()`); `verify-phase3-sheet.mjs`.
   - **table filter panel** (`index.blade.php`); `verify-filter-sheet.mjs`.
   - **table column-toggle panel** (`index.blade.php`);
     `verify-columntoggle-sheet.mjs`.
   - **generic `<x-wire::dropdown>`** (`foundation/dropdown.blade.php` +
     `Foundation\View\Dropdown`): a `sheetOnMobile` prop (default `true`,
     opt-out via `:sheet-on-mobile="false"`) drives it for every consumer;
     `verify-dropdown-sheet.mjs`.
   - **searchable-select combobox** (`partials/searchable-select.blade.php` —
     the shared listbox behind forms `Select` **and** table `SelectFilter`);
     `$sheetOnMobile` blade var (default true). Calls `$float` directly, so the
     `minWidth` clear was added to the sheet-mode reset in `dropdown.js` to drop
     a lingering `matchWidth` width on resize. `verify-select-sheet.mjs`.
   - **date-time-picker calendar** (`components/date-time-picker.blade.php`);
     `$sheetOnMobile` blade var (default true), calls `$float` directly. The full
     calendar + time picker now spread across a full-width sheet on a phone.
     `verify-datepicker-sheet.mjs`.
   - **tags suggestions list** (`components/tags.blade.php`); `$sheetOnMobile`
     blade var (default true), calls `$float` directly. `verify-tags-sheet.mjs`.

   **Pattern for any future floating surface:** (a) pass `sheetOnMobile: true`
   into the panel's `wireDropdown(...)` / `$float(...)` config; (b) add a
   mobile-only backdrop sibling (`fixed inset-0 sm:hidden`, tap-to-close);
   (c) keep the panel's existing desktop classes as the base and append the
   `max-sm:` sheet set (`max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0
   max-sm:top-auto max-sm:w-auto max-sm:max-h-[85vh] max-sm:overflow-y-auto
   max-sm:rounded-t-2xl max-sm:rounded-b-none`) plus the breakpoint-scoped
   slide-up transition. Browser-verify each.

   **Portability note:** the sheet uses the `max-sm:` (max-width) variant so the
   *base* classes remain the unchanged desktop panel (byte-identical desktop, and
   the dynamic `w-*` width needs no `sm:` prefix — which would hit the
   interpolation trap below). `max-sm:` is Tailwind 3.2+; the important-prefix
   route was rejected because its syntax differs between Tailwind 3 (`!w-full`)
   and 4 (`w-full!`) and ADR 0005 keeps both supported.

7. **No canonical owner for touch-size / sheet-on-mobile.** **DONE (2026-07-03).**
   Touch size is owned by `HasSize::getButtonSizeClasses(iconOnly)` (item #3);
   the mobile sheet is owned by the `$float`/`wireDropdown` `sheetOnMobile`
   capability (item #6). Both are single canonical owners consumed by surfaces
   rather than re-encoded per component.

## Codebase gotcha discovered during the modal fix

**Tailwind cannot scan interpolated responsive classes.** `SlideOverComponent`
built classes as `"... sm:{$side}"`; Tailwind scans PHP as plain text, so
`sm:right-0` was never generated and the desktop slide-over silently broke — a
class-string test passed (the string was present) but the CSS did not exist.
Only the browser preview caught it. **Rule:** every dynamic utility must be a
**full literal** in a `match`/ternary arm. A repo-wide sweep found no other
interpolated-breakpoint offenders (the rest already use literal `match` maps,
e.g. `Table::getStackedTableHiddenClass()`).

## Suggested phased plan

- **Phase 1 (P1) — ✅ DONE (2026-07-03):** `$float` viewport-aware `maxHeight`
  shipped + browser-verified; card-mode row actions turned out to already exist.
- **Phase 2 (P2) — ✅ DONE (2026-07-03):** canonical icon-button tap-target size
  + close-button enlargement + checkbox-list / inline-radio-card reflow, all
  browser-verified. Editor-toolbar-on-mobile (#5) intentionally deferred.
- **Phase 3 (P3) — ✅ DONE (2026-07-03):** canonical
  `sheetOnMobile` added to `$float`/`wireDropdown`; **action-group menu**,
  **table filter panel**, **table column-toggle**, the **generic
  `<x-wire::dropdown>`**, the **searchable-select combobox** (forms `Select` +
  table `SelectFilter`), the **date-time-picker calendar** and the **tags
  suggestions list** all migrated and browser-verified (8/8 each). **Every
  floating surface in the repo is now a bottom sheet on mobile** — rollout
  complete. The documented pattern under finding #6 covers any future surface.

Verification for each: add/extend a `workbench/app/Livewire/Previews/*Preview.php`
variant and drive it with a CDP screenshot script (see
`.claude/skills/verify-preview` and `workbench/scripts/verify-modal-mobile.mjs`).
