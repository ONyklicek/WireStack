# Audit Playbook

Use this file when the goal is not only to change code, but to analyze the project broadly and surface inconsistencies, regressions, or likely bugs.

Core lesson baked into this playbook (audits 2026-06 → 2026-07): **diff-scoped reading does not find latent bugs or missing-feature gaps.** A shallow-clone bug in `Repeater::getItemSchema()` and a missing `requiresConfirmation()` on `ModalFooterAction` survived six diff audits because the code was internally consistent and all tests were green. Both fall out immediately from matrix checks (see below). Audit by filling matrices, not by reading diffs.

## Audit Modes

Choose the smallest audit that still covers the risk.

### Local Audit

One package or one feature area. Read the relevant package doc plus the relevant seam in `integrations.md`. Additionally: for every class you touch, check its **matrix rows** in `audit-matrices.md` (which parity group is it in, which hosts consume it, which propagation axes does it feed).

### Cross-Package Audit

Behavior crossing package boundaries (actions + forms, tables + forms, tables + core query runtime, sortable + table macros/plugins, Livewire state serialization). Read the package docs plus `integrations.md`, then verify the affected **capability × host** matrix cells.

### Full Repo Audit

For "complete analysis", architecture drift review, or a broad quality sweep. This mode is **matrix-driven**:

1. Read `../CLAUDE.md`, package docs, `integrations.md`.
2. Open `audit-matrices.md`. Re-derive each matrix from current code (do not trust the stored copy — it is a snapshot). New capabilities, hosts, sibling classes, and tree consumers added since the last audit must be added as rows/columns first.
3. Work every cell marked OPEN or UNVERIFIED, plus every cell whose row or column changed since the matrix date.
4. Runtime-verify before reporting (see below).
5. Commit the updated matrices with the audit date.

Do not start by reading every file in the repo. The matrices tell you where to look; package docs, seams, and high-blast-radius files tell you how the code is meant to work.

## The Four Matrix Checks

These are the checks that catch the bug classes diff reading misses. First versions with current data live in `audit-matrices.md`.

### 1. Capability × Host

Every user-facing capability (field actions, select create-option, remote search, live validation, repeaters, wizard, …) crossed with every host context (standalone `WithForms`, table action modal, wizard step, repeater item, `RelationManager`). For each cell verify **both** halves:

- the **render path** exists (the UI affordance appears in that host), and
- the **dispatch path** exists (the wire method the UI calls is composed into that host).

A cell where the UI renders but the host lacks the trait is a guaranteed runtime error (e.g. `Select::createOptionForm()` inside a table action modal renders a "+ Create" button but `WithTable` does not compose `InteractsWithSelectCreation`). A cell where a fluent API exists but the view never forwards it is a silent no-op (e.g. `BelongsToSelect` inheriting `getSearchResultsUsing()` while its blade never passes `remoteSearch`).

### 2. Propagation Axes × Tree Consumers

The schema tree (`LayoutComponent::getSchema()`) has many consumers, and each must descend the same way. Axes: `statePath`, `live`, `livewire` binding, `disabled`, visibility, validation-rule collection, save/state extraction, lookup-by-state-path (field actions, live validation, afterStateUpdated).

Rule of thumb: **when a change makes one consumer recursive, check every other consumer in the matrix.** The Repeater bug pattern was exactly this — validation collection learned to descend into `Grid`/`Section` (2026-06-30) while item state-path assignment did not (fixed 2026-07-02).

Also verify clone semantics: any consumer that clones tree nodes must deep-clone (shared child instances across clones leak per-clone state such as item paths).

### 3. API Parity of Sibling Classes

For every group of sibling classes serving the same concept on different surfaces (Action family, Select family, Export vs Import, chart widgets, columns vs infolist entries), diff the public APIs. Every hole is either:

- **intent** — record it in the matrix with the reason, so the next audit doesn't re-litigate it, or
- **gap** — a finding (missing `requiresConfirmation()` on `ModalFooterAction` was this).

Inherited-but-dead API is the worst variant: a subclass inherits a method whose effect its view/host never wires up. Grep the subclass's blade for every behavior-bearing flag the parent exposes.

### 4. Adversarial Inputs on Persistence & Query Surfaces

Every helper that writes to the database or composes a query gets probed with hostile-but-realistic inputs: empty sets, missing/unmapped keys, colliding column names across joins, `null`s, dotted names, duplicate headers, empty files. The classic: `updateOrCreate([], $data)` from an empty match-key set silently overwrites the first record in the table; `select *` over a pivot join hydrates pivot columns over related columns.

The matrix lists each surface with the probes to run and their last-known status.

## Runtime Verification Is Mandatory

A finding is only reportable as CONFIRMED after a scratch reproduction ran:

- Write a temporary Pest test inside the owning package's test tree (the harness and DB are already wired), `dump()` the observable, run it, then **delete it**.
- Static reading alone yields at most PLAUSIBLE. Both HIGH findings of the 2026-07-02 audit were confirmed this way in under a minute each; one "obvious" bug turned out to have a third, invisible-from-the-diff layer (a stale `resolvedStatePath` shadowing the fix) that only the runtime probe exposed.
- Conversely, when a matrix cell is merely suspicious, a probe test is cheaper than an argument.

Remember the repo-wide gotcha: green tests and 100% line coverage do not imply shape coverage. Check that tests exercise the *nested/wrapped/colliding* variants, not just the flat happy path.

## Audit Order

1. Scope (pick the mode)
2. Matrices (re-derive, fill OPEN/UNVERIFIED cells)
3. Ownership (canonical-owner rules from `../CLAUDE.md`)
4. Runtime seams
5. API/config consistency
6. Rendering consistency
7. State/query/plugin wiring
8. Tests and docs drift
9. Verification commands

## Classic Checks (still apply)

These pre-matrix checks remain valid; run them opportunistically while filling matrices.

### Dependency Direction

`wire-sortable -> wire-table -> wire-forms -> wire-core`. Look for downstream logic creeping upstream, hard imports where a seam should be used, plugin/macro behavior in the wrong package.

### Service Provider And Boot Consistency

Blade namespaces vs actual views, macros expected by runtime but not booted, synthesizers/plugins registered in one path but bypassed elsewhere, dead config keys. Key files: the four `Wire*ServiceProvider.php`.

### Fluent API Versus Runtime Behavior

Config option exists but is not consumed; runtime expects state the builder never creates; docs mention methods missing in code; defaults differ between builder and runtime. Important areas: forms `Form/ConfigBuilder/FormConfig/FormRuntime/SaveHandler`, table `Table/WithTable/TableQueryService`, sortable provider + `SortableTable`/`WithSortable`.

### Rendering And View Wiring

Partial names vs callers, runtime states missing view branches, modal/action/filter UI diverging from PHP config, previews vs current API. Areas: `packages/*/resources/views/`, `workbench/`.

### State, Serialization, And Hydration

State surviving round trips, default vs hydrated shape, nullable drift, dirty tracking of nested changes. Areas: `TableStateSynthesizer`, `WithTable`, forms `StateManager`, `core/src/Core/State/`. Writing into `StateContainer` bags must go through `StateContainer::writeInto()` — a plain `data_set()` silently drops the write.

### Query, Filter, And Metadata Translation

Filter modes ignored by translation, sort/search options missing in conversion, relation/accessor assumptions, export path vs render path. Areas: `TableQueryService`, `Columns/`, `Filters/`, `core/src/Core/Query/`, `core/src/Core/Metadata/`.

### Plugin And Macro Wiring

Plugin registered but not booted, macro exposed but not consumed, flags no config path sets. Areas: `PluginManager`, sortable provider + `SortablePlugin`, `Table.php`.

### Tests Versus Behavior, Docs Drift

Public API changes without tests, seams with only unit coverage, docs/previews referencing removed APIs, new features with zero docs (check `docs/` for every row added to the matrices). Boost guidelines (`packages/boost/resources/boost/`) count as docs — a stop hook enforces sync.

## Package-Specific Audit Focus

- **Core**: provider registration drift, Blade namespace consistency, action/modal/notification contract mismatches, plugin lifecycle.
- **Forms**: config vs runtime drift, save lifecycle ordering, field API vs rendered markup, action/form integration.
- **Table**: `Table.php` vs `WithTable.php` drift, query/filter/sort translation gaps, synthesizer mismatches, column/filter classes vs partials.
- **Sortable**: provider macro registration, plugin boot lifecycle, reorder flags vs runtime checks, persisted order vs UI.

## Useful Commands

```bash
composer analyse
composer test:core && composer test:forms && composer test:table && composer test:sortable
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
npm run docs:changed -- --dry-run
```

Search patterns:

```bash
rg "macro\(" packages/
rg "registerPropertySynthesizer|PluginManager|ActionMacros" packages/
rg "TODO|FIXME|deprecated|@deprecated" packages/ docs/ architecture/
# Parity check helper: public methods of two siblings
rg "public function" packages/core/src/Actions/Action.php packages/core/src/Actions/ModalFooterAction.php
# Tree consumers: who iterates the schema?
rg "getSchema\(\)" packages/*/src --files-with-matches
```

## Reporting Format

1. Findings first, ordered by severity, each with concrete file references and a one-line failure scenario.
2. Mark each finding CONFIRMED (runtime-reproduced) or PLAUSIBLE (static only).
3. State why the behavior is inconsistent or risky.
4. Note what verification ran and what did not (coverage enforce, DB matrix, …).
5. Update `audit-matrices.md` cells and date.

```text
Findings
- High: ... (CONFIRMED, repro: <one line>)
- Medium: ...
- Low: ...

Open questions
- ...

Verification
- Ran ...
- Did not run ...

Matrices
- Updated cells: ...
```

If no issues are found, state that explicitly, mention residual risk and test gaps, and still bump the matrix date.
