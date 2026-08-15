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
3. ~~**`summary-footer` / `group-subtotal`** — aggregate work that changes rarely.
   `skip`-by-default is exactly the right semantic; target it from the write paths
   that can change a total.~~ **Withdrawn 2026-08-15**: the `skip` semantic exists
   but cannot be undone — a nested island that skips cannot be targeted back into
   life from a click inside its parent, so the totals would go stale on the paths
   that change them. §5.4.3 step 2a has the probe and what the cost actually was.
4. **`pagination`**.
5. ~~**`row-{key}`, per row**~~ — **impossible, established 2026-08-15.** Not a
   trade-off and not a cost: it does not compile. The reason is sharper than the
   docs' "islands can't be used in loops", and it is the *name* rather than the
   body that breaks. `IslandCompiler` extracts the body into its own view file and
   re-evaluates **the directive's own arguments inside that file**:

   ```php
   })(name: 'row-'.$row['id'], always: true);   // ← $row is not, and cannot be, here
   ```

   That file is given the component and its public properties, never the enclosing
   loop's variables, so a dynamic name throws `Undefined variable $row` on the very
   first render. **An island's name must be resolvable from the component alone**,
   which rules out one island per record by construction. Pinned by
   `IslandSemanticsTest`.

   Two things follow. The snapshot question this step was gated on — the `islands`
   memo growing linearly with rows — **cannot arise**: that memo lists hand-written
   directives, of which this repo has two. And the win it was after (an inline cell
   save costing one row instead of the page: ~9 ms and ~1.9 kB against the full
   render) needs a different mechanism — Filament's `wire:partial`, already
   assessed in §5.4.3a, which picks its anchors server-side and
   costs nothing per anchor.

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
| 4 | ~~Per-row islands behind a flag~~ (impossible — §4.3 step 5); `wire:intersect`, `.renderless`, `data-loading`, `wire:click.async` | `2.0.0` | drivers |
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
| Paging | paginator, counts, `rangeFrom`/`rangeTo`, `headerRowCount` | **done** — carried the builder's signature change (§5.2) |
| Interaction | keyboard nav, gestures, active-row marker, record bindings, shortcut help | **done** |
| Skeletons | `rowSkeleton`, `selectionCellSkeleton`, selection announcements, row-class binding, page record keys | **done** — as `RowRenderPlan`; reads `interaction()` for the two things the `<tr>` is shaped by |
| Shell | `isLazy`, `isTableReady`, `lazyPlaceholder`, `pollingConfig`, `pollingAttribute`, `liveChannel`, `filters`, `hasFilters`, sub-rows and grouping with their three guards, `hasViewMenu`, `viewMenuLabel` | **done** — last, because nothing depended on it; takes `hasToggles` from the columns group |

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

After all eight slices: the head block is **322 → 172 lines**, and of the 97
assignments left in it **96 are one-line aliases** off the plan. The
ninety-seventh is `$plan = $component->tableRenderPlan()`. **Every computation
has moved**; what remains is a name table.

The eighth is the `shell` slice — the sixteen locals nothing else depended on, so
they went last: lazy loading, polling and the live channel, the filter
definitions, sub-rows and grouping, and the view menu. `ShellRenderPlan` earns
its place on the three `&&` pairs the block wrote by hand
(`$hasSubRows && $table->isSubRowsExpandable()` and two more): each feature flag
answers from a default whether or not its feature is on, so the guard is what
stops an expand-all control rendering over rows that cannot expand. That rule now
has a test rather than a convention.

`hasViewMenu` is the one shell value with an edge outside its group — the menu
holds column toggles, sub-row expansion, or both — so it takes `hasToggles` as an
argument, the same shape `paging` uses. Writing its test also corrected an
assumption worth recording: **columns are toggleable by default**
(`Column::$toggleable = true`), so a table that declared nothing still has a view
menu. The "no optional regions" case only exists with `toggleable(false)`.

Verified the same way as every slice before it: ten previews chosen to exercise
each shell local — lazy, poll, `live(broadcast:)`, sub-rows both ways, grouping
with summaries, column filters, the gesture lab — normalised for the four things
that genuinely vary per request, and **byte-for-byte identical** across
2 084 123 B. Plus the benchmark gate, which is the one this slice did not skip.

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

At **two slices** this read as no measurable regression: the differences ran in
both directions, and the spread within the baseline alone — 61.4 to 66.1 at 10
editable — was wider than the gap between the variants.

> **That conclusion did not survive the rest of the phase, and the way it failed
> is the lesson.** The gate was then skipped on the next four slices on the
> strength of byte-identical output ("identical HTML ⇒ identical work"), which is
> sound for *correctness* and worthless for *cost*: allocating objects and writing
> properties produces no bytes. Measured across all six slices, the fixed cost had
> accumulated — see below. Run the gate per slice, or the arithmetic hides in the
> total.

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

#### The measured cost of all six slices

Run against the phase's starting point (`4e99925`), two rounds, load 2:

| editable | baseline | with the plan | Δ |
|---|---|---|---|
| 0 of 25 | 19.6, 18.9 | 20.1, 20.7 | **+1.15 ms** |
| 5 of 25 | 40.1, 40.2 | 42.1, 41.6 | **+1.70 ms** |
| 10 of 25 | 61.0, 59.4 | 60.7, 60.9 | +0.60 ms |
| 25 of 25 | 118.9, 118.3 | 121.0, 120.1 | **+1.95 ms** |

Three of the four ranges do not overlap, so this is signal. It does not grow with
the editable ratio — +1.15 ms at zero, +1.95 ms at twenty-five — which is the
signature of a **fixed per-render cost**, and the decomposition isolates it
exactly:

```
chrome (fixed):    8.13 → 9.25 ms   (+1.12)
per-row slope:     0.483 → 0.465    (unchanged)
bytes:             identical, to the byte
framework floor:   1.41 → 1.39 ms   ← untouched code, so the machine was steady
```

**≈ +1.1 ms fixed, nothing per row.** On a 19–61 ms render that is 2–6 %.

#### Why that is a design fault rather than an acceptable tax

The first reading was that this buys islands and islands repay it many times over.
That is backwards. **An eagerly-built plan is actively wrong under islands.**

When the `rows` island renders on its own, its body asks for the plan — and an
eager plan builds all seven groups although rows need only `columns`. It pays for
layout, actions, paging, interaction and state that reach no markup. The entire
premise of islands is that cost is proportional to what changed; an eager plan
installs a fixed floor the island cannot get under.

**Filament, which has the same problem, memoises lazily and per question** rather
than building a plan up front —
`packages/tables/src/Table/Concerns/HasColumns.php`:

```php
protected ?array $cachedVisibleColumns = null;

public function getVisibleColumns(): array
{
    return $this->cachedVisibleColumns ??= array_filter(
        $this->getColumns(),
        fn (Column $column): bool => $column->isVisible() && ! $column->isToggledHidden(),
    );
}

public function flushCachedVisibleColumns(): void { $this->cachedVisibleColumns = null; }
```

Same intent as `ColumnRenderPlan`, different shape: cached on the object it
belongs to, resolved on demand. They pair it with `#[Renderless]` /
`skipRenderAfterStateUpdated()` at the action level, and Filament v5's whole major
bump is Livewire 4 so it can use islands.

Worth noting for contrast that **Nova does not have this problem at all**: it is a
Vue SPA whose fields are client-side components (`IndexField.vue`, `FormField.vue`)
receiving a `field` prop, so nothing re-renders 500 cells of HTML on the server.
That is an architecture, not a technique to borrow.

**So the plan's groups should resolve lazily** — `$plan->columns()` built on first
ask — before the last slice lands, so `skeletons` arrives in the final shape
rather than being rewritten. It also settles the argument that forced the grouped
value objects in the first place: `readonly` properties could not be filled
lazily, and PHPStan was right to refuse it; accessor methods can.

### 5.2 The paging slice, and what mapping it turned up

Paging is the slice that changed `TableRenderPlan::build()`'s signature, because
four of its locals read the page of records. Two facts settled *how*:

- **`build()` takes the records; the accessor sources them.** They are a
  parameter so the class stays testable without a database, but
  `WithTable::tableRenderPlan()` fills it from `getTableRecords()` rather than
  requiring callers to — because an island body will ask for the plan with
  nothing but the component in hand, and must not have to find the records
  first. The memo makes that free everywhere except the lazy-not-ready path,
  which returns a fresh `collect()` each call; the two are value-identical, so
  the plan and the view agree either way.
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

### 5.3 What Filament v5 actually does — read from its source, not its docs

Three questions were put to `filamentphp/filament` at `5.x` (commit `0b845f7`),
which pins `livewire/livewire: ^4.1` and is therefore a real Livewire 4 consumer.
Two answers changed decisions here.

**Islands: Filament does not use them.** Zero occurrences of `@island` in 5.x or
6.x. They built their own partial rendering instead — a `wire:partial` HTML
attribute plus a `ComponentHook` — and **the tables package does not use even
that**: their inline cell save (`updateTableColumnState`) carries no
`#[Renderless]` and triggers no partial render, so **a single cell save in
Filament v5 re-renders the whole table**, exactly the pain §4.3 describes here.
There is no blueprint to copy for rows; islanding ours puts us ahead of them.

What is worth copying is the shape of their anchor. `wire:partial` costs nothing
per anchor — no server registration, nothing in the snapshot — and only the
partials the server *chose* are serialised, into one `partials` effect. That is
the direct answer to the open worry in §4.3 about the `islands` memo growing with
a per-row island: anchor count and memo cost *can* be decoupled, and their ~300
lines are the existence proof if Livewire's memo turns out to be the constraint.
Their fail-safe is worth copying outright: a full render is skipped only when
every call and update was individually covered by a partial, so an unaccounted
update degrades to a full render rather than to stale DOM.

**`wire:sort`: Filament does not use it either**, and the reason settles open
decision 4. Their `sortable.js` is byte-identical between 4.x and 5.x — they
crossed the Livewire 3→4 boundary without touching it — and `sortablejs ^1.15.0`
is still bundled in their *core* asset, so a Filament v5 app ships SortableJS
twice as well. The blocker is the contract, not inertia: `wire:sort` passes
`($item, $position)`, a single moved item, while their server (like ours) wants
the whole new order in one call so it can do one `CASE` UPDATE in a transaction
with before/after hooks. Delegating means reshaping the server half, not deleting
a bundle. **Recommendation: stay self-contained.**

**The `wire:model` modifier: they reached our conclusion independently.** Filament
has one owner (`HasStateBindingModifiers`) and fixed it in January 2026 with a
one-line change, `['blur']` → `['live', 'blur']` — the same answer as
`CanBeLive::getWireModelModifier()`. Two deliberate divergences remain: our
`live()` adds a 250 ms debounce where theirs relies on Livewire's built-in 150 ms,
and they inherit by *pulling* (a child asks its container) where we *push*.

That comparison also found a real bug here, which is the argument for doing it:
their `$entangle` branch suppresses blur and debounce, and ours did not. Eight of
our fields append the `wire:model` modifier to an `@entangle()` call, so
`liveOnBlur()` rendered `.entangle(...).live.blur` — `undefined` in the browser,
a field with no state — and had done since before Livewire 4. Fixed with
`CanBeLive::getEntangleModifier()`.

**File uploads: not comparable.** Filament never hand-rolled the merge we deleted
because they never had array-shaped state — they mint a UUID per file and upload
to `path.<uuid>`, so Livewire's `$append` never applies. Our deletion was right
for our regime. Their keyed design is more robust (per-file cancel, delete,
reorder; no races between parallel uploads) and is the shape to move to if
ordering bugs ever appear.

### 5.4 The ERP variant — many editors on one table

Everything above is written for a table with one reader. An ERP table has several
people in it at once, and two of the conclusions change under that premise. This
section is the variant, not a replacement: phases 1–3 are unchanged, the ordering
inside phase 4 is not.

**The premise, stated so it can be argued with:** several users hold the same
table open; rows are edited in place rather than through a form; the set is large
enough that a full re-render is felt; and a lost write is worse than a slow one.

#### 5.4.1 Reordering: the payload shape is a concurrency decision

§5.3 settled that we keep our own client rather than delegating to `wire:sort`.
That still holds. What does **not** hold under this premise is the other half of
today's design — the *contract*:

```php
// WithSortable::reorderRows()
@param  array<int, array{value: string|int, order: int}>  $items
```

The client sends **the whole new order**, computed from what that user could see.
That is last-writer-wins by construction: user A drags one row and silently
overwrites every reorder B made in the meantime, because A is not saying "move X
after Y" — A is asserting "the order is exactly this". Nothing can detect the
conflict, because nothing was claimed to be true beforehand.

The fix is not `wire:sort`. It is to separate two things the current design fuses:

| | today | ERP |
|---|---|---|
| client | ours | **ours** — scoped reordering, gap handling and the morph guard all live there |
| contract | whole order | **delta — "place X after Y" — plus the versions it assumed** |

A delta is mergeable and checkable: it names the moved record and its neighbours,
and it can carry the same optimistic-lock stamps the inline edit already uses, so
a reorder against a moved set is *refused* rather than silently applied. This is
the same discipline `CellEditPipeline` already has, extended to the one write that
currently lacks it.

Note this is precisely the shape `wire:sort` imposes (`$item, $position`), which
is worth saying plainly: the contract it would have forced on us is the right one
for ERP. We can adopt the shape without adopting the directive, and keep the
client and the `beforeReordering`/`afterReordering` hooks that the delegation
would have cost.

#### 5.4.2 Islands make staleness visible — that is the ERP trap

The counter-intuitive part, and the reason this section exists.

**Today's full re-render is accidentally a freshness mechanism.** Every commit
re-renders the whole table, so any interaction also refreshes everyone else's
changes. Nobody designed that; it falls out of the cost we are trying to remove.

With a per-row island, an inline save re-renders **that row only**. Every other
row keeps whatever it was showing — for as long as that user does nothing. In a
single-reader table that is invisible. With several editors it is a regression in
correctness-as-perceived, and it lands directly on the optimistic lock: **every
cell captures its record's version at render time**, so rows that never re-render
hold stamps that age, and the conflict branch is taken *more* often, not less.

Two findings from this session sharpen it. The lock's resolution is one second
(`RecordVersion::stamp()` is `updated_at` in whole seconds), and the sibling
broadcast that keeps versions current — `wire-editable-committed` — is dispatched
from the commit's *response* handler, so it only ever reaches cells in the same
browser.

**So islands are not enough on their own here; they are what makes the remedy
affordable.** The numbers are already measured in §4.5: `Table::poll()` on a
25×10-editable table costs **76.9 ms and 602 kB per tick** today, which is why
nobody enables it. With a rows island the tick renders ~63 ms and only the rows
morph; a single targeted row is **~4.6 ms**. Polling and `live(broadcast: true)`
— both of which already exist (`Table::poll()`, `getTableLiveChannel()`) — become
usable for the first time *because* of islands.

The ERP design is therefore **islands plus a freshness channel**, and the channel
should refresh the row island rather than the table: a version bump is a row-level
fact.

#### 5.4.3 Ordering, with the gate that decides each step

1. **The action-modal island** — ahead of the rows island, which is not the order
   §4.3 lists. It is smaller, it raises no memo question (one island per table,
   not per row), and it targets the ERP complaint directly: opening a dialog on a
   wide table currently re-renders 500 cells. §4.4 sizes that class of
   interaction — "nothing row-shaped changed" — at **86 % of the time and 85 % of
   the bytes** on a 10-editable table.

   The mechanism is not the obvious one, and the obvious reading is wrong. An
   island is **skipped** on every request after the mount unless it is `always` or
   explicitly targeted (`HandlesIslands::renderIslandDirective()`), so the saving
   does *not* come from skipping the modal — a closed modal's markup is empty
   anyway. It comes from targeting: `SupportIslands::call()` calls
   `$this->component->skipRender()` and renders **only** the island, so the
   response carries the modal and nothing else. That is Filament's behaviour
   reached natively, where they had to swap Livewire's `DataStore` to fake
   `skipRender` and ship their own morph JS.

   Targeting is client-driven, through **`wire:island="action-modals"`** on the
   element that fires the action (`js/features/supportIslands.js` reads any
   attribute starting `wire:island`; `$wire.$island(name)` is the imperative
   equivalent). That lands well here: `TableActionClickResolver` is already the
   single place that knows a modal action from a plain one, and the action button
   is skeleton-compiled per *shape* with `hasModal()` already in the shape key.

   **The risk to gate for** is the corollary of skipping: any request that changes
   modal state without targeting the island leaves the old modal DOM in place, and
   any request that targets it renders *nothing else* — so an action that both
   opens a modal and changes a row must not target. First step is therefore the
   pure-open path only, with everything else left on the full render. Gate: the
   modal drivers — `modal-layering`, `nested-modal`, `modal-open-on`,
   `option-wizard`, `wizard-live` — plus the halt-modal path.

   **Done, 2026-08-14.** `@island('action-modals', always: true)` in
   `tables/index.blade.php`, `wire:island="action-modals"` on any action with
   `hasModal()` in `actions/button.blade.php`, and one prerequisite nobody could
   have predicted from the docs — see 5.4.5. Measured by
   `verify-modal-island.mjs`, which reads the *response* rather than the DOM,
   because the modal looks identical either way: opening the same modal costs
   **9,559 B targeted** against **125,603 B untargeted**, on a four-row preview.
   The island is a fixed size; the full render is not, so the ratio grows with the
   row count — 13× here is the floor, not the headline.

   `always: true` is load-bearing and was found the hard way: without it an
   island is skipped on every request that does not target it, so a modal opened
   by a keyboard shortcut, the row context menu, `openOn()` or a test calling the
   method renders **nothing at all** (10 red tests in
   `ActionModalMobileVariantTest`). With it, the untargeted path is byte-for-byte
   today's behaviour and only the targeted one changes. Both paths are asserted
   in the driver.

2. **Rows island.** The next win — chrome stops re-rendering on every
   interaction. Gate: the decomposition benchmark, and a driver that asserts a
   cell save leaves the toolbar's DOM untouched.

   **Done, 2026-08-15.** Landed as `@island('data-region', always: true)` in two
   commits: a pure extraction of the region into
   `partials/data-region.blade.php` (byte-identical), then the island itself.

   **The boundary is wider than "rows", and deliberately so.** It holds the
   desktop table, the stacked mobile cards *and* the pagination footer, because
   one call can target exactly one island — `SupportIslands::call()` reads a
   single `$metadata['island']` — and all three change together. The cards are a
   second full rendering of the same records; the footer's "showing 1 - 10 of
   240" moves when an edit drops a row out of the active filter. Leaving either
   outside would have made the island a staleness generator, which is the ERP
   trap in 5.4.2.

   Measured on `/previews/table-overview` by `verify-rows-island.mjs`, comparing
   like with like — a targeted sort against a full render of **the same four
   rows**: **64 240 B of markup against 113 160 B, 43 % less**. The comparison
   matters more than the number: measuring the whole response body would have
   said 2 %, because the snapshot rides along either way and dwarfs the
   difference. The saving is fixed-size chrome, so the percentage falls as rows
   grow while the ~49 kB stays.

   The decomposition benchmark's byte fit is unchanged apart from the fragment
   markers — 25 699 B/row + 89 120 B fixed against 88 860 B before, i.e. **+260 B
   for the whole mechanism**.
2a. **Summary footer / group subtotals.** §4.3 put these third, on the reasoning
   that aggregate work changes rarely and `skip`-by-default is the right
   semantic. **Half of that is now unreachable, and the other half was never
   about islands.** Both findings are measured rather than argued.

   **The island half is a dead end either way.** A nested island under a
   rendering parent behaves like this on an update:

   ```
   PARENT ISLAND RENDERED ALONE, islands already mounted (n = 2):
     parent 2         present
     child-plain 2    ABSENT      ← mode=skip, empty content
     child-always 2   present
   ```

   > **Corrected 2026-08-15.** The first version of this probe rendered the parent
   > straight after a mount, where `islandIsMounting()` is still true and *every*
   > island takes the mounting branch and renders. It reported that nested islands
   > are never skipped, and this section drew that conclusion. Wrong: the flag
   > `SupportIslands::hydrate()` sets on every later request is what makes skipping
   > visible, and `markIslandsAsMounted()` is what a probe must call to see it. The
   > verdict below is unchanged; the reason for it is the opposite of what was
   > written here first. Pinned now by `IslandSemanticsTest` rather than by a
   > paragraph.

   So both settings are dead ends, for opposite reasons:

   - **without `always`** the child *is* skipped — and cannot be brought back,
     because a click inside the region targets the region (one call, one island;
     §5.4.4a). Totals would go stale on exactly the paths that change them: a page
     change, a cell save. That is the ERP trap in 5.4.2, and worse than the cost;
   - **with `always`** it renders every time the parent does, which is today's
     behaviour with extra markers.

   Nothing in between exists. To have a footer that skips *and* stays correct it
   would have to be a sibling of the region, targetable on its own — and it cannot
   be: it is the `<tfoot>` of the same `<table>`.

   **The real cost here was a duplicate, not a missing island.** A stacked table
   renders its totals twice into one document — the desktop `<tfoot>` and a card
   footer for the width that hides the table — and each include asked the host to
   compute them, so the whole `SummaryBatch` ran twice per render:

   ```
   before:  4 queries (2 aggregate)   select SUM(...), AVG(...) from ...   ← twice, identical
   after:   3 queries (1 aggregate)
   ```

   Memoised per render (dropped by `getTableProperty()`, like the render plan),
   keyed by scope so the toggle still recomputes, and never for the sub-rows
   scope, which is asked per parent record. Output byte-identical across the ten
   previews. On the tables this is for, that aggregate runs over the entire
   filtered set — which is the whole reason it is worth one query rather than
   two.

   Pinned by `SummaryComputedOnceTest` (verified to fail without the memo) and by
   the batching test next door, which now drops the memo before counting so it
   still measures batching rather than the memo.

3. ~~**Measure the `islands` memo** before going finer.~~ **Moot, 2026-08-15.**
   The memo lists hand-written `@island` directives, and there is no way to
   generate them per record (step 4), so it cannot grow with rows. This repo has
   two.
4. ~~**Per-row islands, editable tables only.**~~ **Impossible, 2026-08-15** —
   §4.3 step 5 has the mechanism. An island's name is re-evaluated inside its own
   compiled view file, which never sees a loop variable, so `row-{key}` throws on
   the first render. The win it was after — a cell save costing one row rather
   than the page — is real and unclaimed; `wire:partial` (§5.4.3a) is the only route
   left to it.
5. **The freshness channel.** Targeted row refresh driven by poll or broadcast,
   and — the part that is easy to forget — the refreshed row must carry a **fresh
   version stamp**, or islands will have made the lock worse rather than better.
   Gate: a driver with two browsers editing the same table.

#### 5.4.3a `wire:partial` — assessed 2026-08-15, and not yet

The escape hatch is Filament's and it is proven in production: `wire:partial`
anchors cost nothing per anchor — a plain HTML attribute, no server
registration, nothing in the snapshot — and only the partials the server chose
are serialised. Measured in their tree: **401 lines** (`DataStoreOverride` 36,
`PartialsComponentHook` 224, `partials.js` 141), plus what we would add around
it.

**First, a correction that changes the arithmetic.** A cell save is *not*
island-targeted today, and this plan said it was. Automatic targeting reads
`action.origin` — the DOM element that fired it — and returns immediately when
there is none:

```js
let origin = action.origin
if (! origin) return
```

Editable cells commit through `$wire.updateTableCell(…)` from Alpine, which has
no origin. Measured on `/previews/table-editable-fill`, the same write, twice:

```
as shipped                          hasHtml=true   no island   58 726 B
$wire.$island('data-region')…       hasHtml=false  data-region 42 374 B   −28 %
```

`$island` is a documented `$wire` magic and is present in this build. So the
region island is currently doing nothing for the most frequent interaction in an
ERP table, and one call-site change fixes that.

**What each option is worth.** Measured rather than extrapolated, on one
25-column, 20-row page with 10 editable columns — the shape this is for — by
`IslandDecompositionBenchmarkTest`'s cell-save case:

| an inline cell save costs | time | bytes |
|---|---|---|
| full component render (before targeting) | 77.2 ms | 609 268 B |
| `data-region` island (what it costs now) | 49.3 ms | 555 776 B — **36 % / 9 %** saved |
| one row (what `wire:partial` would leave) | 3.2 ms | 26 028 B — **96 % / 96 %** saved |

Read the byte column twice. Island targeting removes a **third of the time and
almost none of the bytes**, because on a 20×25 grid the region *is* the bytes —
the chrome it skips is a rounding error next to 500 cells. The 43 % and 28 %
measured earlier came from four-row previews, where chrome dominates; those
numbers are true and unrepresentative, which is exactly why this table exists.

So the remaining gap is **49.3 ms → 3.2 ms and 556 kB → 26 kB**, on the most
frequent write in an ERP grid. `wire:partial` is worth an order of magnitude more
than everything islands could reach here, and that is measured on the shape that
matters rather than inferred from a fit.

**Why not yet, in order of weight:**

1. ~~**It claims an app-wide container binding.**~~ **Answered 2026-08-15: it does
   not have to.** `$this->app->bind(DataStore::class, DataStoreOverride::class)`
   swaps a Livewire mechanism for the whole application, and two packages doing
   that in one app silently disable each other — which mattered, because these
   packages ship into apps that may already run Filament.

   > The reasoning that got here was also wrong on its own terms and is worth
   > correcting rather than quietly dropping: it said Filament may claim the
   > binding because it *is* the app's UI layer and these packages are not. They
   > are — `wire-core` ships actions, modals, notifications, infolists, panels and
   > widgets, and the blueprint's stated identity is enterprise-grade UI
   > components with a deliberate Filament/Nova bias. The real difference is
   > narrower: Filament owns the whole app surface and can require exclusivity,
   > while these packages register nothing but asset routes and expect the app to
   > own its own layout. That is a positioning choice, and a reversible one.

   And it turns out to be moot. Filament overrides `DataStore` to intercept the
   *read* of `skipRender`; but a hook can simply **write** it, and Livewire reads
   it once at render — after every call and update in the request:

   ```php
   public function call($method = null, …) {
       return function () { store($this->component)->set('skipRender', true); };
   }
   ```

   Measured, not assumed: renders go 2 → 1 with that hook active
   (`SkipRenderFromHookTest`, which is shipped precisely because this decision now
   rests on it). Emission needs no override either — Filament's own hook ships its
   partials through `$context->addEffect('partials', …)` in `dehydrate()`, a plain
   `ComponentHook` API. **So `wire:partial`'s shape is reachable with no container
   binding and no incompatibility.**
2. **It rides on Livewire internals** (`store($instance)->get('skipRender')`).
   Four times in this migration, island and render internals have behaved
   differently from what the documentation implies, and every one of them was
   visible only in a browser. Owning ~400 lines against them is a standing cost at
   every Livewire release. This is now the *only* argument left against.
3. ~~**The cheaper win is unclaimed.**~~ Claimed — and it turned out to be much
   smaller on the shape that matters than the preview suggested. See below.

**Where this leaves the decision.** Both gating questions are now answered, and
both answers point the same way:

- the measurement says the gap islands cannot close is **an order of magnitude**,
  on the most frequent write there is (49.3 ms / 556 kB against 3.2 ms / 26 kB);
- the binding question, which was the blocking one, **dissolved**: the mechanism
  is reachable from ordinary `ComponentHook` APIs, so nothing has to be claimed
  app-wide and nothing becomes incompatible with Filament.

What is left is a maintenance judgement, not an unknown: ~400 lines of our own
riding on `skipRender` and a response effect, against internals that have
surprised this migration four times. That is a real cost and it is the whole of
the remaining case against. It is also **the user's call rather than a technical
verdict**, so it stays open here with the numbers attached instead of being
decided by whoever writes the next paragraph.

If it is built: behind a flag, editable tables only, and the first test is
`SkipRenderFromHookTest` — which already exists, because the decision rests on
it.

#### 5.4.3b The engine is built; the table's anchors are blocked on a fork

**Landed 2026-08-15**: `InteractsWithPartials::renderPartial()`,
`PartialRenderHook`, `support/partials.js`, and the coverage rule that decides
when a partial response is allowed to stand in for a render. No container
binding, its own effect name, five tests, all gates green. Nothing calls it yet.

**The table integration then hit the repo's own tripwire, and the tripwire is
right.** A row partial needs the row's markup renderable for ONE record, so the
obvious first move is to lift the row body out of the loop into
`partials/body-row.blade.php` and `@include` it. Byte-identical once whitespace
is collapsed — it even removes 37 B per row of inter-row indentation — but
`TableRenderCountTest` went red:

```
8 extra rows → 8 extra view renders   (expected 0)
```

That test exists for exactly this: *"a PR that drops an `@include` back into the
row loop stays green everywhere else and only shows up on a customer's
Debugbar."* An `@include` per row is O(rows) view renders, which is the
anti-pattern the whole Htmlable-first engine was built to remove. Reverted.

So the table integration needs the row body renderable **without** a per-row
`view()->render()`, and there are exactly two ways, each giving up something the
repo currently protects:

1. **Move the row body into PHP** — a `RowRenderer` assembling the pieces that
   are already Htmlable (the compiled `<tr>` tag, the selection cell, the three
   expander shapes, the per-column cell skeletons, `$action->render()`). This is
   where `render-engine-htmlable-first.md` was heading anyway. **What it gives
   up:** the per-record `@if`s in the loop emit Livewire morph markers, and those
   are load-bearing — the file's own comment records that removing one broke the
   column reorder, green on both fuses, caught only by
   `verify-gesture-lab.mjs`. PHP assembly emits no markers.
2. **Keep the loop inline and give the write path its own row view** — one view
   render per write, which is fine, and the loop keeps its markers. **What it
   gives up:** the row's markup then exists in two places, which is the kind of
   drift no test catches until the two disagree.

Both are real work and both trade something. The measurement says the prize is
49.3 ms → 3.2 ms and 556 kB → 26 kB on the most frequent write in the product,
so it is worth doing properly rather than quickly — and worth choosing
deliberately rather than by whichever is easier to type.

**Step one landed 2026-08-15**, and did not go where it was aimed. The cell
commit targets the island — 59 123 B → **42 765 B**, as predicted — through
`support/island.js`, with the island named by the four editable column views and
**not** by wire-core's panel entries, which share the controller and have no
island. Both halves are pinned by tests.

The fill handle was aimed at too, and **must not be**. It suppresses rendering
while a drag is in flight through Livewire's `morph.updating` hook; an island
fragment is morphed by `morphFragment`, which does not go through that hook. A
targeted fill therefore wipes the cells it has just painted. `verify-spa-navigate.mjs`
was the only thing that saw it — `verify-fill-handle.mjs` passed 26/26 with the
bug in place — and the isolation was one run each way: with the island, its two
fill checks fail; without it, 22/22. The option was removed from the controller
rather than left unused: one that must never be set is worse than none.

The general rule, which the remaining steps have to respect: **a controller that
manages its own DOM state across a write must not have a render pushed at it out
of band.** The fill needs no render at all — it reconciles each cell from the
response payload.

And a fourth entry for the tally that bears on the decision above: this is the
third time in this migration that island machinery has behaved differently from
what the documentation implies, and the third time only a browser driver saw it.
`wire:partial` would put ~400 lines on the same internals, one layer deeper.

#### 5.4.4 What phase 1 already bought for this

Nothing in 5.4.2 or 5.4.3 is reachable without the render plan, and two of its
properties are load-bearing here rather than incidental:

- an island body sees no view locals, so the compiled cell skeletons had to move
  into PHP first — they are `Htmlable` (`Skeleton`) on `$plan->columns()->meta`,
  which an island reaches through `$component->tableRenderPlan()`;
- the plan resolves **per group, on first ask**, so a rows island pays for
  `columns()` alone. An eagerly-built plan would put a fixed floor under every
  island and undo the proportionality that is the whole point.

#### 5.4.4a Targeting is automatic, so the boundary *is* the policy

The thing that changes how the rest of this plan should be read, found in
Livewire's JS rather than its docs (`supportIslands.js`, `interceptAction`):

```js
let islandAttr = …find(attr => attr.name.startsWith('wire:island'))
if (islandAttr) { …; return }            // an explicit target wins
let fragment = closestIsland(origin.el)  // otherwise: the nearest island ANCESTOR
if (!fragment) return
action.mergeMetadata({ island: { name: fragment.metadata.name, mode: 'morph' } })
```

**Any action fired from inside an island targets that island, with no attribute
at all.** `wire:island="…"` is for the other case — targeting an island from
outside it, which is exactly what the modal buttons need and why they carry it.

Two consequences worth writing down:

- **Drawing the boundary is the whole design decision.** There is no separate
  step where targeting gets wired up: put the region in an island and every sort,
  page, cell save, sub-row toggle and row action inside it becomes targeted at
  once. So the boundary has to enclose *everything a change inside it can alter* —
  which is why the footer is in, and why the toolbar (which nothing inside can
  change) is out.
- **`wire:island="action-modals"` on a row action is load-bearing, not
  decoration.** Row actions sit inside the data region, so without the attribute
  a modal-opening click would target the region instead and never render the
  modal. It was added for the opposite reason — to target from outside — and now
  also protects against being captured by the enclosing island.

Teleported markup is outside all of this: a modal's own submit button lives under
`<body>` via `x-teleport`, so `closestIsland()` finds nothing and it does a full
render, which is what a write needs.

#### 5.4.5 The prerequisite the first island turned up: islands vs. Htmlable

Wrapping the action modal in an island produced a **500 on the very roundtrip the
island exists to make cheap** — `Undefined variable $__livewire` from
`modals/confirmation.blade.php`. Green in Pest, green in every full render, red
only in a browser. Worth stating plainly, because it governs every island in the
program and not just this one:

- a **full** component render shares the component with the *view factory*
  (`HandleComponents::render()` → `Utils::shareWithViews('__livewire', …)`), so
  every nested view sees it, including one built in PHP with an explicit data
  array;
- a **targeted island** render does not. `Component::renderIslandView()` puts
  `__livewire` into the island body's *own data* instead. That reaches an
  `@include`; it stops dead at the first `view(...)->render()`.

Which is the whole framework. Rule 5 says a modal is an `Htmlable` that owns one
view and builds it with an explicit array — `Modals\Html\Confirmation`,
`SlideOver`, `Modal`, and the same shape for forms and infolists. None of them
inherit the caller's variables, so shared data is the only channel that reaches
them, and `@entangle` compiles to `$__livewire->getId()`. **Every modal in this
framework is unrenderable from inside an island until that scope is restored.**

`Foundation\Support\IslandViewScope` restores it, on Livewire's own
`renderIsland` hook, for exactly the duration of that render and reverted after —
the same two keys the full path shares (`__livewire`, and `_instance` for
`@this`, which `forms/components/checkbox-list.blade.php` still uses, and a
checkbox list inside an action modal is precisely this case).

Two things follow for the rest of the plan:

- **no Pest test can catch this class of bug.** The full render shares the scope,
  so the island path is only ever exercised by a real targeted request. The
  browser drivers are not a nicety for islands — they are the only gate that sees
  the path at all. `IslandViewScopeTest` reaches it by calling
  `renderIslandView()` directly, which is the closest Pest gets;
- **it is a Livewire gap, not a mistake here.** Any app rendering a Blade view
  from PHP inside an island hits it. Worth reporting upstream; the fix here is
  ~10 lines on a public hook and does not wait on that.

The rows island turned up the **mirror image**, and it is the more dangerous of
the two because it does not throw. `Table::toHtml()` — rule 3's
`{{ $table }}` — renders the component's own view straight from PHP, outside
Livewire's pipeline, so nothing shares `$__livewire`. And `@island` compiles to:

```php
if (isset($__livewire)) echo $__livewire->renderIslandDirective(…);
```

Guarded. Missing scope is not an error, it is **silence**: the table rendered its
toolbar, its filter panels, its modals — and not one row. Caught by
`TableHtmlableTest`, which asserts the echo contains a record.

`IslandViewScope::within()` covers that path, and the shape generalises: **the
moment any part of a view moves into an island, every render of that view outside
Livewire's pipeline must borrow the scope or lose that part silently.** The test
pins both halves — bare render missing the body, scoped render carrying it — so
the trap is documented by something that fails rather than by this paragraph.

---

## 6. Open decisions

1. ~~**v3 support window.**~~ **Settled 2026-08-13**: Livewire 4 floors at 2.0.0,
   1.x stays on `^3.0` (§3). Consequently the "two views or a shim" question is
   moot — one view, no shim.
2. **Where this plan is registered.** `v2-master-plan.md` is the authoritative V2
   document and predates v4. Does the framework floor become an explicit V2 gate
   there, and does it land before or after V2.0's data-source work?
3. ~~**Per-row islands** — opt-in for editable tables, or not at all until the
   `islands` memo growth is measured?~~ **Settled 2026-08-15: neither.** They do
   not compile (§4.3 step 5), and the memo growth that worried this question
   cannot happen. The open question that replaces it: **do we adopt
   `wire:partial`** for the per-row win, which is ~300 lines of our own against a
   mechanism Filament runs in production?
4. ~~**wire-sortable's future** (§4.7)~~ — **settled by §5.3**: stay
   self-contained. `wire:sort` passes a single moved item and its index, while the
   server contract wants the whole new order so it can write one `CASE` UPDATE in
   a transaction; delegating means reshaping the server half rather than deleting
   a bundle. Filament reached the same place — their sortable layer is unchanged
   across the Livewire 3→4 boundary and their apps ship SortableJS twice too.
5. **Do we want to claim CSP support?** v4 makes it reachable; it is a real
   differentiator for the enterprise positioning, and it is a test-surface
   commitment, not a config flag.
