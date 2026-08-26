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
- All four downstream packages require `nyoncode/wire-core` at `self.version`
  and `.github/workflows/split.yml` fires on every tag, so releases are
  lockstep. Seven packages that may only be released together are not seven
  independent packages.

Add to that: `views/partials/component-action.blade.php` emits
`wire:click="callInfolistAction(...)"`, whose only definition is in
`Actions\Concerns\InteractsWithActions`, and `views/actions/modal-host.blade.php`
instantiates `Modals\Html\*` directly. A `wire-core` without Actions or Modals
would not render. Blade compiles lazily, so no Pest run would have said so.

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
