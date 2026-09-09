# Making The Look Yours, Without Forking A View

An application using these packages should be able to look how it wants. Today
the honest answer to "make the sidebar dark and the cards square" is *publish the
Blade and edit it*, and `docs/start/theming.md` already says what that costs:

> **Publish only what you change.** Every overridden view is a file you now
> maintain across upgrades.

A published view is a fork. It renders the markup of the version it was copied
from while the package moves on, and the bug shows up three releases later in a
file the framework no longer knows about. The goal is therefore not to make
overriding easier — it is **to make it unnecessary**, on a surface whose
stability can be promised.

This document is the analysis and the design. Every number in it was measured
against this tree on 2026-09-09; the external positions are cited at the end.

---

## Part 1 — What The Field Settled On

Four independent answers, and they agree more than they disagree.

### Three token tiers

The consensus architecture is primitive → semantic → component. PrimeVue names
it exactly that way, and the pattern is now the default rather than the
exception — token adoption reached 84% of teams in 2026, and the W3C Design
Tokens Community Group shipped its first stable format module in October 2025.

- **Primitive** — raw values with no opinion: `blue-500`, `space-4`, `radius-md`.
- **Semantic** — intent: `primary.color`, `danger.color`. *Themeable attributes
  must live here.* Dark mode is this tier re-pointed, not a second set of rules.
- **Component** — per-element: `button.background`, `stepper.separator.background`.

### Stable element hooks, under two spellings

Both major answers exist and they differ in one interesting way.

**Filament** ships `fi-`-prefixed **classes** and its docs are blunt about why:

> Customizing internal Blade views is discouraged because it can lead to
> breaking changes during future updates. Developers are advised to use CSS hook
> classes for styling instead.

**shadcn/ui** ships a `data-slot` **attribute** on every primitive instead —
chosen so parent components can target children "without relying on fragile
class names", and because Tailwind 4's `data-[…]` and `group-data-[…]` variants
make it first-class.

### Cascade layers instead of `!important`

Styles in a later layer beat earlier layers regardless of specificity, and
crucially: **unlayered normal styles take precedence over layered ones.** A
library that layers its own CSS lets consumers override it with a plain class
selector and no `!important`.

### Scope by which stylesheet loads

Filament's `make:filament-theme` produces a *second* stylesheet registered with
`->viteTheme()` and loaded only on panel pages. The scoping is at the **document**
level, not the DOM level — the public frontend simply never loads that file.

---

## Part 2 — Where This Repository Actually Stands

| Layer | State | Measured |
| --- | --- | --- |
| Primitive tokens | Complete, borrowed | Tailwind 4's own `--color-*`, `--spacing`, `--radius-*` |
| Semantic tokens | One of five | `primary` re-points; `success`/`danger`/`warning`/`info` hard-wired to `emerald`/`red`/`amber`/`cyan` in `HasColor` |
| Component tokens | None | — |
| Hook selectors | Two half-built spellings | ~30 `wire-*` classes (mostly Alpine selectors, but `wire-field` and `wire-resource-page` are real hooks); 387 `data-testid` names across 462 sites in 140 of 309 views |
| Render hooks | **Absent** | `Hook` has 18 names, all lifecycle. `PartialRenderHook` is a Livewire optimization, unrelated |
| Per-instance attributes | Nearly absent | `HasExtraAttributes` on 5 classes, honoured by 8 views |
| Document-level scope | **Already there** | The `$head` slot, `layout.blade.php:29` and `auth-layout.blade.php:17` |

Four structural facts decide the design.

**1. The framework ships no CSS.** No `.css` file in any package, no `:root`, no
`@apply`, one custom property of its own (`--wire-media-rail`). The consumer
compiles everything, scanning `vendor/nyoncode`.

**2. Tailwind 4 already made every utility a variable read.** Verified by
compiling the corpus with the real compiler:

```css
.px-3       { padding-inline: calc(var(--spacing) * 3); }
.rounded-lg { border-radius:  var(--radius-lg); }
.text-sm    { font-size:      var(--text-sm); }
```

So the 1 684 spacing literals and 449 radii written across 309 Blade files are
not an obstacle to token theming. They are already reading tokens.

**3. Views are includes, not components.** 292 `@include` against 33 `<x-…>`.
There is no `$attributes` bag to merge a class into centrally — a hook is
literal text at the element. But a third of the markup funnels through shared
partials (6 708 of 20 526 lines), and `partials/field-wrapper-start.blade.php`
alone reaches 26 field types.

**4. Consumer CSS already wins, and needs no `!important`.** Compiled and
checked: the framework's classes land in `@layer utilities`, and a rule written
plainly in the consumer's stylesheet is emitted **unlayered** —

```css
/* admin.css */
.wire-card { @apply rounded-none border border-gray-200; }
```

```css
/* compiled: outside every @layer, therefore beating .rounded-lg regardless of specificity */
.wire-card { border-radius: 0; border-width: 1px; border-color: var(--color-gray-200); }
```

This is a real advantage over the framework that popularised hook classes:
Filament's docs reach for `!important` because its stylesheet is precompiled and
unlayered. Here the cascade does the work for free.

---

## Part 3 — The Design

Five decisions. Each is a consequence of Part 2, not a preference.

### 1. Scope at the document, never at the DOM

The `$head` slot already lets an application decide what loads on admin pages:

```blade
{{-- resources/views/components/layouts/admin.blade.php --}}
<x-wire-admin::layout :title="$title ?? config('app.name')">
    <x-slot:head>
        @vite(['resources/css/admin.css', 'resources/js/app.js'])
    </x-slot:head>
    …
```

```css
/* resources/css/admin.css — loaded on admin pages and nowhere else */
@import "tailwindcss";
@custom-variant dark (&:where(.dark, .dark *));
@source "../../vendor/nyoncode";

@theme {
    --radius-sm: 0; --radius-md: 0; --radius-lg: 0;
    --radius-xl: 0; --radius-2xl: 0; --radius-3xl: 0;
    --spacing: 0.2rem;
    --color-primary-500: var(--color-violet-500);
}
```

The frontend loads `app.css` and sees none of it. **This works today, with no
framework change.**

It also disposes of a whole branch of design that looked necessary and was not.
A DOM-level scope (`.wire-root`) would need a wrapper element, and then a portal
root, because 18 surfaces `x-teleport="body"` out of any wrapper — and a portal
root carrying `transform`, `filter` or `contain` silently becomes the containing
block for `position: fixed`, breaking every modal. **Document-level scoping has
none of those problems**: a teleported modal is still in the same document, under
the same `:root`.

`InstallScaffold::stylesheetSources()` and `stylesheetPrimary()` already take a
`$relativePath`, so seeding a second entry needs no new installer code.

### 2. Tier 2 is the theming surface; tier 3 is out of reach, and hooks take its job

Adopt the industry model, but stop at two tiers:

- **Primitive** — Tailwind's own. Do not reinvent a palette.
- **Semantic** — `--color-primary-*` plus the four roles currently hard-wired in
  `HasColor`. This is where theming happens, and it is the one piece of token
  work worth doing.
- **Component** — **deliberately not built.** A component token only pays off if
  elements read it, which here means rewriting 1 684 literals into resolver
  calls. PrimeVue can afford tier 3 because it ships CSS; this framework ships
  Blade.

That gap is real, and **hook selectors fill it** — `.wire-table-row` is this
repository's component tier, expressed as a selector instead of a variable. It
is strictly more expressive (it can say "square cards, round avatars", which no
token can) and it costs no render-time work.

### 3. One hook vocabulary: `wire-*` classes, never styled by the framework

Pick one spelling and make it canonical. The recommendation is a **class**, for
reasons local to this repository rather than fashion:

- `wire-field`, `wire-resource-page`, `wire-scroller` already exist — ~30 names.
  A third spelling would be a second wheel.
- Views already use `@class([…])`; adding a class is idiomatic here, while
  adding an attribute means a second attribute beside `data-testid`.
- `@apply` targets a class directly, which is the workflow in §1.

**`data-testid` is not the contract and must not become one.** A test attribute
exists so a test can find an element; the day styling depends on it, it stops
being free to change for testing reasons. What the existing layer is worth is
narrower and still large: **the naming is already done** — 387 names, reviewed
over time, consistent enough to read as a vocabulary. That is the part requiring
judgement; rendering a class beside it is mechanical.

One directive, so a name has one source:

```blade
<aside @wireEl('admin-sidebar') class="…">
{{-- renders: class="wire-admin-sidebar" data-testid="admin-sidebar" --}}
```

Two hard rules:

- **The framework never writes a declaration for a hook class.** The moment
  `.wire-table-row` has styles of its own, specificity fights begin and
  `!important` follows. Hooks are bare targets.
- **A hook name is public API.** It may be added; it may not be renamed or
  removed in a minor release. Names describe the thing, never its appearance —
  `wire-table-toolbar`, never `wire-table-grey-bar`.

*(The alternative — shadcn's `data-slot` — is defensible and would win on a
green field: an attribute cannot be confused with a styling class, and
`group-data-[…]` variants compile here, verified. It loses on the two points
above: this repo already has `wire-*` classes and already has one attribute.)*

### 4. Structure gets render hooks, and only where someone asks

The genuine gap. `Hook`'s 18 names intercept data — `table.composing`,
`form.saving`, `action.executed`. Not one can put an element on a page, so
"a badge after every page title" has exactly one answer today: publish the view.
That is the pressure [ADR 0029](../decisions/0029-modules-as-installable-packages.md) §3
predicted, arriving somewhere nobody routed it.

```php
WireView::renderHook('panels.page.header.end', fn () => view('badges.beta'));
```

[ADR 0030](../decisions/0030-hook-surface.md) §6 already governs the growth rule
— *coverage grows by named consumer, never by symmetry* — and it applies
unchanged. Start where the leverage is: the shared partials give the whole field
layer, the page header and the table toolbar for a handful of names. Do not
enumerate a hundred positions because Filament has a hundred.

### 5. Presets ship as source, never as a stylesheet

A named look (`sharp`, `soft`) is cheap once §1–§3 exist: a `@theme` block plus a
handful of hook rules. Ship them as CSS **source files inside the package**, which
the consumer imports into their own entry:

```css
@import "../../vendor/nyoncode/wire-admin/resources/css/presets/sharp.css";
```

Their Tailwind compiles it, `@apply` works, and the "framework ships no compiled
CSS" property survives intact — the file is inert unless someone imports it.

**Do not ship a preset before §3.** A sharp theme without hook selectors squares
the avatars, and that reads as broken rather than sharp.

---

## Part 4 — The Four Gaps That Must Close First

Measured by compiling the whole corpus with radius tokens zeroed: **14 of 17
radius rules follow the token** and go square, including responsive
(`sm:rounded-2xl`) and stateful (`focus:rounded-lg`) variants. Three do not:

| Gap | Sites | Why | Fix |
| --- | --- | --- | --- |
| bare `rounded` | **101** | Tailwind 4 compiles it to a literal `0.25rem`; every other step reads `var(--radius-*)` | → `rounded-sm`, same pixels, themeable |
| `text-[9\|10\|11\|15px]` | 47 | An arbitrary value is a literal by definition | a `--text-2xs` step, or fold into `text-xs` |
| `rounded-[10px]` | 2 | Same | nearest scale step |
| hand-written `<style>` blocks | 9 files | Bypass Tailwind entirely | rewrite as `var(--radius-*)`; `export/pdf.blade.php` stays exempt — it prints |

`rounded-full` (99 sites) is deliberately *not* on this list. It compiles to
`calc(infinity * 1px)` and follows no token, which is correct: avatars, toggle
knobs and status dots should stay round in a sharp theme. Squaring them is what
hook selectors are for, if anyone ever wants it.

---

## Part 5 — Order Of Work

1. **Gaps (Part 4)** — the 101 bare `rounded` first. Independent of everything
   else, improves the corpus regardless, and without it any radius setting
   visibly misses a third of the corners.
2. **Semantic colours** — unbed `success`/`danger`/`warning`/`info` from
   `HasColor`. Works identically on Tailwind 3 and 4. Two rules while doing it:
   the literal hues stay first-class (`Color::Green` is green, not an alias of a
   re-pointable `success`), and class strings stay literal in the `match` arms —
   they are the scanner's allow-list as much as they are the styling.
3. **`extraAttributes` everywhere** — declared by every component, honoured by 8
   views. The trait's own docblock records this biting once already. Small, and
   it relieves pressure while the rest is built.
4. **Hook classes (§3)** — the `@wireEl` directive, then per surface, table and
   forms first. Every edit is *additive*: adding a class cannot change how
   anything renders. Coverage is a number worth tracking.
5. **Document the §1 recipe** — `docs/start/theming.md` gains it as a new first
   level above colours, with its Czech mirror. It is the highest-value paragraph
   in this document and it describes something that already works.
6. **Render hooks (§4)** — one name per real consumer.
7. **Presets (§5)** — last, and only after 4.

Steps 1, 2, 3 and 5 need no design work and no new abstractions.

---

## Part 6 — What This Does Not Solve

- **Tailwind 3.** Token theming needs variable-backed utilities, which Tailwind 3
  does not emit — there `.px-3` is `padding-left:.75rem`. Colours (step 2) and
  hook classes (step 4) work on both; radius and density are Tailwind 4 only. The
  failure is benign: the `@theme` block sets properties nothing reads, so a
  Tailwind 3 application looks exactly as it does today. `docs/start/upgrade.md:58`
  promises "3.x or 4.x" without qualification and should gain one.
- **Dark mode as a customization axis.** Every hook selector a consumer writes
  has to handle both themes, and nothing here helps them. The token tier does
  this properly — semantic tokens re-pointed under the dark variant — which is an
  argument for step 2 going further than colours eventually.
- **Whether hook names are package-prefixed.** The 387 existing names already
  scope themselves by convention (`admin-`, `table-`, `form-`), which argues for
  keeping that and prefixing only the rendered class.

---

## Sources

- [Filament — Styling overview / creating a custom theme](https://filamentphp.com/docs/5.x/styling/overview)
- [Filament — CSS hook classes](https://filamentphp.com/docs/5.x/styling/css-hooks)
- [PrimeVue — Styled mode and the three token tiers](https://primevue.dev/theming/styled/)
- [shadcn/ui — Tailwind v4 and `data-slot`](https://ui.shadcn.com/docs/tailwind-v4)
- [MDN — Cascade layers](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/Styling_basics/Cascade_layers)
- [CSS-Tricks — Cascade Layers guide](https://css-tricks.com/css-cascade-layers/)
- [Design token architecture, 2026](https://timgraf.com/ui/design-token-architecture-2026-the-strategic-blueprint-for-scalable-design-systems/)
- [Token tier system architecture](https://designsystemproblems.com/token-management/token-tier-system/)
