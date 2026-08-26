# Livewire 4 — execution plan for all five packages

Written 2026-08-13, for the `2.0.0` branch. The *why* lives in
[`livewire-4-migration-and-performance.md`](livewire-4-migration-and-performance.md);
this document is the how, and it is meant to be executed top to bottom.

**Scope discipline.** This migration makes the packages *run correctly on
Livewire 4*. It does not adopt islands, `wire:sort`, single-file components,
`.async`, `wire:intersect` or any other v4 ergonomics. Those are separate,
individually-benchmarked phases. Mixing them in would mean a regression has more
than one candidate cause, and the whole value of this plan is that it does not.

---

## 0. What is already verified

This plan is not a prediction. A detached worktree was built at the current
`2.0.0` HEAD, its five packages floored at `^4.0`, and the full gate run there.

| | |
|---|---|
| Livewire | v4.4.0 |
| Laravel | v13.25.0 |
| Testbench | v11.2.0 |
| PHP | 8.4.24 |

Results, unmodified source apart from the constraint bump:

| Suite | Result |
|---|---|
| `wire-core` Unit+Feature | **2024 passed** |
| `wire-forms` Unit+Feature | **1 failed**, 1037 passed |
| `wire-table` Unit+Feature | **2006 passed**, 2 skipped |
| `wire-sortable` Unit+Feature | **94 passed** |
| `wire-boost` Unit+Feature | **218 passed** |
| root `Integration` | **44 passed** (every committed test) |
| 66 CDP browser drivers | **65 passed, 1 failed** (`sortable-morph`, §1.7 — a driver assumption, not a product regression) |

After the changes in §1 landed, the same gate on the branch itself is **66/66
drivers, 5492 tests, 47 integration tests, lint, analyse and coverage all green**
(§3).

So the migration is a handful of precise changes, not a rewrite. Two facts make
that credible rather than lucky:

1. The packages touch a very small part of Livewire's surface — see §1 of the
   analysis document. Almost everything they use is byte-identical across the
   two versions.
2. The one failure and the one API risk found are both *semantic* changes that
   the compatibility table could not have caught, which is exactly why the probe
   was run instead of trusted to reading.

### 0.1 A tooling bug found on the way — **fixed** (`48007ea`)

`scripts/verify-drivers.sh` passed each driver a `CHROME_PORT` but never an
origin, while every driver hardcoded `http://127.0.0.1:8085`. The documented
`PREVIEW_PORT=8086 bash scripts/verify-drivers.sh` therefore started a server on
8086 and then ran 66 drivers against a port with nothing on it — they failed with
`Alpine is not defined` and "not booted", which reads exactly like a framework
regression. It cost an hour here.

Kept out of the migration commits so it would not blur the diff, and landed after
them: the sweep exports `PREVIEW_ORIGIN`, and all 74 drivers resolve their URL as
`PREVIEW_URL ?? ${PREVIEW_ORIGIN ?? 'http://127.0.0.1:8085'}/previews/…`, so a
per-driver `PREVIEW_URL` still wins for driving one driver at an arbitrary URL.
Verified 2026-08-26: `PREVIEW_PORT=8086 CHROME_PORT_BASE=9700 bash
scripts/verify-drivers.sh toasts` starts its own server on 8086 and passes 10/10.

---

## 1. The verified change list

Everything below is a change this migration must make, with the evidence for it.
Nothing else is in scope.

### 1.1 `wire-core` — `CanBeLive::getWireModelModifier()` emits a modifier that changed meaning

`packages/core/src/Foundation/Concerns/CanBeLive.php:44-56` returns `'blur'` for
`liveOnBlur()`, and every forms field interpolates that into
`wire:model.{modifier}` (`text-input.blade.php:8` and fourteen sibling views).

In v4, `.blur` and `.change` control **client-side state sync timing**, not
network timing. `wire:model.blur` no longer means "push to the server on blur" —
the documented v4 equivalent is `wire:model.live.blur`. A field declared
`->liveOnBlur()` would silently stop being live.

**Change:** `getWireModelModifier()` returns `'live.blur'` instead of `'blur'`.

**Why here:** it is the canonical owner of the binding mode for every package.
One line, one place, and the public fluent API (`liveOnBlur()`) is untouched —
consumers keep the method and keep its meaning. That is the whole point of the
concern existing.

**Risk:** low. The composed attribute becomes
`wire:model.live.blur.debounce.500ms` where a debounce is set
(`Field::getDebounceModifier()`), which is the documented v4 order.

### 1.2 `wire-forms` — the multiple-file upload merge now runs twice

The one test failure:
`WithFormsTraitTest > uploading to a multiple field merges the pending upload
with existing paths` — expected 4 entries, got **7**.

Root cause, found by diffing the trait between versions:

```
v3  function _finishUpload($name, $tmpPath, $isMultiple, $append = false)
v4  function _finishUpload($name, $tmpPath, $isMultiple, $append = true)
```

and in the `$append` branch (v4 `SupportFileUploads/WithFileUploads.php:51-58`)
Livewire now merges the incoming uploads onto the existing property value itself
before calling `updateProperty`. Our `InteractsWithFileUploads` was hand-rolling
exactly that merge, capturing the previous value in `updating` and re-merging it
in `updated` — so on v4 the existing three paths are added twice: 3 + (3 + 1) = 7.

This is **not** test-only. The browser calls `_finishUpload(name, paths,
isMultiple)` with three arguments in both versions (verified in both `dist`
bundles), so the PHP default is what applies, and real uploads append too.

**Change:** stop re-merging what Livewire already merged.

**The care this one needs.** The trait does not exist only for the merge — it
exists because `data_set()` cannot write through `StateContainer`'s
`ArrayAccess` (indirect modification), which is why it writes through
`StateContainer::writeInto()`. Under v4 Livewire performs the merge *and* the
write itself. Whether its write lands correctly for an **action-modal host**,
where the field state lives inside a `StateContainer` rather than a plain array
property, is not covered by any existing test: no test in any package combines an
action modal with a file upload. Deleting the merge on the strength of the
standalone-form tests alone would be exactly the kind of change that passes CI
and breaks a consumer.

**So the test comes first, on v3** (§3, step 1), and the change only after it is
green (§3, step 2).

### 1.3 All five packages — the constraint

`"livewire/livewire": "^3.0"` → `"^4.0"` in `core`, `forms`, `table`,
`sortable`, `boost`.

Nothing else in the manifests moves. `illuminate/*` already allows `^12.0|^13.0`
and the probe resolved Laravel 13 without complaint.

### 1.4 `wire-table` — already done

The synthesizer registration (commit `e1b6389`) was the only hard blocker and is
on this branch already, with `TableStateSynthesizerRegistrationTest` pinning it.

### 1.5 Documentation

| File | Line | Says |
|---|---|---|
| `README.md` | 26 | "Livewire 3.x" |
| `docs/getting-started.md` | 61, 171 | "Livewire 3 is installed", "Livewire 3 already ships it" |
| `docs/troubleshooting.md` | 103 | "Livewire 3 already ships" |
| `docs/sortable/overview.md` | 22 | "Livewire 3 compatible" |
| `docs/cs/getting-started.md` | 61, 173 | Czech mirror |
| `docs/cs/troubleshooting.md` | 102 | Czech mirror |
| `docs/cs/sortable/overview.md` | 22 | Czech mirror |

`AI_DOCS_STANDARD.md` is binding: the Czech mirror moves in the same commit.
`docs/forms/overview.md:366` documents `debounce()` as "wire:model.blur with
debounce" and needs the §1.1 change reflected, in both languages.

### 1.6 `wire-boost` guidelines

The install requirement is exactly the kind of fact an agent generating consumer
code needs, and it is about to change. `packages/boost/resources/boost/guidelines`
gets the v4 floor; `composer boost:check-docs` stays green because the mirrored
`docs/` pages move with it.

### 1.7 `sortable-morph` — an Alpine 3.16 change that reads like a regression

The one failing driver, at check 26 of 27: *"a destroyed controller no longer
speaks for the table — 1 nodes visited, 0 in `<tbody>`"*. On v3 the same driver
is 25/25.

It looks like the sortable package's morph guard leaking a destroyed controller.
It is not. Measured on the same fixture, driving `Alpine.destroyTree(root)` then
`Alpine.initTree(root)` by hand in both browsers:

| | Alpine 3.15.12 (LW3) | Alpine 3.16.0 (LW4) |
|---|---|---|
| `destroyTree` clears `_x_dataStack` | 1 → 0 | 1 → 0 |
| `initTree` rebuilds it | 0 → 1 | 0 → 1 |
| controller torn down and rebuilt (`columnSortableInstance`) | true → false → true | true → false → true |
| **scope object identity after re-init** | **a new object** | **the same object, reused** |
| a reference held across the teardown writes to the live controller | no | **yes** |

The product lifecycle is identical: `destroy()` runs, `controllers.delete($root)`
fires, `init()` runs, `controllers.set($root, this)` re-registers, and the map
ends with exactly one entry either way. What changed is that on 3.16 there is no
second object to hold. The driver's simulation — keep a reference, set
`isDragging` on it, assert the morph goes through — now sets `isDragging` on the
*live* controller, which must block. The check was asserting an Alpine 3.15
implementation detail.

**Change:** the driver, not the package. The stale-controller simulation is
replaced by the invariant that survives both: the wrapper is torn down and
rebuilt (stack 1 → 0 → 1, column drag re-wired), exactly one scope answers for
it, and the live controller guards the morph. The replacement asserts the reuse
explicitly, so if a future Alpine goes back to minting a fresh object the check
fails and the original one is worth restoring — `controllers` is keyed by element
for exactly that case.

This is a test-harness change caused by the floor, so it belongs in the same
commit; no shipped code moves.

---

## 2. What makes this safe

Not assertions — mechanics.

1. **The branch is the blast radius.** `2.0.0` is where this lands; `1.x` never
   sees `^4.0`. A consumer on 1.x is unaffected by every step below.
2. **One concern per commit**, each independently revertible, each with its gate
   run before the next starts. The constraint bump is deliberately *last* among
   the code changes, so every behavioural fix is proven on v3 first where it can
   be — and §1.1 and §1.2 are both changes that must hold on v4 only, so they
   ship together with the bump and are proven in the probe worktree beforehand.
3. **The probe worktree stays** for the duration as a canary: any step can be
   applied there first and gated before it touches the branch.
4. **The gates are the repo's own**, in the order `AI_CHANGE_PROTOCOL.md`
   prescribes — narrow owner suite, then downstream, then Integration, then
   lint/analyse, then coverage, then drivers.
5. **Coverage is a hard gate**, not a review note: every line changed must be
   covered, and `scripts/coverage-floors.json` must not drop.
6. **The browser drivers are mandatory here.** Pest reads markup; the morph, the
   Alpine boot and the upload path only exist in a browser. A green Pest run is
   not evidence for this migration.

### 2.1 Rollback

Each step is one commit. `git revert <sha>` restores the previous state without
touching the others; the constraint bump reverts to `^3.0` and the packages run
on v3 again, because no step below introduces v4-only *syntax*. That property is
worth protecting: it is why islands are out of scope here.

---

## 3. Execution

Each step: change → gate → commit. Do not batch.

**Ordering, and why it is not the obvious one.** The instinct is to land the
behavioural fixes first and flip the constraint last, so each fix is proven on
the version we still ship. That is wrong here, and the reason is measurable:

- `wire:model.live.blur` is *behaviourally* identical on v3 — `onBlur` still
  short-circuits the per-keystroke update (v3 `dist/livewire.esm.js:11844-11864`)
  — but `getModifierTail()` filters `live` out of the Alpine binding on v4 and
  **not** on v3 (`:11870-11876` vs v4 `:15480-15483`). Landing §1.1 on v3 would
  emit a stray `x-model.live` for every `liveOnBlur()` field.
- §1.2 removes a merge that v3 still needs. On v3 it is a regression; on v4 it is
  the fix.

Both changes are only correct *with* the floor. Shipping them separately would
mean a commit that is knowingly broken on the version the branch is running —
so they land in the same commit as the constraint, and every commit stays green.
Reverting that commit reverts the floor and its two dependent fixes together,
which is the correct atom.

### Step 1 — an action-modal upload test, on v3, before anything moves — **done** (`5b4fcce`)

The gap identified in §1.2. Written and passing on **v3 first**, so it records
today's behaviour rather than tomorrow's.

- A table host with an action whose modal form carries a `FileUpload::multiple()`,
  uploading into `StateContainer`-backed modal state.
- Assert the merge result and that the pending file is a `TemporaryUploadedFile`,
  mirroring `WithFormsTraitTest`'s standalone assertions.
- Gate: `composer test:table` on v3. This test is the safety net for step 3.

### Step 2 — the floor, with the two changes it requires (all five packages) — **done**

One commit, because none of its parts is correct without the others.

- `"livewire/livewire": "^4.0"` in the five `composer.json` files;
  `composer update livewire/livewire -W`.
- `CanBeLive::getWireModelModifier()`: `'blur'` → `'live.blur'` (§1.1), plus the
  field-attribute tests that assert the old string.
- `InteractsWithFileUploads`: stop re-merging what Livewire already merged
  (§1.2). `updating` no longer captures the previous value and
  `processFileUploadState()` no longer merges. `removeUploadedFile()` and the
  disk-cleanup path are untouched — they have nothing to do with
  `_finishUpload`.
- **Rehearsed in the probe worktree first**, including the step-1 test, before a
  line moves on the branch.
- If the step-1 test fails there, Livewire's own write does not reach the
  `StateContainer`, and the merge must be kept for that host only — a real
  branch in the trait, not a delete. This is the one place the plan forks, and
  the test decides it, not us.
  **It did not fork.** The rehearsal ran the step-1 test unchanged under v4 and it
  passed, so Livewire's own write does reach the container and the delete is
  safe. The trait lost ~100 lines: both hooks, `processFileUploadState()`,
  `containsUploadedFile()`, `$fileUploadStateBeforeUpdate` and an import that
  nothing used any more.
- Gate: the full protocol — `composer test`, root `Integration`,
  `composer lint`, `composer analyse`, `composer coverage:verify`, and
  `npm run verify:drivers` **on port 8085** (§0.1).

### Step 3 — documentation and boost guidelines — **done**

- The table in §1.5, EN and CS in the same commit.
- The v4 floor in `packages/boost/resources/boost/guidelines`.
- Gate: `npm run docs:check`, `npm run docs:standard`, `npm run docs:api`,
  `composer boost:check-docs`.

### Step 4 — the 2.0 upgrade guide — **done**

A consumer-facing page: the framework floor, the `liveOnBlur()` semantics note
(behaviour preserved, but a consumer who hand-wrote `wire:model.blur` in a custom
view must change it), the `/livewire/` → `/livewire-{hash}/` endpoint prefix for
firewall and CDN rules, and the file-upload append change for anyone who
hand-rolled the same merge.

---

## 4. Explicitly out of scope

Islands and the render-plan extraction that gates them; `wire:sort` delegation
and wire-sortable's double SortableJS bundle; single-file components; `.async`
bulk actions; `wire:intersect` lazy loading; `data-loading`; the `$errors` JS
magic; the CSP build. Each is a separate phase in the analysis document, each
with its own benchmark.

Also out of scope, though found here: the `verify-drivers.sh` origin bug (§0.1,
since fixed in `48007ea`), and the duplicated `getDebounceModifier()` between
`core/Foundation/Concerns/HasDebounce` and `forms/Components/Field`.

---

## 5. Residual risks

| Risk | Covered by |
|---|---|
| ~~Livewire's upload write does not reach a `StateContainer`~~ | **resolved** — step 1's test passes unchanged on v4 |
| Parallel `wire:model.live` reorders inline-edit commits (analysis §2.2) | **not covered** — no driver fires two cell commits in one tick. Own follow-up; the failure mode is a conflict branch taken more often, not data loss |
| `smart_wire_keys` shifts the payload budgets | `TablePayloadFuseTest` passed unchanged under v4 in the probe — budgets hold |
| Programmatic `input`/`change` dispatch vs `.self` | all four sites dispatch on the element carrying `wire:model`; the driver sweep exercises the upload and editor paths |
| Consumers on Laravel 12 | probe resolved Laravel 13; `illuminate/*` still allows `^12.0`, and nothing in the change list is version-specific |
