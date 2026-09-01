# ADR 0025: Core Module Layers, and Not Splitting Them Into Packages

## Status

ACCEPTED — 2026-08-26.

Amends [ADR 0006](0006-modular-core-extraction-strategy.md) and
[ADR 0007](0007-internal-module-dependencies.md). Neither is superseded: 0006's
extraction recipe and 0007's intent both stand. What changes is the module list,
one row of the matrix, the enforcement mechanism, and the answer to "when".

## Context

`wire-core` is 368 files and 32.5k lines across eleven top-level modules —
`Foundation`, `Core` (the headless engine), `Actions`, `Modals`, `Notifications`,
`Widgets`, `Infolists`, `Panels`, `Audit`, `Exceptions` and, until this change,
a deprecated `Concerns` shim. It is by a wide margin the largest package in the
repository, and every V2 phase adds to it: the frontmatter of the seven
`v2.X-*-implementation.md` plans puts `DataSource`, `Workspace`/`Navigation`,
tenancy, workflow, queue, database notifications, global search and
`DomainModule` there.

The question raised was whether those modules should become their own composer
packages. ADR 0006 already answered "eventually, and here is the recipe"; ADR
0007 already wrote the dependency matrix meant to keep that recipe mechanical.

Two findings decided this differently.

**The matrix had drifted, silently.** It covered four modules of eleven and named
"code review + grep" as its enforcement. At the time of writing there were 23
imports across a boundary the matrix forbids, including a bidirectional
`Actions` ↔ `Modals` cycle — `Modals\Wizard` type-hinted `Actions\ModalStep`
while `Actions\Concerns\HasModal` reached back into `Modals\`. Two composer
packages that require each other at `self.version` cannot be released, so that
one edge alone would have ended an extraction before it started. Nobody had
ignored the matrix. There was nothing that would notice.

**Extraction would not have delivered independence.** Four mechanisms hold the
package together regardless of where the PHP lives:

- `nyoncode/laravel-package-toolkit`'s `hasTranslations()` takes no namespace
  argument (`HasViews::hasViews()` does), so `wire-core::` translations cannot
  leave `packages/core`.
- `config/wire-core.php` is one file, keyed by module — `notifications`,
  `modals`, `audit`, the last of which names `AuditEntry::class` directly.
- `dist/wire-core-dropdown.js` is one 38 KB bundle carrying six Alpine
  controllers, one of which (`wireFillHandle`) has only a wire-table consumer.
  **Done 2026-08-30:** that one is out — `wire-table-fill.js`, 9,230 bytes, and
  the shared bundle is down to 29,235 (−23.8 %). A table pays 100 bytes more in
  total; everyone else pays 9 KB less. See Consequences.
- All four downstream packages require `nyoncode/wire-core` at `self.version`
  and `.github/workflows/split.yml` fires on every tag, so releases are
  lockstep. Seven packages that may only be released together are not seven
  independent packages.

Add to that: `views/partials/component-action.blade.php` emits
`wire:click="callInfolistAction(...)"`, whose only definition is in
`Actions\Concerns\InteractsWithActions`, and `views/actions/modal-host.blade.php`
instantiates `Modals\Html\*` directly. A `wire-core` without Actions or Modals
would not render. Blade compiles lazily, so no Pest run would have said so.

**Measured 2026-09-01, and neither is being paid off.** The modal one was already
ruled not-a-defect: framework views must not depend on `<x-*>`, so instantiating
`Modals\Html\*` is the outcome of the modal sweep, not a lapse. The infolist one
is real but buys nothing, for three reasons found by measuring it:

- The layer test reads PHP `use` statements. Blade is invisible to it, so
  removing the hardcoded call would not move the debt by one.
- The debt that *is* measured runs the other way: `InteractsWithActions`
  (Actions) imports `Infolists\{Entry, RepeatableEntry, Infolist}`. Cutting the
  Blade edge leaves all three.
- The premise was a `wire-core` shipped without Actions, and §1 decided the core
  does not split. There is no such consumer, and ADR 0006's bar for making one
  is still unmet.

Making the host supply the expression — the way a table does, through
`ResolvesActionClick` — would have to thread a resolver through six entry views,
the repeatable entry and the section header, none of which have the host in
scope; they are handed a `$field`. So the boundary stays as it is, and what the
reading found instead is in Consequences.

ADR 0006 itself sets the bar for extraction — "when a real use case arises (e.g.
`wire-infolist` needing Actions without Table)". No such consumer exists.

## Decision

### 1. No new composer packages

The repository stays at five: `wire-core`, `wire-forms`, `wire-table`,
`wire-sortable`, `wire-boost`. `Foundation/` stays in `wire-core`, as ADR 0006
said it always would.

### 2. Layers inside `packages/core/src`, replacing ADR 0007's matrix

| Layer | Modules | May import | May not |
|---|---|---|---|
| **L0** | `Foundation/`, `Exceptions/` | — | everything above |
| **L1** | `Core/` | L0 | L2 |
| **L2** | `Actions/`, `Modals/`, `Notifications/`, `Widgets/`, `Infolists/`, `Panels/`, `Audit/` | L0, L1 | another L2 module |

Files directly in `src/` (`WireCoreServiceProvider.php`, `helpers.php`) are the
composition root and are exempt — wiring the modules together is their job.

Two departures from ADR 0007, both because the code and the plan say so:

- **`Foundation` and `Exceptions` are one layer, not two.** Nine classes under
  `Exceptions/` extend `Foundation\Contracts\WireException` while Foundation
  throws them. Any rule that orders the two is already broken.
- **L2 → L1 is allowed.** ADR 0007 said Actions may depend on Foundation only,
  but `InteractsWithActions` drives `ActionPipeline` and `PluginManager`, and
  V2.4 adds `TransitionAction` over `Core/Workflow` and `Queueable` over a job
  runner. A rule the code has never obeyed and the plan intends to break
  further is not a rule.

### 3. The rule is enforced by a test, not by review

`packages/core/tests/Unit/Architecture/ModuleLayersTest.php` walks every file
under `packages/core/src`, reads its `use NyonCode\WireCore\<Module>\` imports
and checks them against the table above. It carries two lists:

- `permittedCoreEdges()` — deliberate, permanent exceptions, each with its
  reason. Today: `Panels → Infolists` (a panel entry *is* an infolist entry) and
  `Audit → Actions` (the audit trail is surfaced as an action).
- `coreLayerDebt()` — what was left over, each entry a thing to remove.

Both are counted per file and target, so a listed file cannot quietly grow a new
edge, and both are checked for staleness, so an entry cannot outlive its import.
A third assertion caps the size of the debt list itself — without it, a new
forbidden import could be silenced by adding a line to the list.

The lists may shrink. They may not grow without someone editing the test, which
is exactly the conversation that should happen.

### 4. Cross-module behaviour goes through a seam, not an import

When an L2 module needs another, the options in order of preference are: a
contract in `Foundation/Contracts/`, or the soft-resolution seam
`Actions\Concerns\HasLifecycle::resolveNotificationManagerClass()` already uses
— `class_exists()` with a silent fallback, which is what ADR 0007 meant by
container resolution and is why `Actions → Notifications` was never really the
drift it looked like.

Contracts introduced this way so far: `Foundation\Contracts\WizardStep`,
`Foundation\Contracts\WritableStateBag` (with `Foundation\Support\StateWriter`),
`Foundation\Contracts\ActionContract`.

### 5. When to reopen the split

Both must hold:

- **A named consumer** wants module X without module Y — ADR 0006's own bar, not
  an estimate.
- **Readiness:** zero forbidden edges for at least one full release · the JS
  bundle split per module · every extractable module has its own `boot*()` and
  its own Blade prefix (`Widgets`, `Infolists`, `Panels` and `Audit` do not
  today) · a decision on what replaces `self.version`.

The first candidate is then **not** `Notifications`, despite being the smallest
clean leaf today: V2.4 gives it an Eloquent model, a migration and a Livewire
bell. It is `Widgets/`.

## Consequences

- **Good:** the boundary is now a build failure instead of a convention. The
  cycle that would have blocked any future extraction is gone, and Foundation
  imports nothing above itself except one docblock `@param`.
- **Good:** the preparation is the same work extraction would have needed first,
  at roughly a fifth of the cost, and none of it is wasted if the answer changes.
- **Trade-off:** `wire-core` stays large, and a consumer installing `wire-forms`
  still gets `Audit`, `Panels`, `Widgets` and `Infolists` they may never use.
  That is the price of not paying for seven release pipelines to deliver the
  same lockstep.
- **Trade-off:** every future PR that wants to cross a boundary must either
  write a contract or argue for an entry in the list. That friction is the
  point, and it will occasionally be the wrong call for a small change.
- **Good (2026-08-30):** the first item of the "shared JS bundle" readiness
  condition in §5 is met. `wireFillHandle` and its `fill/` modules moved to
  `wire-table`, which was cheap in bytes and revealed the real shape of the
  problem: the bundles are separate IIFEs, so an import that crossed the line had
  to become a published signal. There was one — `support/partials.js` asked the
  fill controller whether a drag was in flight, and skipping that guard is a
  known data-loss bug. The `wire-filling` body class the controller already
  writes for its own CSS, and which two browser drivers already assert, is now
  that seam, with both ends pinned in one test. **The lesson for the modules
  still to split: count the runtime edges, not the bytes.** The bytes were
  trivial to move; the single import was the whole cost.
- **And a second edge, missed on the first pass (found 2026-09-01).** Counting
  the *imports* out of the departing code found one. It did not find what the
  departing code was relying on from the bundle it was leaving: `dropdown.js`
  registers its controllers through the `window.Alpine`-or-`alpine:init` idiom,
  and `wireFillHandle` had been one line inside that registrar. The new entry
  took the line and left the idiom, so the bundle registered nothing on a
  `wire:navigate` hop and Alpine died on the whole data region. Every
  server-side test passed — the script tag is delivered either way — and it took
  `verify-spa-navigate.mjs` to see it. **So the edges to count are not only what
  the code imports, but what the surrounding file was doing for it.** Now pinned
  by `FillHandleAssetTest`, which asserts the idiom's shape in the source and in
  the shipped bundle; mutating the entry back to a bare listener leaves the five
  pre-existing assertions in that file green, which is why they were not enough.
- **The Blade audit found a bug rather than a boundary (2026-09-01).** Reading
  `component-action.blade.php` to classify its coupling showed the button naming
  its Livewire method three times — `wire:click`, the `wire:target` gating
  `wire:loading.attr="disabled"`, and the spinner's own target — and only the
  first carried the arguments. Livewire matches a bare method name against every
  call to it, so one click disabled and span every infolist button on the page;
  on a `RepeatableEntry`, that is one per row, since the action name is identical
  across rows and the index is all that separates them. The same shape was in
  both modal-footer partials, where `modalFooterActions()` is a list. A mutation
  of all three passed 4,581 tests, so nothing had ever asserted it. The
  expression now has one owner per surface —
  `Actions\Support\InfolistActionClickResolver` for the infolist, a local
  variable in each footer partial — which is the same discipline
  `wire-core::actions.button` already had.
- **Cost recorded:** ADR 0006's "extraction is a 30-minute mechanical task" is
  not true today and its pre-extraction checklist is incomplete — it says
  nothing about translations, config keys, the shared JS bundle, or service
  provider boot order, all four of which bind a module to the package more
  tightly than its imports do.

## See also

- [ADR 0006](0006-modular-core-extraction-strategy.md) — the extraction recipe,
  amended by §5 and by the checklist gap above.
- [ADR 0007](0007-internal-module-dependencies.md) — the original matrix,
  amended by §2.
- [ADR 0017](0017-erp-crm-application-architecture.md) — the north star whose
  "UI and execution separated" invariant the L1/L2 split expresses.
- `packages/core/tests/Unit/Architecture/ModuleLayersTest.php` — the rule as it
  actually runs.
