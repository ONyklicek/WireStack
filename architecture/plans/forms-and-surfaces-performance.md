# wire-forms, wire-sortable and core's own surfaces: what the render costs, and what to do about it

**Status:** measured 2026-08-17; **all ten steps implemented** (1–6 on 2026-08-17,
7–10 on 2026-08-18). Every number in §0–§2 was produced by a
throwaway probe in this working tree; every probe was deleted. No tracked file
was modified while measuring. Numbers added by the implemented steps are marked
as such in §3 and were measured after the change landed.

---

## 0. The verdict, first

A wire-forms render is **not** dominated by the framework floor, and it is not
dominated by any algorithm either. It is dominated by the *number of fields*,
linearly, with a per-field constant that ranges from about 1.0 KB to about 28 KB
of HTML depending on which field type it is. An empty Livewire component measures
1.32–1.47 ms and 659 bytes; a 25-field mixed form measures 14.1–18.7 ms, so the
fields are roughly 93% of it. There is a real prize here.

But the prize is smaller than the raw byte counts suggest, and the mechanism that
would claim it is blocked. Three findings set the shape of everything below:

1. **Every Livewire round trip on a form re-renders the whole schema and ships
   all of it.** `vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php:226`
   is `if ($html = $this->render($component)) { $context->addEffect('html', $html); }`
   — unconditional. `live()` changes how *often* you pay that, never what one
   costs: an identical 25-field mixed form with every field non-live still ships
   95,671 B against the live version's 95,946 B, a 0.3% difference. Writing the
   motivation as "live fields are expensive" would point the work at the wrong
   thing.
2. **Raw HTML bytes are the wrong unit.** A form is N near-identical field blocks;
   that is maximally compressible. A 25-field mixed form measures 100,701 B raw
   and 6,403 B at `gzip -6` — a ratio of 15.7. Six Selects that are ~78 KB of raw
   HTML are ~4 KB on the wire. A raw-byte gate would overstate the network payoff
   by roughly 19×. Server render time and browser morph cost are real; the
   transferred bytes mostly are not.
3. **The partial mechanism this repo already owns refuses, by design, on exactly
   the path forms use.** `PartialRenderHook::update()`
   (`packages/core/src/Foundation/Support/PartialRenderHook.php:56-61`) sets the
   force-render flag on *any* property update, and `wire:model` is a property
   update. Per-field islands do not compile at all. Details in §2.

So: there is something worth taking, but the first four items on the list are
small correctness-and-hygiene fixes, the fifth is the one large byte win that
needs no new machinery, and the partial seam is last, opt-in, and carries a
design decision rather than a refactor.

### The measurement caveat that resizes every millisecond in this document

`php -i` on this box reports `opcache.enable => On`, **`opcache.enable_cli =>
Off`**. Pest runs under CLI. Every Blade view is therefore re-lexed and
re-compiled on every render inside the test harness. Same probe, same machine,
only `-d opcache.enable_cli=1` flipped:

| Shape | opcache off (CLI default) | opcache on |
|---|---|---|
| One TextInput, per field | 0.4749 ms | 0.0840–0.0854 ms |
| 25-field `->set()` round trip | 15.40 ms | 3.10 ms |
| Engine-only `HandleComponents::update()`, 25 fields | 13.90 ms | 2.35 ms |
| 50-field `->set()` round trip | 25.57 ms | 4.96 ms |

That is a 5–6× inflation. **Ratios and percentages survive the flip** (wrapper
share 51.7% off vs 49.6–50.3% on), so every proportion quoted below is sound.
Absolute milliseconds quoted below are opcache-off numbers unless the sentence
says otherwise, and should be divided by roughly five to reason about production.
Any benchmark this plan asks for must state which it ran under.

Second caveat, on method: unwarmed single-shot timing produces garbage. One
sweep's first data point measured 28.40 ms for a single TextInput because it was
the process's first Livewire render; that one point dragged a least-squares fit
to a 12.01 ms intercept and made the same data yield intercepts of 12.01 / 8.19 /
2.56 ms depending only on the window chosen. Warmed and min-of-N, the same sweep
gives a 1.81 ms intercept. Do not fit an intercept — measure the empty component
directly, as the table's benchmark does.

---

## 1. What was measured

### 1.1 The flat form is exactly linear, and the unit is the field

View renders are deterministic integers with zero variance, and the law is exact:

```
views = 3 + Σ (per-type cost)
```

verified point-by-point up to N=200 with a wildcard `View::composer('*')` counter,
the same mechanism as `packages/table/tests/Unit/Concerns/TableRenderCountTest.php`.
A 0-field form measures 3 views / 1,115 B directly. Per-type cost, measured:

| Field type | views/field | bytes/field (raw HTML) |
|---|---|---|
| Textarea | 3 | 1,054–1,086 |
| Checkbox | 3 | 1,306–1,336 |
| TextInput | 3 | 1,633–1,666 |
| Toggle | 3 | ~2,038–2,069 |
| Radio (5 options) | 3 | ~5,449 |
| KeyValue / ColorPicker / Rating / Slider | 3 | Rating ~10,594 |
| FileUpload, Tags | 4 | Tags ~10,678 |
| DateTimePicker | 5 | **28,444–28,453** |
| CheckboxList | 6 + 1 per option | — |
| Select, default | 7 | 13,082–13,119 |
| Select, `->searchable()` | 6 | 12,512 |
| Select, `->native()` | 4 | 1,630 |
| TiptapEditor / RichEditor / TimePicker | — | 18,213 / 15,772 / 12,967 |

Two things follow. **Views are a bad proxy for bytes**: among fields that all
cost exactly 3 views, bytes range from 1,054 (Textarea) to 10,594 (Rating), a
factor of ten. And **"Select is a 7.5× outlier" is false** — Select is fourth of
twenty-two. DateTimePicker is 2.18× Select's bytes at marginally *more* time. Ten
DateTimePickers measure 285,494 B against ten Selects at 130,912 B and ten
TextInputs at 17,358 B.

The three views an ordinary field costs are the component view plus
`wire-forms::partials.field-wrapper-start` plus `field-wrapper-end`
(`packages/forms/resources/views/components/text-input.blade.php:15` and `:151`).
Those two wrapper includes are 49.6–50.3% of a *bare* TextInput's render time and
17.9–19.7% is the `@include` machinery itself rather than the wrapper's markup —
a control confirmed this: an `@include` of a literally empty partial costs
0.0050–0.0076 ms and is linear in include count, and 2× that equals the whole
inline-vs-include delta. But that 50% is the cheapest field in the package used
as the denominator. Across the real palette the wrapper share is 30.4–33.0% for a
configured TextInput, 24.0% for a Toggle, and **8.4–8.6% for a Select**. A
realistic mixed form puts it near 34%. In absolute terms with opcache on the
whole `@include` overhead is ~0.008 ms/field: inlining both wrappers into 23 field
views buys a fraction of a millisecond on a 20-field form. See §4 for why that is
also already a closed decision.

### 1.2 The round trip, and the honest byte figures

Measured on a 12-TextInput host through a real `Livewire::test(...)->call()` POST:

| | 1 field | 12 fields |
|---|---|---|
| Isolated `(string) $form` | 994–1,000 B | 11,453 B |
| Same, inside the request (`effects.html`) | 1,813 B | 19,845 B |
| Full response body | 2,533 B / 2.231 ms | 21,351 B / 7.751 ms |

The isolated render **understates real wire bytes by ~700 B per field**, because
Livewire's morph markers are compiled behind
`ExtendBlade::isRenderingLivewireComponent()` and are absent outside a Livewire
render. Any forms benchmark taken by stringifying a form object is measuring the
wrong thing.

So the ceiling on a per-field partial at N=12 is **88.1% of response bytes and
71.2% of a round trip**, not the 96%/92% an earlier pass reported by comparing an
isolated one-field render against a whole 12-field response. And that 71.2% floor
is optimistic: `RequestBroker` calls `withoutMiddleware()`, so session, auth and
app middleware are excluded from the fixed cost.

For larger forms, measured on a mixed 25-field shape: 100,701 B raw / 6,403 B
gzipped; on pure TextInputs at 25 fields, 43,922 B raw / 1,782 B gzipped. Per
field, 1,665 B (pure text, flat within ±3 B) or 3,838 B (mixed, flat within ±12
B). A 30-field form doing live validation ships 52,852 bytes of HTML to deliver
an errors memo of 41 bytes.

### 1.3 Repeaters multiply fields, not rows

A repeater of R rows × F fields renders `3·R·F + c` views for TextInput children,
with `c` constant in R and harness-shaped (3 in one probe, 7 in another — never
1). `getItemSchema()` is called exactly R times per render, and R+2 per state-update
round trip. The "3" is a property of TextInput, not of the repeater: Select
children give `6·R·F + 3`, a Grid inside the row adds +1/row, a nested repeater
adds a genuine R_outer×R_inner term.

The decisive measurement is that **the repeater is not the cost — the fields
are.** A flat form with the same 80 fields and no repeater costs 55.23 ms /
361,725 B against the repeater's 58.10 ms / 417,726 B: 95% of the milliseconds
and 87% of the bytes. Row chrome adds 0.12 ms and 2.7 KB over four bare fields.
Per-field wall time inside a repeater versus a flat form of equal field count is
1.13× / 1.12× / 1.13× / 0.96× at n=12/60/150/300. Timing the `getItemSchema`
clone loop alone against the whole round trip gives **0.1%** (0.031 ms of 32.0 ms
at 20×3). A `getItemSchema` memo would buy nothing.

Nor is the reactive dispatch path a cost: resolving a field inside a repeater adds
exactly **+2** schema clones per round trip
(`packages/forms/src/Forms/Runtime/FormRuntime.php:621`), not O(rows). Flat
components are memoised at `FormRuntime.php:544-546`, the form object is memoised
at `packages/forms/src/Forms/WithForms.php:97-98`, and `FormRuntime::prepare()`
is idempotent (`FormRuntime.php:458-460`). There is no per-field recomputation of
per-form work: everything `field-wrapper-start` computes is genuinely per-field,
and the form-constant slice of it measures under 2% of a field.

### 1.4 One root cause explains most of the byte spread

The seven expensive field types have one thing in common that TextInput does not:
each inlines a per-instance Alpine `x-data` blob. TextInput has zero `x-data`
occurrences and costs 1,637 B. On a 20-Select page, the inlined `x-data` blob in
`searchable-select.blade.php:66-202` measures **121,440 B = 45.7% of the whole
page**, and the teleported panel another 4,196 B per field.

Two things that *look* like the cause are not. `wire-core::partials.floating-assets`
contributes **0 marginal bytes** — it is wrapped in `@assets`, which Livewire
dedupes per request via `SupportScriptsAndAssets::$alreadyRunAssetKeys` and
injects into the page layout, not the component;
including it three times emits 0 B. `wire-forms::partials.select-option-modals`
likewise renders 0 bytes unless a modal is mounted. Both cost a view render, and
`sheet-grabber` costs 277 B/field (2.08% of a 20-Select page). Neither is where
the bytes are.

### 1.5 wire-core's own surfaces

**Infolists and panels.** A bare labelled `TextEntry` is 2 view renders (the entry
plus `wire-core::partials.entry-label`) and about 0.24–0.25 ms. That is the floor,
not the constant: the shape this repo's own docs advertise
(`TextEntry::make('email')->icon(…)->copyable()` inside Sections) measures 3.2
views and 0.471 ms per entry, and adding one entry action gives 5.0 views and
0.761 ms — 40 such entries are 30.5 ms, 3.2× the bare figure.

`RepeatableEntry` is the only data-scaling per-row view render in core: 6 views
per row for three plain `TextEntry` children, linear, with no pagination or limit
in the class. Half of that six is the shared `entry-label` include —
`->label('')` drops the slope from 6.00 to 3.00. But core already owns the fix for
the other half: `Foundation/Concerns/HasViewRenderCache.php` is a request-scoped
memo written for exactly these per-row clones, and `IconEntry`/`TextEntry`
declare signatures for it — a mixed text+badge+icon schema measures a slope of
**2.00/row from the one plain child**, badge and icon at zero once warm. The real
exposure is "plain/copyable/list `TextEntry` children", not "RepeatableEntry".

And the repeatable itself is barely the cost: a *flat* Infolist of the same N×4
`TextEntry` measures 10.74 / 21.37 / 54.88 / 222.83 ms at N=10/20/50/200, making
the repeatable's own overhead 2.5–4.3%. About 96% is the per-`TextEntry` view
render at ~285 µs each. Optimising the repeatable targets 3% of the problem.

For scale: a 20-row repeatable of 4 entries measures 22.1 ms. The table's own
`IslandDecompositionBenchmarkTest` reports a 20-row, 25-column read-only table
page at 22.0 ms on this machine. Per rendered item the repeatable is ~13× worse
(278 µs/entry vs 21.5 µs/cell); it reaches the table's cost rendering four things
instead of twenty-five.

**Editable panels do not re-render on a write.** `WithEditablePanel.php:56-63`
calls `skipRender()`, and `HandleComponents.php:311` short-circuits `render()`
entirely — measured, `render()` runs once on mount and zero times per write, and
the response effect keys are `["returns"]` only. It does rebuild the whole schema
per write (`$this->panel()` at line 65, 31 entry objects per write in the probe),
but that measures 0.014 ms of a 0.647 ms write — 2.1%. Caveat worth knowing:
`skipRender()` freezes the *whole host component*, so composing the trait onto a
host that renders anything else leaves that stale, and `HandlesRenderless.php:18`
makes it a no-op if `forceRender` was set.

**Widgets have no per-item view render.** `stats-overview.blade.php:10-64` is one
inline `@foreach` with no include: 1 / 10 / 50 stats all measure **1** view
render. `BarChartWidget` at 1 / 10 / 50 items measures 2 / 2 / 2. The `icon()`
calls are memoised SVG strings from `IconManager`, not view renders. There *is* a
per-widget render — `widget-grid.blade.php:14` `{{ $widget }}` — measuring n+2
(1 widget = 3, 20 widgets = 22), which at handful counts is not a problem.

**wire-sortable has no render path of its own.** Both branches of
`packages/sortable/resources/views/tables/index.blade.php:49-53` and `:56-60`
`@include('wire-table::tables.index', …)` unchanged; the only own markup is the
wrapper div at `:38-48`, and the drag handle `<td>` is created client-side
(`sortable.js:298-306`), costing zero server bytes per row. Its unbounded costs
are elsewhere and are not render shapes: entering reorder mode drops pagination
(`WithSortable.php:132-153`) — measured, a 3-column table over 200 rows renders
10 rows / 50,327 B paginated and 200 rows / 230,158 B in reorder mode, 946.5 B per
extra row — and each drop sends every row's order (200 items = 4,985 B of JSON)
and issues **one UPDATE per row** inside a single transaction
(`WithSortable.php:200-212`, measured 201 queries for 200 items). Unlike the
render, the write is paid on every drop.

### 1.6 What forms has to catch a regression with today

`packages/forms/tests/Unit/Components/DateTimePickerIconTest.php:17-31` registers
a `View::composer('wire-core::foundation.icon', …)` and asserts zero. That is a
real render-count fuse living in forms — narrow (one field, one view name, no
slope), but the pattern has precedent here. There is no payload fuse and no
benchmark: root `phpunit.xml` declares only `Table Benchmarks`, and
`packages/forms/phpunit.xml` declares only Unit / Feature / Standalone. Note that
the table registers its benchmarks **twice** — root `phpunit.xml` (what CI runs)
and `packages/table/phpunit.xml` (what `composer test:table` runs). Registering a
forms suite in only one leaves the other blind.

The table's fuses do not cover forms: `TableRenderCountTest`'s only
non-skeletonable control is `TextInputColumn`, which renders
`tables.columns.text-input-editable`, not any wire-forms view.

---

## 2. What transfers from the table's work, and what does not

### 2.1 The coverage rule is the whole story

`PartialRenderHook`'s docblock states the rule plainly: a partial response is
correct only when everything the request changed is inside a queued region, so
the view is skipped only if every call queued at least one partial **and no
property update happened at all**. `update()` at `:56-61` sets the force flag
unconditionally; `dehydrate()` returns early at `:93` when `covered()` is false —
so a forced request emits **no** `wirePartials` effect at all (the repo's own
`PartialRenderTest.php:112` asserts `toBeNull()`; an earlier report of an "empty
`wirePartials` effect" was a `?? []` default in the probe, not a measurement).

`wire:model` is a property update. Every field interaction in a form is therefore
forced to a full render as the mechanism stands.

That is policy, not a sealed engine — and this is the correction that matters for
scoping. Counter-measurement, userland only, zero changes to any tracked file: with
a partial queued from `updated()`, clearing the force flag *and* calling
`$this->skipRender()` produced renders 1 → 1, effects `['returns','wirePartials']`
with the field HTML and **no `html` effect**. `skipRender()` is Livewire's own
public `HandlesRenderless::skipRender()`, and the update trigger's finish closures
fire inside `updateProperties()` at `HandleComponents.php:408-410`, before
`render()` at `:226` reads the flag. So the two "independent mechanisms" are about
twenty lines apart in one 125-line file.

Three blockers sit in front of that, not one, and the third is the one that bites:

1. the force flag at `:58`;
2. `skipRender` is written only from the `call()` hook (`:80-84`), so clearing the
   force flag alone makes things *worse* — measured renders 2 → 3, shipping both
   `wirePartials` and `html`;
3. **the shape a browser actually sends is not the shape the probe sends.**
   `Testable::setProperty` sends `updates` with `calls: []`. A real
   `wire:model.live` sends `updates` **plus** a synthetic `$commit` call
   (`dist/livewire.esm.js:11079-11091`, `:15433-15438`). That `$commit` queues no
   partial, so `PartialRenderHook::call()` at `:72-78` re-arms the force flag.
   Measured on the real shape: force cleared in `updated()` still gives renders
   2 → 3 with `html` present; adding `skipRender()` gives effect keys
   `['returns']` only — a silently dead response.

So: not decisive, not an engine rewrite, but a bounded change inside
`PartialRenderHook` that needs an opt-in coverage signal on the update path, a
`skipRender` writer there, and a rule that does not count `model.live`'s `$commit`
as an uncovered call. It also needs an answer to the correctness question, which
is real in forms: `visibleWhen`/`hiddenWhen`/`disabledWhen` are shipped
(`InteractsWithStateConditions.php:34,43,52`), and it is measured — setting
`data.kind = 'b'` made one sibling field's markup appear and another's disappear,
14,288 → 14,917 bytes. A per-field partial cannot cover that. (Sibling *value*
changes are safe: values ride the Livewire data payload, and a probe that mutated
three siblings' values through `afterStateUpdated` produced byte-identical markup.
Only visibility rewrites the DOM.) Detecting the carve-out does not need a
dependency graph — a visible-shape signature over `isVisible()`, which
`form.blade.php:3` already calls before rendering each component, is enough.

### 2.2 Islands do not fit forms

`packages/forms/resources/views/form.blade.php:2-5` is
`@foreach($components as $component) @if($component->isVisible()) {{ $component }}`.
An `@island($component->getStatePath())` there is exactly the shape pinned as
non-compiling in `packages/core/tests/Feature/IslandSemanticsTest.php:42-60`:
`IslandCompiler::generateScopeProviderCode()` re-emits the directive's own
arguments inside the extracted island file, and `HandlesIslands::renderIslandView()`
gives that file only `__livewire` plus public properties — never the enclosing
loop's variables. Measured directly on the widget grid, which has the same shape:
`@island(name: $widget->getHeading(), lazy: true)` inside a `@foreach` throws
`Undefined variable $widget` on the **first** render; the static-name control
compiles and emits `wire:intersect.once` correctly. One compiled island body per
`@island` occurrence — that is the rule, and it kills per-field and per-widget
islands alike.

A *statically named* island around a form region does fire on `wire:model.live`
updates — `dist/livewire.esm.js:14718-14725` merges island metadata from
`closestIsland(origin.el)` for any action with an origin, and the model path sets
one at `:15434-15438`; measured, a `$commit` carrying island metadata returned a
258-byte island fragment and no `html` effect. But that is a trap, not an
opportunity. Livewire does **zero** coverage analysis
(`SupportIslands.php:40` is the entire gate), so anything outside the island goes
silently stale — measured, a `<span>` outside the island never received its new
value. `SupportIslands::call()` calls `skipRender()` unconditionally
(`:52-75`). And the saving is component-minus-island, so an island around "a form
region" — where the island *is* most of the component — collapses toward zero.
Two further failure modes: an island first appearing after mount is never in the
`islands` memo (`storeIsland` runs only while `islandIsMounting()`), and after a
normal full render the island emits `mode=skip` with an empty body, so the
browser morph skips the region and stale markup stays on screen.

### 2.3 The renderer exists, but rendering a field standalone is not free

`Form::findComponentByStatePath()` (`Form.php:418` → `FormRuntime.php:591`) plus
`Component::toHtml()` (`Component.php:61`) do produce a correct single-rooted
`TextInput` partial: measured 925 B, 3 view renders, root
`div[wire:key="field-data.name"]`, a byte-exact substring of the full form render.
That is where the good news stops.

- **Not single-rooted in general.** `select.blade.php:103-108` and
  `belongs-to-select.blade.php:87-92` include `select-option-modals` *after*
  `field-wrapper-end`. A Select with a mounted create-option modal measured **two
  roots**, 19,894 B.
- **Not addressable in general.** Eight of the field views never include
  `field-wrapper-start` — hidden, alert, placeholder, view-field, html, repeater,
  repeater-table, builder — so they have no `wire:key` and no anchor at all. A
  bare `->hidden()` field renders 65 B with no root key. `repeater.blade.php:12`
  opens its own root `<div>` with neither.
- **Three separate context dependencies.** `$errors` must be shared or
  `field-wrapper-start.blade.php:3` throws `Undefined variable $errors`.
  `$__livewire` must be shared or `Toggle::toHtml()` throws — nine views use
  `@entangle`, which compiles to `$__livewire->getId()`. And `$this` must be bound
  to the component: `FileUpload` and `Repeater` threw `Using $this when not in
  object context` **even inside `IslandViewScope::within()`**, because
  `ExtendedCompilerEngine::evaluatePath()` binds `$this` only when
  `ExtendBlade::isRenderingLivewireComponent()`. `IslandViewScope::within()`
  shares only `__livewire` and `_instance` (`:69-70`).

Livewire shares `errors` only from the `render` and `renderIsland` hooks
(`SupportValidation.php:33-35`, `:46-48`); `PartialRenderHook` renders in
`dehydrate()`. The consequence was measured with a `ViewErrorBag` shared the way
the `web` group shares one: **the same `addError()` shows in a full render and
does not show in the partial.** That is silent staleness of exactly the class the
coverage rule exists to prevent, and the current engine does not cover it. Fixing
it means `IslandViewScope::within()` also sharing the component's view error bag
and opening a `startLivewireRendering()` window — a change to shared core code,
not a forms-local one.

Also note `PartialRenderHook.php:100` runs the render callback *outside*
`IslandViewScope` and only wraps the returned `Htmlable` at `:105-108`. So
`renderPartial(fn () => $field->toHtml())` fails for entangle fields; only
returning the `Htmlable` works.

### 2.4 Repeater item partials are impossible; a repeater *region* partial is not

`InteractsWithRepeaters.php:65-67` is `unset($items[$index]);
writeRepeaterItems($statePath, array_values($items))` — remove reindexes, so
every downstream item's state path and `wire:key` shifts by one. Reorder is worse:
measured, `reorderRepeaterItems([2,1,0])` fully reverses state while the key set
is **byte-identical** before and after. Add does not shift.

But reindexing is not the blocker. `packages/core/resources/js/support/partials.js:63-95`
morphs only anchors it finds (`anchors.length !== 1 → continue`) and has **no
insert or delete path**, so any item-count change is inexpressible per-item under
any naming scheme — the same rule already stated at `WithTable.php:610-612`.
Meanwhile a *container-scoped* partial works: measured,
`wire:partial="repeater-data.contacts"` emitted 9,338 B covering exactly the two
surviving item paths with `effects.html` absent. Reindexing is invisible to a
whole-region replace.

The repeater's field-level `wire:key`s do exist, by the way — 23 of 31 field views
include `field-wrapper-start.blade.php:12`, so every leaf field inside every
repeater item is keyed today. Those keys are index-derived and carry zero identity,
so they pair exactly as positional morph would; adding a wrapper-level
`wire:key="repeater-{$statePath}-{$index}"` would be a no-op for the correctness
it looks like it buys. Giving items real identity would mean writing a token into
state, with save/validation/`StateContainer` blast radius.

### 2.5 `RowRenderer` and the fuses

`packages/table/src/Support/RowRenderer.php:27-38` gives loop extraction as one
reason and morph-marker elimination (459–999 B/row) as the second and, in its own
words, the one that pays — and it says removal was safe only once
`ctx-`/`sel-`/`exp-`/`act-{key}-{name}` `wire:key`s landed, gated by
`RowMorphKeysTest`. Forms do render their schema in the same `@foreach` + `@if`
shape. The marker cost in forms was measured but its *removability* was not: a
TextInput field carries 24 comment nodes (12 `[if BLOCK]` / `[if ENDBLOCK]`
pairs) and 17 whitespace text nodes, and comment bytes are 38.8–40.1% of the
payload (~686 B/field) against 6.1–6.3% for whitespace-in-runs. So closing
indentation — the table's cheap win — caps at ~6% for forms, and the 40% that is
markers comes off only by deleting conditionals, which
`TablePayloadFuseTest.php:366-371` explicitly warns against (the context-menu
`@if` was removed on that reasoning, broke column reorder, and both fuses stayed
green).

One correction for anyone porting the table's numbers: the budgets in
`TablePayloadFuseTest.php` are one-sided ceilings, and their comments are stale.
Running its own `pfPerRow()` today returns 978.75 B / 4 whitespace runs / 6
comments, not the 1826.75 / 11 / 16 the comments claim — commit `3ce49dd`
("Assemble the row in PHP, and stop paying for markers per row") invalidated them.
Do not import those integers into a forms fuse; measure forms and pin forms.

Finally, the table's render fuse *does* transfer, in its non-zero form.
`TableRenderCountTest.php:382` pins a slope (`expect($panelSlope / 8)->toEqual(1)`)
for markup that legitimately renders per row. That assertion shape maps directly
onto the two unbounded loops in forms.

---

## 3. Ordered steps

Ordering principle: correctness and tripwires first, then the one large byte win
that needs no new machinery, then the mechanism work. Each step names what it
gives up, because several of these trade something real.

### Step 1 — Give forms a render-count fuse with a *slope*, registered twice — **DONE**

**Landed as** `packages/forms/tests/Unit/Rendering/FormRenderCountTest.php` (in
`Rendering/`, not `Concerns/`: forms' `tests/Unit/Concerns/` holds tests for
`src/Concerns/*` traits, and a whole-form render fuse is not one). **No suite
wiring was needed** — the §1.6 worry does not apply, because
`packages/forms/tests/Unit` is already a suite in *both* root `phpunit.xml`
("Forms Unit") and `packages/forms/phpunit.xml` ("Unit"). Only a new *directory*
such as `tests/Benchmarks` would need registering twice; step 10 still does.

Slopes measured on this machine and pinned: **3.00 views per ordinary field**,
**6.00 per Select** (7.00 before step 2, and flat in option count — verified at 3
and 30 options), **0.00 per CheckboxList option** (1.00 before step 6), and
**3.00 × children per repeater item** with no per-item chrome (verified at 1 and
2 children). Every assertion is a slope between two sizes, never an absolute
count, because `@once` / `@assets` / the icon cache emit once per process.

**What it changes.** A new fuse in the shape of `TableRenderCountTest`: a wildcard
`View::composer('*')` counter, measured at two field counts, asserting the
marginal cost per field is exactly 3 for an ordinary field, exactly 1 per option
for `CheckboxList`, and the measured per-item slope for a `Repeater`.

**What it is worth.** Nothing directly. It is what stops every later step from
silently regressing, and what would have caught an `@include` dropped back into a
loop. The counter is about eight lines.

**The gate.** The test is the gate. It must be written against a freshly measured
baseline on the machine it runs on, not against the table's integers (§2.5).

**What it gives up.** A test that hard-codes integers will need updating whenever a
field view legitimately gains a partial. That is the point.

### Step 2 — Hoist the `select-option-modals` mount check into `select.blade.php` — **DONE**

**Landed** as a canonical predicate rather than a copied Blade condition: the two
call sites would otherwise have re-derived the same rule the partial states, which
is the drift the partial exists to prevent. `Select::hasMountedCreateOptionModal()`
/ `hasMountedEditOptionModal()` / `hasMountedOptionModal()` now own it,
`select-option-modals.blade.php` reads the first two to choose which modal to
draw, and both `select.blade.php` and `belongs-to-select.blade.php` gate the
`@include` on the third. Measured: the per-Select slope drops **7.00 → 6.00**
views, pinned by the step 1 fuse, which also asserts the gate still opens (a
mounted modal costs strictly more renders than a closed one).

**What it changed.** `select.blade.php` guarded only on `@if($livewire !== null)`;
the two `@if`s that actually gate output lived inside the 111-line partial. The
mounted-modal check moved up so the partial is not rendered when nothing is
mounted.

**What it is worth.** One view render per Select that emits zero bytes. Measured at
about 0.03 ms per field opcache-off, so roughly 0.006 ms with opcache on. A form
with six Selects saves six empty renders. This is the smallest item here and is
listed because it is genuinely free.

**The gate.** Step 1's fuse: Select drops from 7 views to 6 with no modal mounted,
7 with one mounted. Byte-identity assertion on the rendered form.

**What it gives up.** Nothing. It is a one-line move of an existing condition.

### Step 3 — Make `hiddenLabel()` do something on core entries, or delete it — **DONE (made to work)**

**Landed** as one canonical predicate, `HasLabel::hasVisibleLabel()`, holding both
rules — not hidden, and not empty — so no view restates them. All ten entry views
(seven infolist, three panel) gate on it, and `field-wrapper-start.blade.php` was
migrated onto it too, keeping only its own `$hideLabel` override (`hasVisibleLabel()
&& ! $hideLabel`). `->label('')` keeps working, which is what the pre-existing
suppression hack relied on.

**It surfaced a live bug.** `IconEntry` and badge `TextEntry` memoise their render
through `HasViewRenderCache` on a signature that listed `getLabel()` but *not*
`isLabelHidden()`. The moment the flag became a rendered difference, a
`hiddenLabel()` entry and a labelled sibling collided on one cache entry and the
hidden one drew a label anyway — request-scoped and shared across clones, so a
single repeatable mixing the two hits it. Both signatures now record label
visibility. `EntryHiddenLabelTest` covers it, and the guard was verified to fail
with the signature reverted.

**What it changed.** `grep -rn "isLabelHidden" packages/core/resources/views/
packages/core/src/Infolists packages/core/src/Panels` returns nothing — verified.
Every entry view gates on `@if($field->getLabel())`, and `HasLabel::getLabel()`
never returns null (it falls back to `Str::headline($name)`). So `hiddenLabel()`
is dead on every infolist and panel entry, which is precisely the use case
`HasLabel.php:29-31` documents for it.

**What it is worth.** Correctness first. As a byproduct it is the cheapest lever on
`RepeatableEntry`: measured, `->label('')` drops the per-row slope from 6.00 views
to 3.00. With `hiddenLabel()` working, "turn the labels off inside rows" becomes
available to users without an empty-string hack.

**The gate.** A unit test per entry view asserting `entry-label` renders zero times
under `hiddenLabel()` and once without — the shape `EntryActionsGuardTest` already
uses. Plus an EN/CS docs pass if the behaviour is currently documented as working.
It is not: `grep -rn hiddenLabel docs/ packages/boost/resources/` returns nothing,
so nothing claimed it worked and no docs correction was owed. `hiddenLabel()` is
undocumented on *every* surface, forms included — a pre-existing gap, not this
step's.

**What it gives up.** Nothing, unless someone is relying on `hiddenLabel()` being a
no-op, which nothing in the repo is.

### Step 4 — Resolve `Widget::lazy()`: delete it — **DONE**

**Landed.** `lazy()`, `isLazy()` and the `$lazy` property are gone from
`Widget.php`. `WidgetBaseTest`'s two cases became one asserting the methods do not
exist, with the reasoning inline; `StatsOverviewWidgetTest`'s case was dropped.
All six doc lines are gone from EN, CS and the boost mirror, and both upgrade
guides gained a section pointing at `<livewire:… lazy />` as the thing that does
work. `npm run docs:api`, `docs:check` and `docs:standard` all pass.

**What it changed.** `packages/core/src/Widgets/Widget.php:69-80` defined
`lazy()`/`isLazy()`. Verified: the only readers anywhere are
`WidgetBaseTest.php:139,143` and `StatsOverviewWidgetTest.php:75-77`. No widget
view mentions lazy, intersect, `wire:init` or island;
`widget-grid.blade.php:10-14` renders `{{ $widget }}` with no lazy branch. The
public API promises deferral and delivers nothing. Six doc lines advertise it:
`docs/core/widgets.md:56,666-667`, the CS mirror at `:57,671-672`, and
`packages/boost/resources/boost/docs/core/widgets.md:56,666-667` — the boost copy
is shipped to agents as guidance, so deleting only the `docs/` lines leaves the
claim in place.

**Why delete rather than wire up.** Per-widget islands do not compile (§2.2,
measured: `Undefined variable $widget` on first render). The only island shape
that works is one literal-named island around the whole grid, which is
`Dashboard::lazy()`, not `Widget::lazy()`. Per-widget deferral would need a nested
`<livewire:… lazy>` per widget or the partials route — a real feature, not a
one-liner, and nothing has asked for it.

**What it is worth.** Removes a documented lie. No measured performance effect.

**The gate.** `composer test:core`, `npm run docs:api` (which exists to catch docs
that disagree with the real public API), `npm run docs:standard` for EN/CS parity.

**What it gives up.** A public method disappears. That is a 2.0 change and the
branch is `2.0.0`, so the timing is right; it needs an upgrade-guide line.

### Step 5 — Extract the per-instance Alpine `x-data` blob into `Alpine.data()` — **DONE (all seven)**

**Landed.** Six controllers in a new forms bundle (`wire-forms-fields.js` ←
`resources/js/fields/{date-time-picker,time-picker,tags,rating,rich-editor,markdown-editor}.js`,
sharing `fields/typing.js`), and the seventh — the searchable-select combobox —
in **core's** `wire-core-dropdown.js`, because
`wire-core::partials.searchable-select` is included by seven surfaces across
forms *and* table (`tables/filters/select`, `ternary`, and three column filter
partials), so core is its canonical owner. The plan's framing of it as "Select"
understated the reach.

**Measured, marginal bytes per field, before → after:**

| Field | before | after | cut |
|---|---|---|---|
| DateTimePicker | 28,444 | 14,541 | −48.9% |
| TimePicker | 12,965 | 7,585 | −41.5% |
| Select (combobox) | 12,975 | 7,434 | −42.7% |
| MarkdownEditor | 10,589 | 6,705 | −36.7% |
| Tags | 10,679 | 8,015 | −24.9% |
| RichEditor | 15,767 | 14,115 | −10.5% |
| Rating | 10,598 | 9,414 | −11.2% |

**The plan overstated this, and the table says how.** "Up to ~45% of a
Select-heavy page" was right about the *blob*, but the blob is not all a heavy
field costs: what remains is genuine markup — 42 calendar cells, toolbar buttons
with inline SVG, star glyphs — which is why RichEditor and Rating barely move
while DateTimePicker halves. Anyone reading the 28 kB figure as recoverable
should read this table instead. The remaining markup is a different problem with
a different technique (render the panel only when open, or teleport one shared
panel), and is not in this plan.

**Three things worth recording for whoever does the next one.**

1. **The browser gate earned its place immediately.** Every Pest suite was green
   and all three datepicker drivers failed on the first run: the preview page
   never loaded the new bundle, so `wireDateTimePicker` was undefined and the
   `x-data` silently did nothing. `@wireStackScripts` is *additive* — an app that
   never adds it still has to get the controller — so each converted view now
   includes `wire-forms::partials.field-assets`, the same per-surface `@assets`
   contract `floating-assets` and the file-upload view already keep. Markup tests
   cannot see this class of break at all.
2. **`state` cannot move into the bundle.** `$wire.entangle` and `@entangle` are
   Alpine *magics*, in scope only inside an `x-data` expression, so the binding
   stays in the markup and everything else becomes config. `FormFieldPayloadTest`
   pins that, because a controller that lost its binding would quietly stop
   syncing rather than fail.
3. **A Blade `@if` inside the blob becomes a runtime branch.** A factory is
   compiled once and shared by every instance, so `@if($typeable)` around
   `applyTyped()` had to become `if (! this.typeable) return false`. Anything that
   used to vary the object's *shape* now has to vary its behaviour.

**The MarkdownEditor case is a correctness win, not just bytes.** Its renderer was
written entirely in HTML entities because it lived in an attribute — a raw `"` in
a regex literal had truncated the component once, and an entity written once
decoded to `replace(& with &)`, a no-op that let raw HTML reach `x-html`. In a
`.js` file neither trap exists. `MarkdownEditorXDataTest` was rewritten to assert
the sanitiser against the *shipped bundle*, which also catches a `resources/js`
edit that was never rebuilt.

**Browser gate: all 71 drivers pass**, including `spa-navigate` (22/22) — the one
that would catch a registrar arriving too late — and every table filter surface
that goes through the combobox. `verify-markdown-editor.mjs` needed its selector
updated (it located the component by `renderMd` *inside* the `x-data` attribute);
its assertions were kept and now run against the factory call.

**Twelve tests changed**, all of them asserting on the inlined body rather than on
behaviour: the assertions moved to where the truth now lives (the source module or
the built bundle) rather than being dropped. Two counts changed for a real reason
— wire-forms resolves two asset entries now, and `@wireStackScripts` emits eight
tags.

**TiptapEditor was already outside this.** It is a code-split ESM bundle the field
delivers on request; its `x-data` is `tiptapEditor(@js($field->getAlpineConfig()))`
already, which is the shape this step was aiming at.

**What it changed.** Seven field types inlined a per-instance `x-data` blob:
DateTimePicker (28,444 B/field), TiptapEditor (18,213), RichEditor (15,772),
Select (13,082), TimePicker (12,967), Tags (10,678), Rating (10,599),
MarkdownEditor (10,591). TextInput has zero `x-data` occurrences and costs 1,637
B. On a 20-Select page the blob measured 121,440 B = 45.7% of the page. The fix is
an `Alpine.data()` registration in the package bundle plus a small per-instance
config object — the shape the JS asset toolkit already supports
(`architecture/assets.md`, ADR 0024).

**What it is worth.** This is the largest byte item in wire-forms and it needs no
new render mechanism. It was **not measured as an implemented change** — the
121,440 B figure is the size of what would move, not a measured after-figure.
Quote it as "up to ~45% of a Select-heavy page's HTML", not as a delivered saving.
Note the gzip caveat from §0: the raw win is large, the wire win is much smaller
(the first Select on a page costs 3,270 gzipped bytes and each subsequent one
about 146 B), but the browser-side parse and morph cost of the raw HTML is real.

**The gate.** A new forms payload fuse measuring bytes per field for each of the
seven types before and after, with its own budget, plus
`npm run verify:drivers` — the combobox, the date picker and the editors are all
Alpine behaviour that Pest cannot see. This step must not ship without a driver
run; it is exactly the class of change the browser gate exists for.

**What it gives up.** Per-instance inline configuration becomes indirection through
a registered component. Any consumer who has been overriding a field view and
relying on the inline `x-data` shape breaks. Given seven field types, this is the
largest-blast-radius item on the list and should be done one type at a time,
starting with DateTimePicker (largest) rather than Select (most discussed).

### Step 6 — Move `CheckboxList`'s per-option `@include` out of the loop — **DONE**

**Landed** as `partials/checkbox-list-options.blade.php`: one partial owning the
grid `<div>` *and* the option loop, included once per grid from each of the two
layouts, replacing `checkbox-list-option.blade.php` (deleted — it was not a
documented extension point; `grep` found no reference outside the two call sites).
The grid wrapper moved in with the loop because both call sites emitted it
identically.

Measured on this machine after the change, warmed, min-of-7, opcache-off:
**20 options 4.20 → 2.65 ms (−36.9%)**, **100 options 11.34 → 4.13 ms (−63.6%)**;
render slope 1.00 → 0.00 per option. Markup verified unchanged by an A/B dump of
five shapes (flat, grouped, searchable, disabled, columns): the only diffs are the
random Livewire component id and one newline. `CheckboxListOptionsMarkupTest`
pins the per-option markup; the step 1 fuse pins the slope.

**What it changed.** `checkbox-list.blade.php:74` and `:82` each contained
`@include('wire-forms::partials.checkbox-list-option', …)` inside a `@foreach` —
verified, and a grep of all 69 `@include` sites in the wire-forms view tree finds
no other include written inside a loop. Measured: exactly one view render per
option, 10 options = 16 views, 210 options = 216.

**What it is worth.** Measured on an inlined copy prepended over the `wire-forms`
namespace, byte-identical output verified (14,195 B both ways, sole diff the
random `wire:id`): **3.98 → 2.97 ms at 20 options (−25.5%, 26 → 6 views)** and
**10.25 → 4.49 ms at 100 options (−56.3%)**. Opcache-off numbers; divide by ~5.

**How, not naively.** The partial's own header says it exists "Shared by the flat
and grouped layouts so the two can never drift apart", and it is included from two
sites. Pasting it into both duplicates markup and reintroduces the drift it
prevents. The correct shape is the one the sibling `checkbox-list-choices` layout
already uses structurally: one include *outside* the loop, with the loop inside the
partial. That keeps a single Blade owner and satisfies the "markup belongs in
Blade" rule. Do not cite the sibling layout as evidence of the payoff — measured
at equal option counts it is not faster (3.814 vs 3.655 ms at n=20, 8.825 vs 8.829
at n=100); it renders different markup. The payoff evidence is the byte-identical
A/B above.

**The gate.** Step 1's fuse, extended: `CheckboxList` slope drops from 1.00 to 0.00
views per option. Byte-identity assertion between old and new render.

**What it gives up.** The option partial stops being independently includable, which
is a (small) documented extension surface consideration —
`docs/forms/custom-fields.md` documents the field wrappers as extension points but
not this one. Check before shipping.

### Step 7 — Decide what a polling widget should re-render — **DONE (partials, not islands)**

**The cost, which this plan left unmeasured, is 21×.** A 12-widget grid renders in
6.5 ms / 57 219 B; one widget alone is 0.311 ms / 3 940 B. That is the whole of a
tick's waste before anything else on the page is counted, and it made the
decision rather than a judgement call.

**The plan's three options were the wrong shortlist.** It weighed a grid-level
island, a nested `<livewire:>` per widget, and accepting it — and missed the
mechanism this repository already owns. A partial is an ordinary attribute the
*server* picks at write time, which is exactly why it reaches where an island
cannot name a per-item region (§2.2, §4). So the tick calls
`refreshWidget('key')`, `WithWidgets` queues that widget, and the response
carries it alone. No island, no nested component, nothing outside the grid going
stale — the trade the plan was braced for does not arise.

**Widgets needed identity for it.** They are the one component here built by
`make()` with no name, so `Widget::key()` was added; `WithWidgets` stamps a
default from the widget's position in `getWidgets()` — the *unfiltered* list,
deliberately, since a widget hidden by a condition would otherwise renumber every
one after it and a tick would answer with the wrong widget. `PollDirective` gained
an `expression` so the canonical owner of the `wire:poll` vocabulary still owns
the whole attribute.

**The browser gate earned its place again.** Every PHP assertion passed while the
feature did nothing: the widget page loaded no wire bundle at all, so the anchor
was inert — the response arrived carrying the region and nothing on the page
changed, with no error and no console warning. The applier ships inside
`wire-core-dropdown.js`, and a widget-only dashboard has no dropdown, modal or
table to pull it in. A new `wire-core::partials.partial-assets` names that
dependency, and the grid includes it when any widget polls.
`verify-widget-poll.mjs` (15/15) is the first driver to cover widgets at all; it
proves the tick fires, answers with a region rather than a page, moves only the
polling widget's stamp, and **keeps ticking afterwards** — the anchor is nested
inside the polling element on purpose, because Livewire stops a poll whose
directive has left the element.

**One claim of mine did not survive checking, and is recorded so nobody re-derives
it.** The same reasoning suggested a plain `rowPartials()` table would be inert
too, since every `floating-assets` include in the table view is behind a
condition. It is not: a table without partials already carries the bundle, so the
table has never had this gap and needs no include. `@assets` output never appears
in a component's own HTML — Livewire pushes it into the component store — so a
markup assertion cannot see it either way, which is what made the first reading
look like a bug.

**What it changed.** `widget-grid.blade.php:12-13` emits
`$widget->getPollingDirective()` onto a plain `<div>`. From source, not docs:
`dist/livewire.esm.js:15566-15571` — a `wire:poll` with no expression evaluates
`$refresh`, a full component render. Island targeting is decided at `:14697-14726`
and fires only when the origin element carries `wire:island` or
`closestIsland(origin.el)` finds one; the widget grid has neither. So **one
polling widget re-renders the entire host component** — every other widget on the
dashboard, plus any table on the page.

**What it is worth.** Not measured. The cost is whatever else is on that page, which
is why it is a decision rather than a number. Per-widget islands are impossible
(§2.2, measured), so the options are one static island around the whole grid, a
nested `<livewire:>` per widget, or accepting it and documenting it.

**The gate.** Whichever option is chosen, a driver run — polling is timing
behaviour and Pest cannot see it. There is no CDP driver covering widgets today.

**What it gives up.** A grid-level island makes everything *outside* the grid go
stale on a poll tick, with no coverage analysis from Livewire to catch it (§2.2).
That is the trade, stated plainly.

### Step 8 — Close the sortable × `rowPartials` hole — **DONE**

**Reproduced first.** A new preview (`/previews/sortable-partials`:
`alwaysReorderable()` + `rowPartials()` + a `TextInputColumn`) and a new driver
(`workbench/scripts/verify-sortable-partials.mjs`). Before the fix, an inline save
in reorder mode took the row from 3 cells to 2, the page from 6 handles to 5, and
**produced no console error at all** — 9/13 checks. After, 13/13.

**Fixed by announcement, not by firing Livewire's hooks.** `partials.js` now
dispatches `wire:partials-applied` on `document` after a batch is morphed in,
carrying the anchors it replaced; `sortable.js` listens and re-adds the missing
handles. Firing `morph.updating`/`morph.updated` was the smaller change and is
the wrong one — `window.Livewire.trigger` *is* public, so it was available, but
wire-sortable's `onMorphUpdating` `skip()`s the cell the user is typing in, which
is right when the whole table is re-rendering around it and exactly backwards for
a partial whose entire purpose is to carry that cell's saved value back. An
announcement lets a listener repair what it owns without giving it a veto over a
targeted write. It is a DOM event rather than an export because the bundles are
separate IIFEs and core must not learn that downstream packages exist.

**The repair is narrower than a re-init.** `addRowDragHandles()` already skips any
row that still has a handle, and SortableJS is bound to the `<tbody>` rather than
to the rows — so a replaced row needs its handle back and nothing else to be
draggable again. No `destroyRowSortable()`, no width lock, no focus loss, which is
what makes it safe to run while a cell is focused where `onMorphUpdated`'s own
`editingCell()` early-return exists precisely to avoid a full `setup()`.

**The column half went in too.** Between a header drag and the server persisting
it the body carries the client's order while the server still renders its own, so
a partial in that window puts one row back into the old order. `onPartialsApplied`
re-derives the order from the headers — which *are* the record of a drag until the
server confirms it — and calls the existing `reorderBodyColumns()`. Note its guard
is separate from the handle repair's: `columnReorderable()` alone renders the
wrapper without row-reorder mode, and a shared guard would have skipped exactly
the case the column half is for.

**Not taken: `usesRowPartials()` returning false while reordering.** That is the
cheapest fix and it gives up partial rendering exactly where the table is largest,
since reorder mode drops pagination. `SortablePartialsTest` pins that the anchors
are still emitted, so the cheap fix cannot be reintroduced by accident.

**Browser gate: 72/72.** The one failure the first sweep reported was the new
driver's own `no failed requests` check tripping on `404 /favicon.ico` — the
preview ships none, and `verify-fill-handle.mjs` and `verify-fill-selection.mjs`
already exclude it for the same reason. It passes standalone because a repeated
run reuses the Chrome profile; a sweep gives each driver a fresh
`user-data-dir` and the request is made. Worth knowing before reading a sweep
failure as a regression.

**Coverage went from nothing to four tests.** `grep -rn "rowPartials|wire:partial"
packages/sortable/` returned nothing before this. The new
`SortablePartialsTest` asserts the combination is offered, that a save really
answers with `wirePartials` and no `html` (the precondition the browser bug needs),
and that both halves of the contract are in the *shipped bundles* — the
announcement lives one package up and could otherwise be renamed out from under
the listener.

**What it changed.** This is a correctness bug, not performance.
`packages/sortable/resources/js/sortable.js:300-306` prepends a handle `<td>` to
every `<tr>` client-side, rebuilt only from
`window.Livewire.hook('morph.updated')`. The row-partial applier at
`packages/core/resources/js/support/partials.js:95` calls `window.Alpine.morph()`
with its own config that never fires that hook — in Livewire's source,
`morph.updating`/`morph.updated` fire only from `getMorphConfig()`
(`dist/livewire.esm.js:14248,14265,14283`), referenced at exactly two sites, both
Livewire's own passes. Measured on a
`->reorderable()->columnReorderable()->rowPartials()` table: the live row in
reorder mode has three `<td>`s, the partial replacement has two, and nothing
restores the handle. `Table::usesRowPartials()` (`Table.php:2346-2349`) returns the
raw flag with no sortable exclusion, and `grep -rn "rowPartials\|wire:partial"
packages/sortable/` returns nothing — zero coverage of the combination.

**What it is worth.** A broken drag handle in a narrow but reachable window
(`rowReorderable && isReordering`). `columnReorderable` has a smaller version via
`reorderColumnCells`.

**The gate.** A CDP driver: enter reorder mode on a `rowPartials()` table, trigger
an inline cell save, assert the handle cell is still present and still drags. Pest
sees the markup, not the morph.

**What it gives up.** The cheapest fix is for `usesRowPartials()` to return false
while reordering, which gives up partial rendering exactly where the table is
largest (reorder mode drops pagination). The better fix is for the partial applier
to fire the morph hooks, which risks other listeners.

### Step 9 — Re-cost the sortable reorder write — **DONE**

**The trade the plan warned about does not exist.** It expected that "sending only
the moved range means the server must reconstruct the rest" and that
`resolveReorderSlots()` would have to be re-derived. It does not: that function
redistributes the dragged rows' **own** existing order values among themselves and
leaves untold rows alone, which makes it correct for any contiguous subset by
construction. So the client-side change needed no server change at all, and the
guarantee the plan was protecting — a filtered or paginated drag cannot renumber
invisible rows — is the very property that makes the small payload safe.

**Landed.** `onStart` records the key order, `onEnd` diffs it, `reorderPayload()`
sends the slice between the first and last position that changed. `order` stays
the absolute 1-based position, because `canReorder()` / `beforeReorder()` /
`afterReorder()` receive the payload even though the write ignores it. An
uncomparable before/after — a morph that added or removed a row mid-drag — falls
back to the whole tbody, always correct and only expensive.

**Measured as the slope the plan asked for.** Full payload: query count rises
**1.0 per row** of table. Range payload: **0** — the same drag costs the same on a
20-row and a 60-row table. `ReorderWriteCostTest` also pins that a range payload
lands byte-identical order values to the full one, which is the claim the whole
change rests on.

**The 5,000-row extrapolation is now moot rather than measured.** It was the
plan's own arithmetic, and the fix removes the scaling term it extrapolated, so
there was nothing worth measuring at that size.

**The sub-row half was a real N+1.** `WithTable::getTableRecords()`'s plugin
intercept returned before `eagerLoadSubRows()`, so reorder mode — the one mode
that drops pagination — went per parent. Measured before the fix: twelve more
parents, twelve more queries; after, zero. The obligation belongs to anything that
intercepts the fetch, which is now said in the code and in
`architecture/sortable.md`.

**Browser gate.** `verify-sortable-everything` (18/18) does real drags and already
asserted the properties a wrong payload would break — "every row the search hid
kept its exact position", "page one is untouched by a drag that happened on page
two" — so the range payload is verified where it matters, not just in PHP.

**What it changed.** Not a render. `sortable.js:220-229` collects **every**
`tr[wire:key]` in the tbody on each drop, not the moved rows;
`WithSortable.php:200-212` then issues one UPDATE per item in a transaction.
Measured at 200 items: 201 queries, 4,985 B of JSON. On a 5,000-row table in
`alwaysReorderable()` mode — where `mountWithSortable()` sets `isReordering = true`
with no toggle and no way back (`WithSortable.php:94-96`, `getTableToolbarWidgets()`
returns `[]` at `:63`) — that extrapolates to roughly 125 KB of request body, a
5,000-binding `whereIn`, and 5,000 UPDATEs holding row locks in one transaction,
on every drop. The extrapolation is arithmetic, not a measurement.

Also worth fixing while in there: the reorder-mode intercept returns at
`WithTable.php:1073-1079` *before* `eagerLoadSubRows()` at `:1102`, so a sub-row
table in reorder mode loses batch sub-row loading and goes per-parent.

**The gate.** A query-count slope test — the third axis §4.8 of the migration plan
already asks for. Assert the per-drop query count does not scale with total row
count once the fix lands.

**What it gives up.** Sending only the moved range means the server must reconstruct
the rest, and `resolveReorderSlots()` (`WithSortable.php:236-271`) deliberately
redistributes existing order values so a filtered drag cannot renumber invisible
rows. Changing the payload shape means re-deriving that logic. Not free.

### Step 10 — Only then: an opt-in per-field partial seam — **DONE (diff, not dependency analysis)**

**The design question was put to the maintainer and answered: compare, don't
reason.** The plan's §2.1 proposed an opt-in coverage signal plus a visible-shape
signature over `isVisible()`. That covers `visibleWhen` siblings and nothing else
— a sibling whose `options()`, `label()` or `helperText()` closure reads the
updated field's state also changes markup, and no signature over visibility sees
it. So the seam renders the fields and compares each one's markup against what it
last sent, exactly as `WithTable::queueChangedRowPartials()` compares a row. The
`visibleWhen` carve-out then needs no special case at all: a field appearing
changes the *set*, which is a shape change and falls back to a full render.

**The measurement that reshapes the feature.** A `TextInput` renders no `value`
attribute — the value rides `wire:model` and the data payload — so **an ordinary
keystroke commit changes no markup anywhere**. The most common outcome is not "send
one field", it is "send nothing at all", and the response carries neither a view
nor a region. The plan's framing (a one-field partial saving 88.1% of response
bytes) assumed the edited field had to be sent. It does not.

**Measured on a 12-field form:** full render 19 860 B (gz 914) against one field's
1 562 B (gz 399) — 12.7× raw, 2.3× gzipped, matching §1.2's gzip caveat.

**All four blockers were real and all four are closed.**

1. `Undefined variable $errors` — reproduced, then fixed by
   `IslandViewScope::asLivewireRender()` sharing the component's view error bag.
2. `Using $this when not in object context` on `FileUpload` — reproduced, fixed by
   the same method opening a `startLivewireRendering()` window. Kept separate from
   `within()` on purpose: that window also switches on morph markers, and
   `Table::toHtml()` renders `{{ $table }}` outside any Livewire render.
3. The anchor, gated on the opt-in the way the table gates its row anchors, and
   fed to the 23 wrapper-bearing views through shared view scope — a field builds
   its view from PHP and inherits nothing from the form's own view. The eight
   views with no wrapper are detected by *looking for the anchor in the markup*
   rather than by keeping a list, so the rule cannot drift as views change.
4. The update path: `update()` now records that an update happened rather than
   forcing outright, and `covered()` asks the component. `$commit` is exempted from
   the "a call that queued nothing forces the render" rule, which is what lets the
   view be skipped at all on a `wire:model.live` request.

**One thing PHP cannot see, and the driver can.** `Testable::set()` sends updates
with `calls: []`, so `PartialRenderHook::call()` never runs and the view is not
skipped in a PHP test. A browser always sends `$commit` alongside. So
`verify-field-partials.mjs` (13/13) is where "the response carried no view" is
actually asserted — along with the region reaching the DOM, which is the half that
caught the same missing-applier trap as steps 5 and 7: a form of plain inputs has
no dropdown, modal or table to pull `wire-core-dropdown.js` in, so `form.blade.php`
now includes `partial-assets` when the flag is on.

**The sweep caught a regression of my own, which is what it is for.** Putting the
wider scope in `PartialRenderHook` for *every* partial grew the table's row
markup, because `startLivewireRendering()` also switches on the morph markers the
table spent real work removing (848–1035 B per row). Every PHP suite passed —
`TablePayloadFuseTest` budgets the page, not a partial — and only
`verify-row-partials` saw it. The hook uses the narrow scope again and wire-forms
opens the wider one around its own render, where it is the one region that needs
markers. Verified byte-identical against a stash of the core changes: 17 803 B
either way.

**Not done:** `callFieldAction` as a trigger, which §5 rules unsound without write
tracking that still does not exist, and `addRepeaterItem`, whose measured win
shrinks as the request gets expensive. Neither is needed: the update path is the
one the feature is for, and it is the one that works.

**What it changed.** Everything in §2. `WithForms` does not compose
`InteractsWithPartials`, but that is not a gate — the hook is registered globally
for every Livewire component at `WireCoreServiceProvider.php:150` and reads the
store key with no trait check, and `packages/forms/composer.json` already requires
`wire-core`. (While there, fix the stale comment at `WithTable.php:104-106`, which
says nothing calls `renderPartial()` yet; the same file calls it at 673, 706, 727,
736 and 2530.) The actual work is four things:

1. `IslandViewScope::within()` must also share the component's view error bag, or
   partials silently drop validation errors — measured, §2.3. Shared core change.
2. A `startLivewireRendering()` window, or four field views (repeater,
   repeater-table, builder, file-upload) throw `Using $this when not in object
   context` — measured, §2.3.
3. An anchor, gated the way the table gates it
   (`SummaryRenderer.php:184`, `RowRenderPlan.php:146`) because
   `partials.js:68` requires exactly one anchor per name and errors on duplicates.
   `field-wrapper-start.blade.php:12` gives 23 of 31 field views a `wire:key`
   already, but eight views have no wrapper at all and Select's option modals sit
   *outside* it.
4. The update-path decision from §2.1: an opt-in coverage signal, a `skipRender`
   writer on the update path, and a rule that does not treat `model.live`'s
   `$commit` as an uncovered call — plus a visible-shape check so
   `visibleWhen` siblings cannot go stale.

**What it is worth, honestly.** For a form of N cheap flat fields, a one-field
partial saves **88.1% of response bytes and 71.2% of a middleware-free round trip
at N=12** (measured, §1.2), rising with N. On a 25-field mixed form the partial
path measured 1.313 ms / 3 view renders against 14.06 ms / 97 views / 99,684 B for
the full render. Gzipped, the byte cut is 2.1× (53%), not 22× — and it degrades
with state size, since the Livewire snapshot ships the full data array regardless
(measured: partial snapshot 947 B at 8-char values, 25,749 B at 1000-char values).

Two named surfaces do **not** deliver that. `addRepeaterItem` measured 43.2% /
31.9% / 19.3% of bytes saved at 3 / 5 / 10 items — the saving shrinks as the
request gets expensive, because the partial *is* the repeater. And `callFieldAction`
is unsound as a one-field partial: `InteractsWithFieldActions.php:31-60` invokes an
arbitrary user closure through `app()->call($callback, ['set' => …])`, and
`InteractsWithState.php:63-72` lets `$set()` write any sibling path with nothing
recording which paths were written — the repo's own `FieldActionsTest.php:31-33`
does exactly that. Covering it needs write tracking that does not exist.

**The gate.** A forms benchmark suite registered in both `phpunit.xml` files
(§1.6), warmed and averaged the way `IslandDecompositionBenchmarkTest` is, plus a
staleness test that sets a `visibleWhen` trigger and asserts the sibling's
appearance/disappearance is reflected, plus a test that a validation error added
during a partial request reaches the client. That last one is the test whose
absence made the error bug invisible.

**What it gives up.** A second render path through forms, with the correctness
burden §2.1 describes. Given that the flat-form win is real but bounded, and that
the two obvious trigger surfaces are the weak ones, this should stay behind an
explicit `Form::fieldPartials()`-style opt-in and should not be default behaviour.

---

## 4. Considered and killed

**Per-field islands.** `@island($component->getStatePath())` in
`form.blade.php`'s `@foreach` does not compile — same shape as `IsmPerRow`,
pinned non-compiling in `IslandSemanticsTest.php:42-60`, and reproduced directly
on the widget grid (`Undefined variable $widget`, thrown on the first render).
`IslandCompiler::generateScopeProviderCode()` re-emits the directive's own
arguments into the extracted island file, which receives only `__livewire` plus
public properties.

**A static-name island around a form region.** Fires on model updates, yes —
measured, 258-byte fragment, no `html` effect. But Livewire does zero coverage
analysis, so everything outside goes stale; a mount-absent island is never in the
`islands` memo; after a normal full render the island emits `mode=skip` with an
empty body and the browser keeps stale markup; and the saving collapses toward
zero precisely because the island is most of the component.

**Per-widget islands / wiring up `Widget::lazy()`.** Same compile failure. Only
a whole-grid island compiles, which is a different feature. Delete the method
(step 4).

**Per-item repeater partials.** `partials.js:63-95` has no insert or delete path,
so any item-count change is inexpressible per item under any naming scheme. Not
because of reindexing — a container-scoped partial covers reindexing fine
(measured, 9,338 B, no `html` effect).

**A `wire:key` on the repeater item wrapper.** Index-derived keys pair exactly as
positional morph does; measured, a full reorder leaves the key set byte-identical
while state fully reverses. It would mirror `builder.blade.php:55`, which has the
same defect. Real fix requires item identity in state — out of scope.

**Memoising `findComponentByStatePath` or caching cloned item schemas.** Measured:
+2 clones per round trip, not O(rows); the whole `getItemSchema` clone loop is
0.1% of a round trip (0.031 ms of 32.0 ms at 20×3). Nothing there.

**Hoisting "form-constant" work out of the field loop.** Everything
`field-wrapper-start` computes is per-field. The form object and
`FormRuntime::prepare()` are already memoised. Measured headroom under 2% of a
field.

**Inlining `field-wrapper-start`/`-end` into the 23 field views.** Already
evaluated and rejected on file:
`architecture/plans/render-optimization-audit-2026-07-17.md:156` — "3c —
field-wrapper double-`@include`. EVALUATED, NOT REDUCED — byte-identity blocks
it", and the same entry prices the obvious remedy at zero ("Rendering start+end
separately from PHP is still two renders (no win)"). A probe reproduced exactly
the whitespace-trim delta it describes (413 vs 426 bytes). Two corrections for the
record: the halves *can* be inlined independently — Blade includes are textual, so
`-end` alone inlines correctly and drops one render per field — and the blocker is
byte-identity plus the fact that both partials are a documented public extension
point (`docs/forms/custom-fields.md:176,194` and the CS and boost mirrors), so 23
is a floor, not a total. The measured prize with opcache on is ~0.008 ms/field.
Not worth reopening.

If the wrappers are ever revisited, the mechanism is `Skeleton`
(`packages/core/src/Foundation/View/Skeleton.php`), which is core-owned and
already used outside the table by `Actions/Action.php:259`. The wrapper's shape
cardinality in a form is one or two, so 40 wrapper renders in a 20-field form
would collapse to one or two without duplicating markup. A probe measured an
inlined variant at 26–32% faster per field with byte-identical output, so the win
is real — it is the byte-identity guard and the public extension point that stand
in the way, not the absence of a mechanism.

**Trimming `@php` locals before an `@include` to shrink the copied scope.** Killed
by decomposition. `array_diff_key(get_defined_vars(), …)` measures 0.27 µs against
36.70 µs for the full `make()->render()` — 0.7%. Scope size costs ~0.02 µs/var;
growing the *partial* from 1 to 150 lines costs +113.5 µs against +2.6 µs for
growing the caller's scope from 0 to 50 vars. The direction was backwards by ~44×.
`text-input.blade.php` declares five `@php` locals, not thirteen, and three of them
are re-read later so they cannot be removed anyway.

**`->native()` on Select as an alternative to the partial mechanism.** The
measurement is real — 20 Selects: 28.00 → 14.67 ms (−51.0%) and 265,961 → 38,256 B
(−85.6%) — but it is an *initial mount* win, and a partial's win is on the update
round trip where native delivers nothing comparable: measured on the same shape,
native-no-partial cuts 85.6% while combobox-plus-partial cuts 95.0%, and the gap
widens with option count (at 50 options/field native collapses to 68.1% and the
partial holds at 95.0%). They also compose (99.3% together), so there is no
sequencing argument. And `->native()` is a feature removal, not an optimisation:
a native `<select>` cannot host search, remote search, or create-option
(`Select.php:91`, `select.blade.php:152-153`), and `createOptionForm()` sets
`searchable = true` without forcing `isNative()` false, so `->native()` silently
drops those affordances.

**`#[Renderless]` replacing `WithTable::markTableViewChanged()`.** Struck from
§4.6 of the migration plan. `HandleComponents::update()` runs `updateProperties()`
then `callMethods()`, and the skip decision at `:534` inspects `$calls` only
(`shouldSkipRenderAfterCalls`, `:600-606`). Measured: a commit of
`{updates: {perPage: '50'}, calls: [{method: 'editCell'}]}` with a `#[Renderless]
editCell` returned the new `perPage` in the snapshot and **no** `html` effect —
exactly the stale-view bug `markTableViewChanged()` prevents. Two calls where only
one is renderless do render (`every()` fails). The hand-rolled conditional skip is
strictly more correct. `#[Renderless]` is safe only for a method that can never
share a commit with a property update.

**`wire:sort` delegation.** Already settled the other way at
`livewire-4-migration-and-performance.md:863` ("stay self-contained") and `:898`,
with the open-decision entry struck at `:1544-1549`; §4.7 and the phase-5 table row
at `:497` are stale and should be reconciled. Three further reasons it would not
work: `dist/livewire.esm.js:7021` spreads user `config` **last** into
`new Sortable(el, {...})`, so supplying `onStart`/`onEnd` *replaces* the plugin's
own and deletes `keepElementsWithinMorphMarkers()` (unexported, unreachable from
user config) — the morph-marker guard this repo already treats as load-bearing;
`wire:sort`'s `($item, $position)` callback cannot reconstruct
`resolveReorderSlots()`'s redistribution of existing order values, and its
`position` is 0-based among filtered children while `reorderRows` consumes 1-based
order; and the package runs a *second* Sortable on `<thead>` for column reorder
that delegation would also have to cover. The bundle-size argument is also weaker
than it looked: of `wire-sortable.js`'s 45,478 B, roughly 38.6 KB is vendored
SortableJS and about 5 KB is the controller, which would be rewritten into config
rather than deleted; `packages/forms/dist/tiptap/` ships 288,669 + 96,431 + 51,151
B, so this is not the largest JS saving available.

**`data-loading` as a wholesale replacement for `wire:loading`.** Partly viable,
much narrower than §4.6 claims. There are 19 `wire:loading.attr` sites, not 15;
12 of them already carry `wire:target` on the same element naming the same method,
so `data-loading` is near like-for-like there. Only 7 are untargeted. The flagship
symptom ("a filter change disables the pager") does not exist under v4: all six
pager instances render inside `@island('data-region', always: true)`, the
search/filter controls are outside it, and `livewire.esm.js:15256-15262` bails for
an untargeted `wire:loading` when `closestIsland(el)` exists and the message has no
action for that island. Independently, all six pagination buttons also carry
`wire:click`, so `getTargets` harvests an implicit target and the untargeted branch
is never entered at all. Two sites can never convert: `wire:poll` sets
`targetEl: null` (`:15567`), so a poll tick stamps `data-loading` on nothing —
`tables/columns/poll.blade.php:25,29-30` depends on that — and
`file-upload.blade.php:82` vs `:102` are siblings of the input, not descendants.
Also, `.attr="disabled"` sets a real `disabled` attribute; no CSS on
`[data-loading]` reproduces that, so even the easy swaps are not literally
like-for-like. The Tailwind objection is *not* a blocker: measured against real
Tailwind binaries, `[[data-loading]_&]:hidden` compiles from 3.1, `group-data-[loading]:`
from 3.2, and the repo already ships arbitrary variants that need ≥3.1
(`HasColor.php:324`, `flex.blade.php:8`, `rich-editor.blade.php:267`). Only the
literal `in-data-loading:` / `not-data-loading:` tokens are v4-only, which makes
this a rename, not an impossibility.

**Rewording §4.6's `$errors` JS magic row as a payload win.** The mechanism note is
right that errors come from the server — `dist/livewire.esm.js:11418` is
`state.clientErrors ??= component.snapshot.memo.errors`, and a grep for "validate"
across the whole v4 bundle returns four hits, all HTML attribute-name lists, so
there is zero client-side rule evaluation. But the proposed reword makes the
payload *bigger*: today's `@error` block at `field-wrapper-end.blade.php:7-9`
emits ~70 bytes only when the field errors, while `wire:text="$errors.first(…)"`
is an always-present element with a longer attribute. The row needs a
disambiguating clause ("display only; errors still arrive from the server"), not a
rewrite. Two things in its favour that should be recorded: `mergeNewSnapshot` runs
on every commit independent of `effects.html`, so `memo.errors` stays fresh even on
HTML-free responses, and `clear(field)` is genuinely zero-request.

**Porting the table's payload-fuse *integers* to forms.** Those budgets are
one-sided ceilings whose comments are stale by 2.6–2.8× (§2.5). A forms fuse built
from freshly measured forms numbers passes by construction on day one, which is
correct and is how every budget in that file was authored.

**Closing indentation in forms views as an alternative to partials.** Measured:
whitespace-in-runs is 6.1–6.3% of a forms payload; comments (morph markers) are
38.8–40.1%. The 6% is the addressable part; the 40% comes off only by deleting
conditionals, which `TablePayloadFuseTest.php:366-371` warns against by name after
that exact move broke column reorder with both fuses green.

**`wire:intersect` for the table's lazy wrapper.** The bundling claim is right —
Livewire 4 ships `@alpinejs/intersect` (`dist/livewire.esm.js:5821,5848,13756`)
and `SupportLazyLoading.php:168` still emits `x-intersect`, so
`tables/index.blade.php:121-123` keeps working (note the migration plan's
`index.blade.php:341` cite is stale). But `x-intersect` yields `origin = null`,
and five features are origin-gated and unreachable from it: `data-loading`,
automatic island scoping, `.renderless`, `.async`, `.preserve-scroll`. So
`wire:intersect` is the only way to combine a viewport trigger with any of those —
e.g. a load-more sentinel inside an island. For the one existing site it buys
nothing (that wrapper sits outside both islands and needs a full render).
`wire:loading` is *not* origin-gated, so keeping `x-intersect` costs no loading
states. Worth noting: no CDP driver covers the lazy table wrapper, so "already
works under v4" is a source read, not a measured result.

**A pagination-disable driver assertion as evidence the `data-region` island
changed loading scope.** It would pass identically before and after the island
shipped, because those buttons carry `wire:click` and never take the untargeted
branch. The only genuinely target-less `wire:loading` inside the island is
`tables/columns/poll.blade.php:25,29,30`, and its own tick originates inside the
island so the indicator still fires.

**A "no chrome in forms, so the table's toolbar island has no analogue"
conclusion.** Measured on a bare all-TextInput form the chrome really is 0.34–0.57
ms — but that form has no chrome by construction (`form.blade.php` is a bare
`<div>` plus a foreach). A form with 3 Sections, 8 fields and a Repeater measures
6.60 ms and 42,679 B of fixed cost when sweeping repeater rows, stable across two
runs: 68% of the table's ms chrome and 48% of its byte chrome. The table's own
island runs in the *other* direction anyway — `index.blade.php:648` is
`@island('data-region', always: true)` and the payoff is rendering the rows while
skipping the toolbar. The forms analogue is an island around the repeater, not
around the whole form. Per-row/per-field granularity is still the larger win
(measured 93% ms / 96% bytes on that shape), so the preference ordering was right;
the reasoning was not.

**Comparing forms' per-field cost to the table's per-row cost.** The table's
benchmark rows carry 25 columns, so 0.469 ms/row is 0.019 ms/cell read-only and
about 0.26 ms per editable cell. Against ~0.6–0.8 ms per forms field. "Forms is a
per-item problem, the table is a fixed-overhead problem" is an artefact of quoting
the table in bundles of 25. If that contrast goes anywhere, it goes per *cell*,
and the forms side needs re-measuring with the table's warm-and-average method
first.

---

## 5. What is not worth doing

**Nothing in the reactive-dispatch path.** Measured at +2 clones per round trip.
There is no index, no memo and no cache to add there.

**No `getItemSchema` memo.** 0.1% of a round trip.

**No per-form wrapper-class memoisation.** Under 2% of a field, and the form-constant
fraction of `field-wrapper-start` is a slice of that.

**No inlining of the field wrappers.** Closed on 2026-07-17 for byte-identity and
public-extension-point reasons; ~0.008 ms/field with opcache on. If revisited, use
`Skeleton`, not duplication.

**No `RowRenderer` port to forms.** Its documented payoff is morph-marker
elimination, which required stable `wire:key`s on structurally-varying children and
was gated by `RowMorphKeysTest`. Forms' repeater items have no stable identity to
key by, and the removability of forms' markers was never measured — only their
size. Ruling it out on the "loop extraction is the point" reading would be wrong
too; it is out of scope because the precondition is missing, not because the
mechanism is.

**No `RepeatableEntry` skeletonisation as a performance item.** The repeatable's own
overhead is 2.5–4.3%; ~96% is the per-`TextEntry` render. The `entry-label` include
measures −6.8 µs against an unlabelled entry (zero within noise) inside a real
render; the 37.6 µs a standalone render of the partial costs is `view()` factory
overhead that `@include` does not pay. A careful A/B on the entry itself put the
saving at 54–61 µs/entry (20–33% of a *bare* entry's render, never the 50% a
render-count fraction suggests) — which on 10–30 entries is 0.5–2 ms, below the
noise of one round trip. Do step 3 for correctness; do not sell it as speed. Two
warnings for anyone measuring this: `View::prependNamespace` silently no-ops unless
`View::getFinder()->flush()` runs first, and a `View::composer('*')` counter left
running during timing charges the two paths differently and will report counts as
cost.

**No forms benchmark quoting bare absolute milliseconds.** The repo's own
calibration loop (`TableBenchmarkTest.php:47-63`) ran 0.71 ms here against a stated
4.1 ms reference. Quote slopes, ratios and gzipped bytes, and state the opcache
setting.

**No raw-HTML-byte gate as a proxy for network payload.** Measured 15.7× at
`gzip -6` on a mixed 25-field form, and roughly 19× on the Select-heavy shape. Gate
on gzipped body bytes, render time, and view-render slopes.

**`callFieldAction` as a partial trigger.** Unsound without write tracking that does
not exist (`$set()` may write any sibling path, and the repo's own tests do).

**`addRepeaterItem` as the headline partial surface.** Measured 43.2% / 31.9% /
19.3% byte saving at 3 / 5 / 10 items — the win shrinks exactly where it is
needed.

**One more thing that is worth doing and is not a performance item.** Forty
copyable infolist entries fire `@include('wire-core::partials.copy-assets')` forty
times; Livewire's `@assets` `ob_start`s and runs the body every time, discarding
all but the first. Measured cost +0.134 ms/entry (n=40: 11.444 → 16.788 ms), plus
40 `is_file()` stats to keep one result. That is the anti-pattern
`packages/core/src/Foundation/View/CellSync.php:20-24` was written to prevent, and it is a call-site guard of the kind step 3 of the
2026-07-17 audit already landed for `entry-actions`.
