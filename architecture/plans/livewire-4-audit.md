# Livewire 4 — post-migration audit

Written 2026-08-14, on `2.0.0` at `4e99925`, against **Livewire v4.4.0** as
installed in `vendor/`. This is the audit of the migration that
[`livewire-4-execution-plan.md`](livewire-4-execution-plan.md) executed, plus a
survey of the v4 surface the packages do **not** yet use.

Method, same as its predecessors: every claim about Livewire 4 below is read out
of the installed `vendor/livewire/livewire` tree and quoted with a file:line.
Numbers are measured on this machine today, not carried over from the plan.

**Constraint on every recommendation here:** existing behaviour, appearance and
public API are preserved. Nothing below asks a consumer to change a line of their
code. Where an item cannot honour that, it says so and is not recommended.

---

## 0. Verdict

**The migration is correct and complete in code.** The four gates I re-ran are
green, and the two changes the plan called load-bearing — the `wire:model`
modifier and the file-upload merge — I re-derived from the v4 parser rather than
trusting the plan, and both hold (§2).

What is left is **not** a broken migration. It is:

- **residual v3 claims in prose**, in the highest-visibility place there is: the
  four per-package `README.md` files that Packagist renders (§3.1);
- **two follow-ups the plan itself filed and nobody closed** (§3.6, §3.7);
- **one gate the plan's own phase table listed and that was never written** — the
  concurrent-commit driver (§3.4);
- **the entire v4 performance surface, still unused**, because it is gated behind
  a refactor that has not started (§4).

The headline for §4: the packages currently use **none** of the v4 features that
motivated the move. Islands, `.async`, `data-loading`, `wire:sort`,
`wire:intersect`, `#[Renderless]` — zero adoption. That is exactly what the plan
intended (it scoped itself to "run correctly on v4"), so this is a statement of
where the work stands, not a criticism of it.

---

## 1. Gates re-run today

| Gate | Command | Result |
|---|---|---|
| Full suite | `composer test` | **5492 passed, 2 skipped** (15 275 assertions, 106 s) |
| Static analysis | `composer analyse` | **0 errors** (612 files) |
| Lint | `composer lint` | **passed** |
| Browser | `npm run verify:drivers` | see §1.1 |

### 1.1 Browser gate

**All 66 drivers passed** — `npm run verify:drivers`, ~17 minutes, exit 0.

This is the gate that matters for this migration: Pest reads markup, and the
morph, the Alpine boot and the upload path only exist in a browser. Notably
`sortable-morph` is **25/25**, i.e. the Alpine 3.16 scope-reuse rewrite from plan
§1.7 holds, and `selection-gestures` is 77/77.

Three drivers (`select-dedup`, `swipe`, `tiptap-split`) report "ran, no summary
(exit=0)" — they pre-date the shared `checker()` helper and signal only through
their exit code. Not a v4 issue; worth folding onto `lib/cdp.mjs` eventually so
the sweep can count their checks too.

---

## 2. The two load-bearing changes, re-derived from the v4 source

The plan asserted these. I did not take them on trust, because both are the kind
of change that passes every PHP test either way.

### 2.1 `wire:model` — the modifier split, and why `live.blur` is right

v4's `directive("model", …)` (`dist/livewire.esm.js:15395-15478`) splits the
modifier list **at `live`**:

```js
let liveIndex = modifiers.indexOf("live");
let isLive = liveIndex !== -1;
let shouldSendNetwork = isLive || hasLazyWithoutLive;
let ephemeralModifiers = isLive && !hasLazyWithoutLive ? modifiers.slice(0, liveIndex) : modifiers.slice();
let networkModifiers  = isLive && !hasLazyWithoutLive ? modifiers.slice(liveIndex + 1) : [];
```

Everything **before** `live` times the client-side sync; everything **after** it
times the network commit. Tracing the two strings that matter:

| Attribute | `shouldSendNetwork` | Network binding | Verdict |
|---|---|---|---|
| `wire:model.blur` (v3 output) | **`false`** | none | **never reaches the server** |
| `wire:model.live.blur.debounce.500ms` (v4 output) | `true` | `@blur → update()` | correct |

So the §1.1 change was not a precaution — on v4 the old string is silently inert,
and `liveOnBlur()` would have stopped being live with no error anywhere. The fix
in `CanBeLive::getWireModelModifier()` is confirmed correct, and `liveOnBlur()`
keeps its meaning for consumers.

**One wrinkle worth documenting.** In that same block the `@blur` binding calls
`update()`, *not* `debouncedUpdate()` (`:15448-15458`). A field declared
`->liveOnBlur()->debounce(500)` therefore emits
`wire:model.live.blur.debounce.500ms` and the debounce is **inert** — the commit
fires on blur immediately. This is harmless (blur is not a rapid-fire event) and
it is not a regression, but `docs/forms/overview.md:366` documents `debounce()`
in terms of blur, so the pairing reads as if it does something it does not. A
sentence in the docs, not a code change.

### 2.2 `.self` is forced onto every `x-model`

Same block, `:15417-15421`: unless `deep` is present, `self` is **always** pushed
onto the ephemeral modifiers. The plan flagged four sites that push values by
dispatching a DOM event and said the read "belongs in the driver gate, not in a
confident claim". I read all four to the element:

| Site | Dispatches on | Carries `wire:model` | OK |
|---|---|---|---|
| `forms/…/file-upload.blade.php:56` | `$refs.fileInput` | `:127` — same element | ✅ |
| `forms/…/rich-editor.blade.php:42` | `$refs.textarea` | `:276` — same element | ✅ |
| `forms/resources/js/tiptap-editor.js:110` | `$refs.hiddenInput` | `tiptap-editor.blade.php:180-181` — same element | ✅ |
| `forms/resources/js/image-processor.js:303` | `this.$refs.fileInput` | same ref as the upload input | ✅ |

`event.target === el` holds in all four, so `.self` is satisfied. Two of them are
file inputs, which take the `handleFileUpload()` early return at `:15406` and
never reach the `.self` logic at all.

**This risk is now closed by reading.** A driver would still be better evidence,
but it is no longer an open question with a guess attached to it.

---

## 3. Findings — what the migration left behind

Ordered by how likely a user is to hit it.

### 3.1 Four package READMEs still require "Livewire 3.x" — **the visible one**

The plan's documentation sweep (§1.5) listed the root `README.md` and the `docs/`
tree. It missed the per-package READMEs, which `.github/workflows/split.yml`
publishes to the standalone repositories — i.e. **these are the front page on
Packagist for each package**.

| File | Line | Says |
|---|---|---|
| `packages/core/README.md` | 9 | `- Livewire 3.x` |
| `packages/core/README.md` | 42 | "Alpine.js (included via Livewire 3)" |
| `packages/forms/README.md` | 9 | `- Livewire 3.x` |
| `packages/table/README.md` | 26 | `- Livewire 3.x` |
| `packages/table/README.md` | 95 | "Livewire 3 includes Alpine.js automatically" |
| `packages/sortable/README.md` | 9 | `- Livewire 3.x` |

Root `README.md:26` already says `Livewire 4.x`, so the repo currently
contradicts itself. `composer.json` floors at `^4.0` in all five packages, so a
user who believes the README installs a package that will not resolve.

**Fix:** six lines. No API, appearance or behaviour implication.

### 3.2 Two docs pages still cite the old Livewire endpoint

`docs/upgrade.md:76-77` correctly documents the v4 move to `/livewire-{hash}/…`.
But two pages still name the old concrete path as a live example:

| File | Line |
|---|---|
| `docs/getting-started.md` | 253 — "breaks Livewire's own `/livewire/livewire.js`" |
| `docs/troubleshooting.md` | 158 — "the same block 404s Livewire's own `/livewire/livewire.js`" |
| `docs/cs/getting-started.md` | 256 — Czech mirror |
| `docs/cs/troubleshooting.md` | 159 — Czech mirror |

The *argument* both passages make (a `try_files` block breaks route-served
assets, which is why the packages ship a real file) is still exactly right. Only
the path is stale. Per `AI_DOCS_STANDARD.md` the EN/CS pairs move together.

Confirmed empirically rather than from the upgrade guide — the preview server's
own request log during today's driver run:

```
2026-08-14 01:09:51 /livewire-5d8e1895/livewire.js ............... ~ 0.18ms
```

The hash derives from `APP_KEY`, so it differs per application. The docs should
name the shape, not a hash.

### 3.3 The payload fuse still carries only v3's numbers — now measured

Plan §2.3 required: "re-measure deliberately, record the v4 numbers next to the
v3 ones, do not just bump the numbers." The test passes, so nobody re-measured.
Its comments still read "Measured 2026-08-08", which is v3's compiler output.

I measured it under v4.4.0 (by temporarily tightening each budget so the
assertion reports its actual value, then restoring the file):

| Budget | v3 (2026-08-08) | **v4.4.0 (today)** | Budget | Headroom |
|---|---|---|---|---|
| bytes / row | 1823 | **1826.75** | `< 1900` | 73 B |
| whitespace runs / row | 11 | **11** | `≤ 11` | **0** |
| morph markers / row | 16 | **16** | `≤ 16` | **0** |

**`smart_wire_keys` did not inflate the row.** That is the predicted outcome for
the reason the analysis gave — the body row loop is assembled in PHP
(`index.blade.php:1129-1160`), not by a `@foreach`, so the v4 key compiler has
nothing to inject into. The +3.75 B is drift, not a key.

Worth recording in the test's comments: two of the three budgets sit **exactly**
on the measurement, so the next legitimate change to the row trips them, and the
person who trips them should find v4 numbers there rather than v3 ones.

### 3.4 The concurrent-commit driver was never written — **now written** (§5.1)

`livewire-4-migration-and-performance.md` §5 lists Phase 2's gate as "`composer
test`, `npm run verify:drivers`, **new concurrent-commit driver (§2.2)**". There
are 66 drivers in `workbench/scripts/` and none of them fires two cell commits in
one tick.

This is the one *behavioural* risk of the migration that remains genuinely
uncovered: on v4 `wire:model.live` commits run in parallel, so two inline-edit
cells committing near-simultaneously can each carry a version resolved before the
other's `wire-editable-committed` broadcast. The failure mode is the conflict
branch being taken more often than on v3 — not data loss, since that is what the
optimistic lock is for — but the conflict branch is the least-exercised path in
the editable cell.

The plan's own residual-risk table already says "not covered". It was the
highest-value single piece of work in this list — and writing it immediately
turned up §3.4a, which is why it was.

### 3.4a What the new driver found — a false conflict on same-tick sibling edits

Writing §3.4's driver turned up a real product gap, and it is not the one the
analysis document predicted.

The prediction was that v4's parallel requests would let two cells carry versions
resolved before each other's broadcast. What actually happens is simpler and
worse: **two same-tick commits are not two requests at all.** Livewire bundles
them into one (`requests: 1, maxInFlight: 1`, measured by patching `fetch` in the
driver) and runs them in order on the server. So:

1. the first write moves the record's `updated_at`;
2. the second arrives in the *same request*, still carrying the version read at
   render time;
3. `RecordVersion::conflicts()` sees a mismatch and refuses it as
   *"Record was modified by another user"* — same user, same request, one call
   earlier.

`wire-editable-committed` cannot cover this. It is dispatched from the commit's
**response** handler (`dropdown.js:697`), so it fires long after a sibling in the
same request was serialised into the payload. The mechanism exists precisely to
stop this ("so the next field edited on the same record does not falsely
conflict", `dropdown.js:694-696`) and structurally cannot.

**This is not a v4 regression.** v3 squashed same-tick calls into a single commit
inside its 5 ms buffer too, so it behaves identically there. The migration merely
made it worth looking at.

The user-visible effect: tab out of one cell straight into another on the same
row, and the second edit is rejected with a misleading message. Nothing is
silently lost — the cell reverts, shows the error, adopts the server version and
can immediately be re-saved — so this is a UX defect, not a data-integrity one.

Fixes worth weighing, none of them in this audit's scope:

- have the server return the *fresh* stamp per call and let the pipeline treat a
  version that matches "the value this same request just wrote" as non-conflicting;
- or resolve the version server-side per record rather than trusting the render-time
  stamp when the caller is the same component instance;
- or give `updated_at` sub-second precision, which narrows but does not close it.

Pinned by `workbench/scripts/verify-concurrent-commits.mjs` (17/17), which asserts
the safety properties rather than blessing the behaviour — see its header.

### 3.5 Three tests are written but uncommitted

```
?? packages/forms/tests/Feature/ModalHostFooterTest.php
?? packages/table/tests/Feature/ActionModalFooterTest.php
?? tests/Integration/BladeDirectiveIntegrityTest.php
```

They pass (they ran inside today's 5492). They are untracked, so **CI does not
have them**.

`BladeDirectiveIntegrityTest` deserves a specific note: it compiles every shipped
Blade view and fails on any directive-shaped token surviving into the markup. It
was written for the `@elseunless` bug in 1.17.2 — but it is precisely the guard
the islands work in §4.1 will need, because a mistyped or unavailable `@island`
does not error, it renders as text. Committing it is a prerequisite for that
work, not just hygiene.

### 3.6 `verify-drivers.sh` still cannot honour `PREVIEW_PORT`

Filed in plan §0.1 as a follow-up; still open. `scripts/verify-drivers.sh:32`
accepts `PREVIEW_PORT`, `:78` starts the server on it, and `:144` runs each
driver with only `CHROME_PORT="$port" node "$f"` — no `PREVIEW_URL`, while every
driver hardcodes `http://127.0.0.1:8085`. The usage line the script prints at
`:21` (`PREVIEW_PORT=8086 bash scripts/verify-drivers.sh`) therefore starts a
server nothing talks to, and all 66 drivers fail with "Alpine is not defined",
which reads exactly like a framework regression.

It cost an hour during the migration. It will do it again to the next person.

### 3.7 `getDebounceModifier()` is still duplicated

`packages/core/src/Foundation/Concerns/HasDebounce.php:30` and
`packages/forms/src/Components/Field.php:99`. Flagged as out-of-scope in plan §4;
still open, and it is a direct violation of the canonical-owner invariant in
`CLAUDE.md` — core owns the concern, forms should delegate. It also now composes
with §2.1's modifier string, so the two want to be read together.

### 3.8 Cosmetic

`packages/forms/src/Forms/WithForms.php:51` — comment reads "Livewire 3 lifecycle
hook"; the hook is unchanged in v4, only the comment is dated.

---

## 4. Findings — the v4 surface the packages do not use

Adoption today is **zero**. Each item below is assessed against the brief:
preserve behaviour, appearance and public API.

### 4.1 Islands — the whole performance case, still blocked

Confirmed from the installed source. `HandlesIslands::renderIslandView()`
(`vendor/…/SupportIslands/HandlesIslands.php:184-207`) builds the island's scope
as:

```php
$properties = Utils::getPublicPropertiesDefinedOnSubclass($this);
$scope = array_merge(['__livewire' => $this], $properties);
$view->with($scope);
$view->with($data);          // only the directive's own with:
```

— the component's public properties and the explicit `with:`, and **nothing
else**. A non-targeted island then returns `renderSkippedIsland()` with `mode:
skip` and empty content (`:97-105`): not rendered, not sent, not morphed.

Against that, `packages/table/resources/views/tables/index.blade.php`:

| | |
|---|---|
| Total lines | **1539** |
| Head `@php` block | lines **1–322** |
| Locals assigned in it | **102** |

An island body sees none of those 102. **So islands remain gated behind the
`TableRenderPlan` extraction**, which is Phase 1 of the analysis document and
**has not been started** — no `*RenderPlan*` file exists in the repo.

This is unchanged from the plan's assessment; I am confirming it still holds
against 4.4.0 rather than 4.0, and that the prerequisite has not moved.

**API/appearance impact: none.** Islands are internal to the shipped view;
`Table::…` stays byte-identical for consumers. That is what makes this the right
next investment — the largest measured win with the smallest consumer-facing
surface.

### 4.2 CSP — recommend **not** claiming it (open decision #5, answered with a number)

v4 makes it reachable: `dist/livewire.csp.js` ships, and `config/livewire.php:263`
carries `'csp_safe' => false`. I checked what the build actually changes:

| | `livewire.esm.js` | `livewire.csp.esm.js` |
|---|---|---|
| `new Function` occurrences | **1** | **0** |
| evaluator | Alpine's default | `cspEvaluator` / `cspRawEvaluator` |

The CSP build swaps Alpine's evaluator for a restricted one — property access and
simple calls, not arbitrary JavaScript. The packages' views are heavy users of
exactly what it forbids:

| Attribute | Count in `packages/*/resources/views/` |
|---|---|
| `x-show=` | 92 |
| `x-data=` | 61 |
| `x-text=` | 44 |
| `x-model=` | 12 |
| `x-on:click=` | 10 |
| `x-init=` | 6 |
| `x-on:change=` | 2 |
| `x-bind:disabled=` | 1 |
| **total** | **~228** |

And they are not all trivial. `index.blade.php:342` is a statement block:

```blade
x-intersect.once="if (!loaded) { loaded = true; $wire.loadTable(); }"
```

That cannot be expressed in a CSP evaluator without being rewritten into a
component method.

**Recommendation: do not claim CSP support.** It is ~228 expression sites plus a
driver run to prove it, it touches every view in the repo, and it is the one item
in this list with a real risk of changing appearance by accident. If the
enterprise positioning eventually needs it, it is its own project with its own
plan — not a flag.

### 4.3 `wire:sort` — SortableJS genuinely ships twice (open decision #4)

Verified by occurrence count, not line count (both bundles are minified, so
`grep -c` lies here):

| Bundle | `Sortable` occurrences | Size |
|---|---|---|
| `packages/sortable/dist/wire-sortable.js` | 37 | **43 705 B** |
| `vendor/…/livewire/dist/livewire.js` | 129 (+ the `sortablejs` source banner) | — |

`packages/sortable/resources/js/sortable.js:16` is a plain
`import Sortable from 'sortablejs'`, so our 43.7 kB is very nearly all library. On
v4 an app ships it twice.

The analysis document's recommendation still reads correctly to me: keep the
package's **server** contract (reorder pipeline, gap handling, scoped reordering,
the `wire-table` integration — that is where its value is) and make the **client**
half pluggable. It would also retire the `morph.updating` / `morph.updated` skip
dance at `sortable.js:51-55`, which exists only because our controller and
Livewire's morph do not know about each other.

**API impact: none if the server contract is kept.** Appearance: needs a driver
run, since drag feedback is the framework's rather than ours.

### 4.4 Smaller replacements, all API-neutral

| We hand-roll | v4 offers | Site | Note |
|---|---|---|---|
| `x-data="{loaded:false}"` + `x-intersect.once` | `wire:intersect` — real, `dist/livewire.esm.js:14968-14990`: it forwards `wire:intersect<modifiers>` to `x-intersect<modifiers>` and evaluates the expression as a Livewire action | `index.blade.php:341-342` | drops a wrapper and its state. (Note the bare `directive("intersect")` in the bundle is Alpine's own plugin at `:5848` — not this) |
| `skipRender()` juggling behind a `function_exists('Livewire\store')` guard | `#[Renderless]` / `.renderless` | `WithTable.php:565-566, 593-594` | removes the guard and the store poke |
| 15 `wire:loading.attr`, 15 `wire:loading`, 9 `wire:loading.remove`, 32 `wire:target` | automatic `data-loading` attribute | across the views | CSS instead of markup — a payload win on wide tables, but **appearance-sensitive**: the CSS must reproduce today's states exactly |
| forms error display round-trips | `$errors` JS magic / `wire:text` | forms views | client-side display only |

`wire:confirm` is the one v4-era directive already in use
(`core/…/modal-host-footer-action.blade.php:17`,
`table/…/modal-footer-actions.blade.php:22`).

### 4.5 `.async` — thinner than it looks

The plan cites bulk actions and exports as the `.async` candidates. Scanning for
the long-running calls, the only one that surfaces in `WithTable` is:

```
packages/table/src/Concerns/WithTable.php:2402
    public function exportTable(string $format = 'csv'): StreamedResponse
```

A `StreamedResponse` is a download, and an async call is precisely the one that
does not return a normal response to the browser — so **exports are probably not
an `.async` candidate**, and the analysis document's "export / bulk delete" example
is half wrong.

The other half holds. The bulk-action entry points are side-effect-only:

```
packages/table/src/Concerns/InteractsWithTableModals.php:32
    public function executeBulkAction(string $actionName, bool $confirmed = false): void
packages/table/src/Concerns/InteractsWithTableModals.php:284
    public function executeBulkActionWithData(string $actionName, array $data = [], bool $confirmed = false): void
```

Both return `void`, which is exactly the shape `.async` wants — nothing is waiting
on a response. So the "long bulk delete freezes the whole table" case is real and
addressable; the "export" case is not, and should be dropped from the phase before
someone tries to implement it.

Caveat before promising it: a consumer's bulk action may redirect or return a
download of its own from inside the callback, so this wants a per-action opt-in
(`->async()` on the action), not a blanket flag. That also keeps it API-additive.

### 4.6 Confirmed clean — no v4 deprecation is being relied on

So a future audit does not redo this:

- All three morph hooks we use (`morph.updating`, `morph.updated`, `morphed`) are
  present in v4's dist, and the bundle emits **no** deprecation strings at all.
- Not used anywhere: the deprecated `commit` / `request` JS hooks, `$wire.$js()`,
  `$this->stream()`, `wire:transition`, unclosed `<livewire:x>` tags.
- No hardcoded `wire:model.blur|change|lazy|defer` anywhere in the packages —
  every binding composes through `CanBeLive`. (The four textual hits are prose in
  the upgrade guides.)
- Version windows agree: Livewire 4.4.0 allows `illuminate/* ^10–^13` and
  `php ^8.1`; the packages require `^12.0|^13.0` and `php ^8.2`. The
  `tests.yml` matrix (Laravel `12.*`, `13.*`) is inside both.
- v4's richer interceptor API (`Livewire.intercept*`) is the replacement for the
  deprecated hooks — since we never used those, there is nothing to migrate.

---

## 5. Recommended order

### 5.1 Done — the seven cheap closures (2026-08-14)

All seven landed after the audit was written, each gated:

| # | Item | What changed |
|---|---|---|
| 1 | §3.1 package READMEs | Livewire 3.x → 4.x in `core`/`forms`/`table`/`sortable`, plus the two "Alpine via Livewire 3" asides. The **Laravel** line was wrong too — all four said "Laravel 10, 11, or 12" against a `^12.0\|^13.0` manifest; now `12.61+ or 13.12+`, matching the root README and the toolkit's real floor |
| 2 | §3.5 the three tests | committed, so CI has them |
| 3 | §3.2 endpoint paths | `/livewire/livewire.js` → `/livewire-{hash}/livewire.js`, EN+CS together |
| 4 | §3.3 fuse numbers | v4 measurements recorded beside the v3 ones, with the reason `smart_wire_keys` did not move them and a note that two budgets have zero headroom |
| 5 | §3.6 `PREVIEW_PORT` | drivers now fall back through `PREVIEW_ORIGIN`, exported by the sweep. Verified both ways: `PREVIEW_PORT=8087 … toasts` passes (it could not before) and the default path still passes |
| 6 | §3.4 concurrent-commit driver | `verify-concurrent-commits.mjs`, 17/17 — and it found §3.4a |
| 7 | §3.7 `getDebounceModifier()` | the modifier's shape and precedence now live only in core's `HasDebounce`; `Field` keeps its `$defaultLiveDebounce` property and overrides one small hook. `TextFilter`, which takes the debounce without `CanBeLive`, is unaffected |

Gates for the batch: `composer test` **5492 passed / 2 skipped**, Integration
**47 passed**, `composer analyse` **0 errors**, `composer lint` passed,
`composer coverage:verify` **OK** (core 95.5 / forms 95.9 / table 90.3 / sortable
96.3 / boost 100, no floor moved), and the full driver sweep re-run after the
mechanical edit to all 66 driver files.

### 5.2 Next — the work that actually needs deciding

1. **§3.4a the false conflict** — new, and the only user-visible defect in this
   document. Not a migration item; it wants a decision on which of the three
   fixes to take.
2. **`TableRenderPlan` extraction** (Phase 1) — v3-safe, independently
   measurable, kills the documented magic-property hazard at
   `index.blade.php:24-26`, and gates everything below.
3. **Islands**, seams 1–4.
4. **`wire:intersect`, `#[Renderless]`, `data-loading`** — small, API-neutral.
5. **`wire:sort` delegation** — decide #4 first.

Not recommended: **CSP** (§4.2).

---

## 6. What this audit did not do

- It did not run the benchmarks. §4's sizing is the analysis document's
  arithmetic over its own measurements, not re-measured here.
- It did not open a browser by hand beyond `verify:drivers`.
- It did not audit `wire-boost`'s generated guidelines against the v4 floor
  beyond confirming `composer boost:check-docs` is wired to the docs that moved.
