# Livewire 4 — migration analysis and the performance case

Written 2026-08-13. Verdict first, evidence after.

**Target: 2.0.0.** The framework floor moves to Livewire 4 in the 2.0 line
(decided 2026-08-13, §3); 1.x stays on `^3.0`. `v2-master-plan.md` remains the
authoritative V2 plan — this document is the framework-floor gate feeding into it,
not a competing roadmap.

**Method.** Every claim about Livewire 4 below was read out of the `v4.4.0` tag
source (downloaded and grepped locally), not out of a blog post or the upgrade
guide alone. Where the upgrade guide and the source disagree, the source wins and
the file:line is quoted. The v3 side is `vendor/livewire/livewire` at `v3.8.3`,
which is what this repo currently resolves. Baseline numbers are from
`packages/table/tests/Benchmarks/WideTableBenchmarkTest.php`, run today.

---

## 0. Verdict

1. **The PHP compatibility surface is one line wide.** wireStack touches almost
   nothing of Livewire's internals. Exactly one call breaks on v4 — the property
   synthesizer registration — and the fix is a one-liner that is *already valid on
   v3*. Everything else this repo uses (`Synth`, `BaseUrl`, `setPropertyAttribute`,
   `Livewire\store()`, `LivewireManager::current()`, `WithFileUploads`,
   `TemporaryUploadedFile`, `WithPagination`, `@assets`, `@entangle`, `@js`, the
   `morph.*` JS hooks, `$wire.$commit`) is present and unchanged in v4.4.0.
2. **The risk is behavioural, not structural.** Four v4 semantic changes land on
   things this repo is deliberately clever about: `wire:model` becoming `.self` by
   default, `wire:model.live` requests running in parallel, non-blocking
   `wire:poll`, and `smart_wire_keys` injecting keys the payload fuse counts.
   None of these are caught by Pest — only `npm run verify:drivers` sees them.
3. **The performance case for v4 is islands, and it is large.** Today every commit
   re-renders and re-morphs the entire table. Measured: **23.5 ms** for a 25×20
   page with no editable columns, **178.8 ms** with 25. A single inline cell save
   pays all of it. Islands make that cost proportional to what changed.
4. **Islands are not a Blade-only change for this table.** `@island` bodies are
   extracted at compile time into standalone view files that cannot see the
   enclosing view's locals — and `tables/index.blade.php` computes ~80 locals in its
   head `@php` block. Islands therefore require extracting a render-plan owner
   first. That refactor is worth doing on its own, on v3, before v4 is in the
   picture.
5. **`wire:sort` is a strategic problem for wire-sortable.** Livewire 4 bundles
   SortableJS *inside* `dist/livewire.js` (124 `Sortable` symbols, the
   `sortablejs/modular/sortable.esm.js` banner is still in there) and ships a
   `wire:sort` directive. The package's client half is now duplicated by the
   framework.

---

## 1. Compatibility surface — everything wireStack touches

The whole Livewire footprint of five packages, ~74 k LOC:

| API | Used at | v3.8.3 | v4.4.0 | Status |
|---|---|---|---|---|
| `Livewire\Component` | 20 imports (docs/tests/host stubs) | ✔ | ✔ | parity |
| `HandleComponents::registerPropertySynthesizer()` | `WireTableServiceProvider.php:34` | ✔ | **gone** | **break** |
| `Synthesizers\Synth` (base class) | `TableStateSynthesizer.php:19` | ✔ | ✔ | parity |
| `SupportQueryString\BaseUrl` | `Livewire/TableUrl.php:22` | ✔ | ✔ | parity — ctor and `pushQueryStringEffect()` unchanged |
| `setPropertyAttribute()` | `WithTableQueryString` | ✔ | ✔ | parity (`HandlesAttributes.php:14` both) |
| `use function Livewire\store` | `WithTable.php:66,594` | ✔ | ✔ | parity (`helpers.php:138`) |
| `LivewireManager::current()` | `Notifications/Drivers/CurrentComponentDriver.php:47` | ✔ | ✔ | parity (`LivewireManager.php:120`) |
| `WithFileUploads`, `TemporaryUploadedFile` | forms `InteractsWithFileUploads` | ✔ | ✔ | parity, same namespace |
| `WithPagination` | table | ✔ | ✔ | parity |
| `@assets` / `@endassets` (27 / 9 uses) | 14 partials | ✔ | ✔ | parity (`SupportScriptsAndAssets.php:93`) |
| `@entangle` (12 uses) | modals, pickers | ✔ | ✔ | parity — still deferred by default, `.live` opt-in |
| `@js` (82 uses) | everywhere | ✔ | ✔ | parity |
| JS `Livewire.hook('morph.updating'\|'morph.updated'\|'morphed')` | sortable, record-actions | ✔ | ✔ | all three present in v4 `dist/livewire.js` |
| JS `$wire.$commit()` | `record-selection.js:153,160,302` | ✔ | ✔ | parity |
| JS `commit` / `request` hooks | **not used** | — | deprecated | dodged by luck |
| `$wire.$js('name', fn)` | **not used** | — | deprecated | dodged |
| `$this->stream()` | **not used** | — | signature changed | dodged |
| `<livewire:x>` unclosed tags | **not used** in package views | — | breaking | dodged |
| `wire:transition` | **not used** | — | now View Transitions API | dodged |

### 1.1 The one break, and its fix

`packages/table/src/WireTableServiceProvider.php:33-35`:

```php
app(HandleComponents::class)
    ->registerPropertySynthesizer(TableStateSynthesizer::class);
```

In v4 the synthesizer registry moved out of `HandleComponents` into a new
mechanism. `HandleComponents::__construct(protected HandleSynths $synths)`
(v4 `HandleComponents.php:24`) and the registration method is
`HandleSynths::registerSynth()` (v4 `HandleSynths/HandleSynths.php:29`).
`registerPropertySynthesizer` does not exist anywhere in the v4 tree.

The fix is the public facade method, which exists in **both** versions and
delegates to the right place in each:

```php
Livewire::propertySynthesizer(TableStateSynthesizer::class);
```

- v3.8.3 `LivewireManager.php:41-44` → `HandleComponents::registerPropertySynthesizer()`
- v4.4.0 `LivewireManager.php:58-61` → `HandleSynths::registerSynth()`

**This should ship now**, in a 1.17.x patch, independent of any migration
decision. It is strictly more correct on v3 too (reaching into a `Mechanisms\`
class was never the supported seam) and it removes the only hard blocker.

**Shipped 2026-08-13** as `app(LivewireManager::class)->propertySynthesizer(...)`
rather than through the `Livewire` facade: the facade's `@method` list does not
carry `propertySynthesizer` in either version, so PHPStan rejects the facade call.
The container route is the same seam, is PHPStan-clean, and matches the pattern
already used by core's `CurrentComponentDriver`. `LivewireManager` is a container
singleton in both (`LivewireServiceProvider.php:25-27` in v3, `:30-31` in v4).

Two things checked while in there, both of which turned out **not** to need
action:

- v4's `HandleSynths::findByTarget()` caches synth matches by
  `get_debug_type($target)` (`HandleSynths.php:168-180`). Our `match()` is a plain
  `instanceof StateContainer`, so the cache is correct for us.
- `Synth::matchByType()` is **not** new in v4 — it exists in v3.8.3's base class
  too. It is only consulted for a *typed property update arriving without synth
  meta* (`HandleSynths.php:124`), and our container always dehydrates with its
  meta key, so that path is never taken for `tableState`. Implementing it would be
  unreachable code, which the coverage gate would rightly reject.

---

## 2. Behavioural changes that land on our cleverness

These are the ones that pass PHPUnit and break in a browser.

### 2.1 `wire:model` is `.self` by default

v4: "`wire:model` now only listens for events originating directly on the element
itself." Four places in this repo push a value by dispatching a DOM event:

| Site | Dispatch |
|---|---|
| `forms/.../file-upload.blade.php:56` | `$refs.fileInput.dispatchEvent(new Event('change', {bubbles:true}))` |
| `forms/.../rich-editor.blade.php:42` | `$refs.textarea.dispatchEvent(new Event('input'))` |
| `forms/resources/js/tiptap-editor.js:110` | `$refs.hiddenInput.dispatchEvent(new Event('input', {bubbles:true}))` |
| `forms/resources/js/image-processor.js:303` | `input.dispatchEvent(new Event('change', {bubbles:true}))` |

All four dispatch **on the element that carries `wire:model`**, so `event.target
=== element` holds and `.self` semantics are satisfied. This is a read of the
code, not a browser observation — it belongs in the driver gate, not in a
confident claim.

The second half of this change is a **consumer-facing API risk**:
`CanBeLive::getWireModelModifier()` (core) lets an app choose the modifier that
every forms field interpolates into `wire:model.{modifier}`. An app passing
`blur` or `change` gets different semantics on v4 (client-side sync timing, not
just network timing) — the documented remedy being `.live.blur` / `.live.change`.
Since we own the string that gets built, we can map it: on v4, rewrite a bare
`blur`/`change` to `live.blur`/`live.change` inside the concern, so consumer code
does not change meaning under them. That is a two-line change in one canonical
owner, which is exactly what that concern exists for.

### 2.2 `wire:model.live` requests now run in parallel, `wire:poll` no longer blocks

This is the highest-risk item for wire-table, because the inline-edit path assumes
commits are serialised:

- `core/resources/js/dropdown.js:590-700` — the editable cell holds an
  optimistic-lock version resolved at render time, commits through
  `$wire.updateTableCell(key, col, next, ver)`, and on success broadcasts
  `wire-editable-committed` so sibling cells of the same host can bump their
  version.
- `record-selection.js:302` — selection commits are debounced 350 ms and then
  `$wire.$commit()`.
- Polling (`Table::poll()`), plus `live(broadcast: true)`, re-read on their own
  schedule.

On v3, two of those overlapping meant one waited. On v4 they can be in flight
together, so two cells committing near-simultaneously can each carry a version
resolved before the other's broadcast, and the loser gets a conflict where it
previously got a queue. That may well be *correct* behaviour (it is what
optimistic locking is for) but it changes how often the conflict branch is taken,
and the conflict branch is the least-exercised path in the editable cell.

Action: a dedicated CDP driver that fires two cell commits in the same tick and
asserts the conflict UI, run on both v3 and v4. Nothing else will catch it.

### 2.3 `smart_wire_keys` defaults to `true`

v4 `SupportCompiledWireKeys/SupportCompiledWireKeys.php:30` — on unless
`livewire.smart_wire_keys` is `false`. It compiles keys into loops. The table's
body row loop is assembled in PHP (`index.blade.php:1129-1160`, deliberately not
a `@foreach`) so it is untouched, but every `@foreach` in the partials is in
scope.

Consequence: `TablePayloadFuseTest` budgets (`<1900` bytes, `≤11` whitespace runs,
`≤16` morph markers per row) are measurements of v3's compiler output. Under v4
they will move. **That is the test doing its job** — re-measure deliberately,
record the v4 numbers next to the v3 ones, do not just bump the numbers.

### 2.4 Endpoint prefix `/livewire/` → `/livewire-{hash}/`

Derived from `APP_KEY`. wireStack's own asset fallback is a separate route
(`Bundle::servedByRoute('wire-table')` etc.), so nothing in the packages breaks —
but `docs/getting-started.md` § JavaScript Assets and any deployment guidance
mentioning firewall/CDN rules for `/livewire/*` needs a v4 note.

---

## 3. Version-support strategy

**Decided 2026-08-13 (maintainer): Livewire 4 is a 2.0.0 gate.** The `2.0.0`
branch exists; 1.x stays on `"livewire/livewire": "^3.0"` and does not widen.

This document originally recommended one codebase on `^3.0|^4.0` across 1.x. That
recommendation is withdrawn — the 2.0 floor is the better call, and the reason is
in §4.2 rather than in the compatibility table. Dual support is nearly free *until
islands*, and islands are the entire performance case:

- Blade leaves an unknown directive in the output as literal text, so an
  `@island(...)` in a shared view renders the string `@island(...)` into the page
  on v3. Dual support therefore means either a no-op shim (which gives v3 users the
  restructuring cost with none of the benefit) or a second copy of
  `tables/index.blade.php`.
- A second copy of a 1 539-line view is the single most expensive maintenance
  liability this repo could take on, and it would have to be carried for the whole
  of the 1.x line.

So: **2.0.0 floors at `^4.0`, one view, no shim, no matrix.** What 1.x still gets
is §1.1 — that fix is correct on v3 on its own terms and should be back-ported so
1.x and 2.0 do not diverge on the registration seam.

Consequences of the 2.0 floor, to be settled deliberately rather than by drift:

- **`^4.0` also implies Laravel 11+/12+ in practice** and drops any pretence of a
  v3 escape hatch for consumers — a 2.0 upgrade guide has to say so on line one.
- **v2-master-plan.md does not know about Livewire 4.** It is the authoritative V2
  plan (phases V2.0 → V2.6, written 2026-07-06) and it was written when v4 was not
  on the table. The framework floor is a cross-cutting V2 gate, not a seventh
  phase: it changes the render model that V2.0's data-source work and V2.1's
  monolith split both build on. Register it there before either phase starts, or
  the two plans will contradict each other.
- **The render-plan extraction (§4.2, phase 2) is the one piece that should not
  wait for 2.0.** It is v3-safe, independently measurable, and it is the
  prerequisite for islands. Doing it on 1.x keeps the 2.0 diff to the parts that
  genuinely need v4.

CI on `2.0.0`: no matrix, just move the constraint and let the existing gates
report. The v3 legs stay on the 1.x branch where they belong.

---

## 4. Performance

### 4.1 The baseline, measured today

`WideTableBenchmarkTest`, 25 columns × 20 rows = 500 cells, full Livewire render,
this machine, PHP 8.5:

| editable columns | ms / render |
|---|---|
| 0 of 25 | 23.5 |
| 5 of 25 | 49.8 |
| 10 of 25 | 107.7 |
| 25 of 25 | 178.8 |

Payload per row (`TablePayloadFuseTest`): `<1900` bytes, `≤11` whitespace text
nodes, `≤16` morph-marker comments.

Two readings:

- **The editable ratio is the cost driver**, ~6 ms per editable column per 20 rows,
  i.e. ~0.3 ms per editable cell. Skeleton compilation has already taken the
  column-static half of that; what remains is per-record.
- **Everything pays the full number.** A keystroke in the search box
  (`wire:model.live.debounce.300ms`, `index.blade.php:472`), a sort click, a
  per-page change (`index.blade.php:1478`), a single cell save, a selection commit
  when summaries are on — each re-renders all 500 cells and morphs the result.

That is the structural fact the whole optimisation history in this repo has been
working around: skeleton compilation, whitespace elimination, morph-marker budgets,
the `Primitives`/`CellSync`/`RecordVersion` singletons. All of it reduces the
*constant* on a cost that is paid in full on every interaction.

### 4.2 What islands actually do (read from source)

- **Compile time.** `IslandCompiler::compileIsland()`
  (`SupportIslands/Compiler/IslandCompiler.php:88-121`) extracts each `@island`
  body into its own cached Blade file and replaces it in the parent with an
  `@island(..., token: '<hash>-<n>')` directive.
- **First render.** The island body renders inline, wrapped in fragment markers
  (`HandlesIslands.php:89-94`).
- **Every later request.** A *non-targeted* island returns
  `renderSkippedIsland()` — empty content, `mode: skip`
  (`HandlesIslands.php:97-105`). It is not rendered on the server, not sent, not
  morphed. `always: true` opts back in.
- **Targeted call.** `SupportIslands::call()` (`SupportIslands.php:37-75`) calls
  `$this->component->skipRender()` and renders only the island. The parent view —
  including its head `@php` block — never runs.
- **Island scope.** `renderIslandView()` (`HandlesIslands.php:185-207`) gives the
  island `['__livewire' => $this] + Utils::getPublicPropertiesDefinedOnSubclass($this)`
  plus the directive's `with:` array and `@use` imports hoisted from the parent
  (`IslandCompiler::extractImports()`). **Not** the parent view's local variables.

That last bullet is the design constraint for this repo.
`tables/index.blade.php` lines 1-260 compute ~80 locals — `$columnMeta`,
`$visibleColumns`, `$rowClassBinding`, `$actionClick`, the compiled per-column cell
skeletons, the whole sheet/mobile/gesture config — and the body reads them
everywhere. An island body sees none of it.

**So islands require extracting a render plan first**: move that head block into a
memoised owner (working name `TableRenderPlan`, resolved once per request from the
component, e.g. `$component->renderPlan()`), which the main view and every island
body both read. Worth stating plainly: **this refactor stands on its own**. It is
v3-safe, it is measurable with the existing benchmark, it kills the documented
hazard at `index.blade.php:24-26` (the legacy magic properties that "build the
deprecation map on every access and must not be used in per-row/per-column
loops"), and it is a prerequisite for anything island-shaped later.

### 4.3 Island seams, in payoff order

1. **`rows`** — targeted by sort / filter / page / cell save. Everything else on the
   page stops re-rendering and stops being morphed.
2. **`toolbar`** — search field, filters, column toggles, header actions. Today a
   search keystroke re-renders it because the field lives in it; as an island it
   renders once.
3. **`summary-footer` / `group-subtotal`** — aggregate work that changes rarely.
   `skip`-by-default is exactly the right semantic; target it from the write paths
   that can change a total.
4. **`pagination`**.
5. **`row-{key}`, per row** — the biggest single win and the one to gate. An inline
   cell save currently costs the full 178.8 ms and morphs ~38 kB; one row is ~9 ms
   and ~1.9 kB. Caveat from the source: `SupportIslands::dehydrate()` adds an
   `islands` memo listing every island's name and token, so per-row islands grow the
   snapshot linearly in rows. Fine at 20 rows; measure before allowing it at 200.
   Recommend it as an opt-in for editable tables only.

### 4.4 Sizing the win — measured decomposition

`WideTableBenchmarkTest` reports one number per shape. That number is a sum of
three things with different fates under islands, so
`IslandDecompositionBenchmarkTest` sweeps the page size and fits a line: the slope
is the per-row cost, the intercept is everything that does not scale with rows,
and a bare Livewire component with an empty view gives the framework floor.

Measured today, 25 columns, this machine, PHP 8.5:

```
framework floor (empty component):  1.48 ms      567 B

 0 of 25 editable   0.588 ms/row +  10.65 ms fixed   (framework 1.48 + chrome 9.17)
                     5 651 B/row +  88 390 B fixed
10 of 25 editable   3.147 ms/row +  10.73 ms fixed   (framework 1.48 + chrome 9.25)
                    25 699 B/row +  88 270 B fixed

20-row page, full render:   21.5 ms / 201 kB   (0 editable)
                            76.9 ms / 602 kB   (10 editable)
```

Three facts fall out:

1. **Chrome is a flat ~9.2 ms and ~88 kB**, independent of the editable ratio and
   of the page size. Every interaction pays it, including ones that cannot
   possibly change it — opening a row-action modal, the shortcut-help modal, a
   notification.
2. **The framework floor is 1.5 ms.** Islands never remove it (hydrate, snapshot,
   dehydrate, checksum are paid on every request whatever renders), so it is the
   asymptote for any island saving.
3. **Bytes are the harsher axis.** A 20-row page with 10 editable columns ships
   **602 kB** of HTML that the browser then morphs, on every commit. The payload
   fuse already established the morph is not cheap (~100 ms measured on a 40-row
   preview, longer than the round trip that carried it).

Applying the island semantics from §4.2 to these numbers gives the *modelled*
saving per interaction class. This is arithmetic over a measured decomposition,
not a measurement of an island implementation — it sizes the work, it does not
prove it:

| Interaction | Renders today | With islands | Saved (time / bytes) |
|---|---|---|---|
| **Nothing row-shaped changed** — open/close an action modal, halt modal, `?` help, a header action, a notification, a wizard step inside a modal | full page | chrome only, rows island skipped | **50 % / 56 %** (0 ed.)<br>**86 % / 85 %** (10 ed.) |
| **One row changed** — inline cell save, a row action's success, one toggle | full page | that row's island only | **90 % / 97 %** (0 ed.)<br>**94 % / 96 %** (10 ed.) |
| **The whole page changed** — search, sort, filter, page, poll tick | full page | rows island only, chrome skipped | **38 % / 44 %** (0 ed.)<br>**16 % / 15 %** (10 ed.) |

The shape of that table is the point: **the further an interaction is from the row
data, the more it saves** — and those are exactly the interactions that feel worst
today, because a user who opened a modal has no mental model in which the table
should be re-rendering at all.

### 4.5 The blocking that disappears

Separate from render cost, and arguably more visible to a user.

**v3's model, read from `dist/livewire.esm.js:8689-8730`.** `CommitBus.add()`
buffers 5 ms, then calls `findPoolWithComponent(commit.component)`; if a pool
already holds an in-flight commit **for that component**, no new pool is created.
The commit waits in `this.commits` and is only flushed by
`sendAnyQueuedCommits()` after the in-flight pool resolves.

The consequence for this repo is sharper than it looks, because **a wireStack
table is one Livewire component.** Rows, toolbar, filters, selection, inline
edits, row actions, bulk actions and polling are all traffic on the same
component, so in v3 they strictly serialise. Within the 5 ms buffer several
property updates squash into one commit — beyond it, they queue.

What that costs, using the measured render times as the queue's service time:

| Scenario | v3 today | v4 |
|---|---|---|
| `Table::poll()` on a 25×10-editable table | each tick is a 76.9 ms render + 602 kB morph, and **every user action in that window queues behind it** | poll is non-blocking; combined with a rows island the tick renders 63 ms → and only the rows morph |
| Typing in search (300 ms debounce) | keystroke *n+1*'s commit waits for *n*'s full round trip | `wire:model.live` requests run in parallel |
| Fill-handle drag across 20 cells | 20 × (render + RTT), strictly sequential | per-cell commits overlap; with a per-row island each is ~4.6 ms |
| Bulk export / bulk delete taking seconds | the whole table is frozen for the duration | `wire:click.async` — the action leaves the queue entirely |
| Cell save while a poll tick is in flight | the save waits | independent |

v4 decides this per action (`dist/livewire.esm.js:10742-10744`): an action is
async if the directive carries `.async`, if the method is listed in the
snapshot's `async` memo, or if the call metadata says so. So this is opt-in per
call site — which is what we want, because the inline-edit path deliberately
*wants* ordering (§2.2). The right posture for wire-table is: mark the
long-running, side-effect-only calls async (exports, bulk actions, telemetry),
and leave `updateTableCell` strictly ordered.

### 4.6 Other v4 primitives that replace machinery we hand-rolled

| We do | v4 offers | Note |
|---|---|---|
| `x-data="{ loaded: false }"` lazy wrapper, `index.blade.php:341` | `wire:intersect.once` (+ islands' own `lazy:`) | direct replacement |
| `skipRender()` juggling with a `function_exists('Livewire\store')` guard, `WithTable.php:593-594` | `.renderless` modifier / `#[Renderless]` | removes the guard and the store poke |
| 15 × `wire:loading.attr`, 9 × `wire:loading.remove` | automatic `data-loading` attribute on the triggering element | CSS instead of markup; big payload saving on wide tables |
| forms error display round-trips | `$errors` JS magic (`wire:text="$errors.first('email')"`) | client-side validation display |
| long bulk actions blocking the table | `wire:click.async` | export / bulk delete stay non-blocking |
| — | `csp_safe` config + a CSP build (`dist/livewire.csp.js`) | a supportable claim we cannot make today; would need its own driver run |

### 4.7 `wire:sort` vs wire-sortable

Verified: v4's `dist/livewire.js` bundles SortableJS (124 `Sortable` symbols, the
`sortablejs/modular/sortable.esm.js` source banner survives in the build) and ships
`src/Features/SupportWireSort`. `packages/sortable` bundles the same library into
`dist/wire-sortable.js`.

On v4 an app therefore ships SortableJS twice. The decision to take, explicitly:

- keep the package's **server** contract — the reorder pipeline, gap handling,
  scoped reordering, the `wire-table` integration — that is where its value is; and
- make the **client** half pluggable, delegating to `wire:sort` on v4 and keeping
  the bundled driver on v3.

That also removes the `morph.updating` / `morph.updated` skip dance in
`sortable.js:51-55`, which exists only because our controller and Livewire's morph
do not know about each other. Livewire's own directive does.

### 4.8 Performance work that needs no Livewire 4

- **Attack the 0.3 ms editable cell.** Column-static cost is already skeletonised;
  profile one editable cell render to see whether what remains is attribute
  escaping, `Skeleton::splice`, or the per-record version lookup.
- **Summaries.** `WithTable.php:1441+` computes summaries in memory over a
  collection. `SummaryBatchTest` exists, so the batching is tested — worth
  confirming the `selection` scope path uses SQL aggregates rather than hydrating
  the selected set for large selections.
- **A query-count fuse.** There is a render-count fuse and a payload fuse. Several
  tests count queries locally (`ColumnLoadRelationsTest`,
  `SelectColumnRelationshipLoadingTest`, `WithTableSubRowsTest`), but there is no
  per-row *slope* budget on query count the way there is on bytes. Same trick,
  third axis.

---

## 5. Phased plan

Re-anchored on the 2.0.0 decision (§3). Phases 0 and 1 are 1.x work that makes the
2.0 diff smaller; everything from 2 on lives on `2.0.0`.

| Phase | Content | Branch | Gate |
|---|---|---|---|
| 0 | Synthesizer registration through the supported seam — **done** (§1.1, `TableStateSynthesizerRegistrationTest`) | `2.0.0`, back-port to `1.x` | table + sortable + Integration, lint, analyse, coverage ✅ |
| 1 | Extract `TableRenderPlan` out of `index.blade.php`'s head block — **started**, see §5.1 | `2.0.0` | `WideTableBenchmarkTest` + `IslandDecompositionBenchmarkTest` before/after — must not regress |
| 2 | Floor all five `composer.json` at `^4.0`; fix what the suites report; `.blur`/`.change` mapping in `CanBeLive`; re-measure the payload fuse under v4 and record the new numbers next to the v3 ones | `2.0.0` | `composer test`, `npm run verify:drivers`, new concurrent-commit driver (§2.2) |
| 3 | Islands in `tables/index.blade.php`, seams 1–4 from §4.3 | `2.0.0` | decomposition benchmark before/after, published |
| 4 | Per-row islands behind a flag; `wire:intersect`, `.renderless`, `data-loading`, `wire:click.async` | `2.0.0` | drivers + snapshot-size check on the islands memo |
| 5 | `wire:sort` delegation, SFC in docs and recipes, CSP claim, 2.0 upgrade guide | `2.0.0` | full gate + docs EN/CS |

Phase 1 was scoped as 1.x work, before the 2.0 floor landed first. It is now
being done on `2.0.0` — the branch the islands it gates live on — and nothing
about it is v4-specific.

### 5.1 Phase 1 — how it is being done, and where it is

`tables/index.blade.php` opened with a **322-line `@php` block computing ~102
locals**. The move is **slice by slice**, not one commit: each group of locals
goes into {@see TableRenderPlan}, the view aliases what moved
(`$activeTableFilters = $plan->activeFilters`), and the ~1 200 lines below the
head block stay untouched. That keeps every step small enough to gate and
reversible on its own, and the aliases disappear later — as each island is carved
out, its body reads `$plan->…` directly, because an island body cannot see view
locals at all (§4.2).

The seam, landed with the first slice:

- `NyonCode\WireTable\Support\TableRenderPlan` — resolves, never renders.
- `WithTable::tableRenderPlan()` — memoised **within** one render so the view and
  every island body share one instance, and dropped **between** renders because a
  request may write state before it renders.

**The plan is resolved on first use, by the view that reads it** — not built by
`getTableProperty()` and handed over. That is a correctness requirement, not a
lazy-loading preference, and it cost a regression to learn:

> `wire-sortable`'s table view applies the user's persisted **column order** by
> calling `$table->columns(...)` inside its own `@php` block, deliberately ahead
> of including wire-table's view ("so that `$table->getColumns()` returns columns
> in the persisted order… without this, Livewire's morph undoes the visual
> reordering on every re-render").

A plan built when `getTableProperty()` returns has already read the *declared*
order, so the reorder was silently undone on every render. The general rule the
remaining slices must keep: **a view is allowed to reconfigure the table before
the part that reads the plan renders**, so the plan must be resolved as late as
possible.

Worth noting how it was caught, because it bears on how the rest of phase 1 is
verified: the byte-identical HTML check passed, because the table it renders has
no column reordering. Only `verify-column-reorder.mjs` went red. The HTML diff is
still the right first check — it is fast and exact — but it proves identity only
for the shapes it renders, and the driver sweep is what covers the rest.
`TableRenderPlanTest` now pins the invariant directly (verified to fail against
the eager build), so it is no longer browser-only.

The practical rule that follows: **the probe table must exercise what the slice
touches.** The actions slice was verified against a table carrying all four
action surfaces, the quiet style, both collapse modes and a row context menu —
not the plain table the first two slices used.

#### A second render-time mutation, found by sweeping for the first

A search for other views that reconfigure state mid-render turned up only one
more thing, and it is not in a view at all — it is behind a getter the plan now
calls:

`Table::composeRowActions()` applies the quiet style by calling `$action->quiet()`
on the Table's **own** `Action` instances, without cloning them. So
`getRowActionsForDisplay()` and `getMobileRowActionsForDisplay()` mutate shared
objects on a Table that `WithTable` memoises for the request.

Harmless today: the write is idempotent and re-derived from the same
`actionsStyle`, and the action slice's render output is byte-identical. But it is
the same shape as the column-order bug — state that changes when a getter is
called, on a memoised object — and the plan calls that getter earlier in the
render than the view did. Pinned by a test asserting the row actions come back
quiet. The clean fix, if it ever bites, already exists two methods away:
`getMobileHeaderActionGroup()` does `(clone $action)->withoutKeyboardShortcut()`.

**The plan is a composition root, one value object per slice** —
`$plan->state->activeFilters`, `$plan->columns->visible`. That shape was settled
at the second slice rather than the seventh, and not for taste:

- flat promoted properties would reach ~100 constructor parameters, with the
  documentation for all of them stranded in one docblock;
- the obvious escape — declaring the properties and filling them from a private
  method per slice — is legal PHP (a readonly property may be initialised from
  anywhere in the declaring class's scope, verified before relying on it) but
  **PHPStan rejects it**: `property.readOnlyAssignNotInConstructor` wants the
  assignment in the constructor literally, and it was right to, because the
  pattern makes "is this property set yet" unanswerable by reading the class.

So each slice gets its own small object with a promoted constructor and a
`resolve()` factory: `TableQueryState` and `ColumnRenderPlan` so far. Note the
latter is deliberately NOT `ColumnSet`, which already exists and answers
*config*-level questions about the columns a table declares; this one is what a
particular render resolved for a particular user, including their per-user column
visibility and the compiled per-column markup.

| Slice | Locals | Status |
|---|---|---|
| State + active filters | `search`, `filters`, `columnFilters`, `activeFilters`, `activeColumnFilters`, `sortColumn`, `sortDirection`, `perPage`, `hasActiveFilters` (+ the recursive `$filterHasValue` closure, now `holdsValue()`) | **done** |
| Columns | `visibleColumns`, `columnMeta` incl. the compiled `<td>` skeletons, `fillColumns`, `filterableColumns`, sub-row columns, `toggleableColumns`, `colSpan`, `hasCopyableColumn`, `mobileSortableColumns` | **done** |
| Actions | row/bulk/header/mobile action lists, both collapsed groups, the click resolvers, position/alignment/label/width | **done** |
| Layout | density and border, the stacked-on-mobile class pair, the five mobile-sheet classes | **done** |
| Paging | paginator, counts, `rangeFrom`/`rangeTo`, `headerRowCount` | next — needs `$records`; the slice that changes the builder signature (see §5.2) |
| Interaction | keyboard nav, gestures, active-row marker, record bindings, shortcut help | two edges out into skeletons |
| Skeletons | `rowSkeleton`, `selectionCellSkeleton`, selection announcements, row-class binding, page record keys | last — the dependency sink, and also needs `$records` |

That order is not the order they appear in the file. It comes from a dependency
map of the remaining locals: layout is the only group with no edges in or out,
paging is self-contained but forces the `$records` change, and skeletons consumes
from interaction (`keyboardNav`, `tableRole`, `activeRowConfig`) and from the
not-yet-sliced leftovers (`isSelectable`, `hasSummaries`), so it goes last.

**Dead locals found while mapping, to delete rather than migrate:**
`$actionsAlignment` (the raw `left`/`center`/`right`; only the derived class is
ever rendered — removed with this slice), `$isCompact`, `$hasRecordPointer`, and
`$usesRangeSelection`. The last is the interesting one: it looks live because
`partials/selection-cell.blade.php` uses a variable of that name, but that one is
supplied by `Table::getSelectionCellSkeleton()`, not by the view local.

The inverse trap, for whoever does the remaining slices: `$pollingConfig`,
`$shortcutLegend` and `$shortcutHelpEvent` read as dead — zero occurrences in the
view body — but are consumed by `partials/polling-indicator.blade.php` and
`partials/shortcut-help-modal.blade.php` through **implicit `@include` scope
inheritance**. Grep alone would delete them.

After four slices: the head block is **322 → 259 lines**, and of the 100
assignments left in it **55 are one-line aliases** off the plan — so **57 of the
original ~102 computations have moved**, including the whole hot path. What
remains is interaction, paging, skeletons and the leftovers.

Two behaviours the column slice's tests pinned that nothing had asserted before,
both easy to get backwards when the islands work starts moving this code again:

- `toggleableColumns` filters on `canView()` **alone**, deliberately. A column the
  user has hidden must stay in the toggle menu or there is no way to switch it
  back on; `visibleToggleableCount` is the other half, counting how many are
  currently on.
- `isFillEnabled` is not `fillHandle()`. Only a writable column is fillable, so a
  table can have the handle on and nothing to fill — the flag exists to catch a
  handle that would write nowhere.

**Evidence the first slice is a pure lift** — stronger than the benchmark, and
cheap to repeat for each of the remaining slices: two representative tables (one
plain, one searched + sorted + column-filtered + a cleared range filter) rendered
before and after produce **byte-identical HTML** once the random Livewire
component id and the checksum derived from it are normalised.

#### Benchmarking this on a developer machine

Worth writing down, because the first two attempts produced numbers that were not
merely noisy but self-contradictory — 5 editable columns "slower" than 10, and a
baseline run at 60.6 ms where the previous baseline run said 23.3.

The cause was the measurement itself. Swapping the files to switch between
baseline and change makes PhpStorm reindex — measured at **712 % CPU** — and that
storm lands on the benchmark run immediately after the swap. The A/B method was
sabotaging its own measurement.

`scripts`-free fix, kept in the scratchpad rather than the repo: swap, then
**wait for the load average to come down**, and only then measure. Two rounds,
alternating, at load 2:

| | 0 ed. | 5 ed. | 10 ed. | 25 ed. |
|---|---|---|---|---|
| baseline | 19.7, 20.4 | 42.5, 40.8 | 66.1, 61.4 | 123.2, 121.1 |
| with the plan | 21.2, 20.7 | 42.4, 42.5 | 62.8, 61.5 | 126.4, 122.2 |

No measurable regression: the differences run in both directions (10 editable
columns is *faster* with the plan in both rounds), and the spread within the
baseline alone — 61.4 to 66.1 at 10 editable — is wider than the gap between the
two variants. Nor is there a mechanism: the same calls happen, one object is
allocated, and `columnMeta` is now built in one pass instead of two.

The deterministic half is better evidence than the timings. **Bytes are unchanged
to the byte** across both slices — 5 651 B/row + 88 469 B fixed, 25 699 B/row +
88 349 B fixed, 201 502 B and 602 297 B totals — and the per-row slope did not
move.

That run also carries its own control: the decomposition benchmark reports a
**framework floor** measured from a bare Livewire component with an empty view,
which none of this work touches. When the whole run reads high, that floor reads
high with it (1.36 → 1.62 ms), which is how a machine-drift run is told apart
from a regression — in that run the full 20-row render rose *less* than the floor
did.

### 5.2 The paging slice, and what mapping it turned up

Paging is the slice that changes `TableRenderPlan::build()`'s signature, because
four of its locals read the page of records. Two facts settle *how*:

- **The records must be passed in, not fetched by the plan.** `getTableRecords()`
  is memoised in `WithTable::$cachedRecords` — except on the lazy-not-ready path,
  which returns a bare `collect()` **without** assigning the memo. So a plan that
  called it itself would hold a different (equally empty) instance than the view's
  `$records`. Passing them in also matches how `getTableProperty()` already
  resolves them once.
- **Nothing invalidates the records mid-render.** All six `cachedRecords = null`
  sites are action-phase (`setPage`, `refreshRow`, `importTable`,
  `invalidateTable`, and wire-sortable's two), and every `$component->` call the
  view makes is read-only. The one intra-method reset — `rehomeOutOfRangePage()`
  clamping an out-of-range page — completes *inside* `getTableRecords()` before
  the view is handed anything, so the plan can never observe the pre-clamp page.

#### A product gap found while mapping it — not to be fixed inside the refactor

The four record-derived locals all branch on
`$records instanceof LengthAwarePaginator`. That is a boolean over what is really
**three** cases, and the other two are reachable configuration:

| mode | set by | `total()` | `firstItem()` / `lastItem()` |
|---|---|---|---|
| standard | default | yes | yes |
| simple | `Table::simplePagination()` | **no** | yes |
| cursor | `Table::cursorPagination()` | **no** | **no** |

`WithTable::paginateQuery()` really does construct all three, and both are
exercised by existing tests. But because the view's boolean is false for the
latter two, they take the fallback branch everywhere — so a simple- or
cursor-paginated table today renders **no pagination links at all**
(`$hasMultiplePages = $hasPaginator && …`) and a "showing 1 – N of N" line
counting only the current page. Simple pagination is the sharper case: it *has*
working `firstItem()`/`lastItem()`, so the fallback is strictly worse than what
the type can answer.

Neither paginator fails loudly if called wrongly, which is why this went
unnoticed: both abstract paginators `__call`-forward to the underlying
collection, so `total()` on a cursor paginator ends in a `BadMethodCallException`
from `Collection` rather than a clear type error.

**The paging slice must preserve this exactly.** A refactor whose whole warrant is
byte-identical output is the wrong place to change what a simple-paginated table
renders. The value object should model the three cases faithfully — never
reaching `total()` for the latter two, never `firstItem()`/`lastItem()` for cursor
— while the view keeps branching as it does now. Fixing the UI is a separate
change with its own gate.

---

## 6. Open decisions

1. ~~**v3 support window.**~~ **Settled 2026-08-13**: Livewire 4 floors at 2.0.0,
   1.x stays on `^3.0` (§3). Consequently the "two views or a shim" question is
   moot — one view, no shim.
2. **Where this plan is registered.** `v2-master-plan.md` is the authoritative V2
   document and predates v4. Does the framework floor become an explicit V2 gate
   there, and does it land before or after V2.0's data-source work?
3. **Per-row islands** — opt-in for editable tables, or not at all until the
   `islands` memo growth is measured?
4. **wire-sortable's future** (§4.7) — pluggable client delegating to `wire:sort`,
   or stay self-contained and accept that v4 apps ship SortableJS twice?
5. **Do we want to claim CSP support?** v4 makes it reachable; it is a real
   differentiator for the enterprise positioning, and it is a test-surface
   commitment, not a config flag.
