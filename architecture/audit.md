# Audit Playbook

Use this file when the goal is not only to change code, but to analyze the project broadly and surface inconsistencies, regressions, or likely bugs.

## Audit Modes

Choose the smallest audit that still covers the risk.

### Local Audit

Use for one package or one feature area.

Read:

- relevant package doc
- relevant seam in `integrations.md`

### Cross-Package Audit

Use when behavior crosses package boundaries, especially:

- actions + forms
- tables + forms
- tables + core query runtime
- sortable + table macros/plugins
- Livewire state serialization

Read:

- relevant package docs
- `integrations.md`

### Full Repo Audit

Use when the request is "complete analysis", architecture drift review, or a broad quality sweep.

Read:

- `../CLAUDE.md`
- `README.md`
- package docs
- `integrations.md`
- only the relevant ADRs if intent is still unclear

Do not start by reading every file in the repo. Start from package docs, seams, high-blast-radius files, and tests.

## Audit Order

1. Scope
2. Ownership
3. Runtime seams
4. API/config consistency
5. Rendering consistency
6. State/query/plugin wiring
7. Tests and docs drift
8. Verification commands

## What To Check

### 1. Dependency Direction

Check that package responsibilities still flow in the intended direction:

```text
wire-sortable -> wire-table -> wire-forms -> wire-core
```

Look for:

- downstream package logic creeping into upstream packages
- hard imports where a seam should be used instead
- plugin/macro behavior implemented in the wrong package

### 2. Service Provider And Boot Consistency

Check that registration and boot behavior matches package intent.

Look for:

- Blade namespace registered but views/components missing
- macros expected by runtime but not booted
- synthesizers/plugins registered in one path but bypassed elsewhere
- config-driven behavior with missing defaults or dead config keys

Key files:

- `packages/core/src/WireCoreServiceProvider.php`
- `packages/forms/src/WireFormsServiceProvider.php`
- `packages/table/src/WireTableServiceProvider.php`
- `packages/sortable/src/WireSortableServiceProvider.php`

### 3. Fluent API Versus Runtime Behavior

Check that fluent config methods actually affect runtime execution.

Look for:

- config option exists but is not consumed
- runtime expects state that builder never creates
- docs/examples mention methods missing in code
- default values differ between builder and runtime

Important areas:

- forms:
  `Form.php`, `ConfigBuilder.php`, `FormConfig.php`, `FormRuntime.php`, `SaveHandler.php`
- table:
  `Table.php`, `WithTable.php`, `TableQueryService.php`
- sortable:
  `WireSortableServiceProvider.php`, `SortableTable.php`, `WithSortable.php`

### 4. Rendering And View Wiring

Check class-to-view and partial-to-runtime consistency.

Look for:

- Blade partial names that no longer match callers
- new runtime states with missing view branches
- modal/action/filter UI code diverging from PHP config/state
- docs preview pages not matching current component API

Important areas:

- `packages/*/resources/views/`
- `workbench/resources/views/`
- `workbench/app/Livewire/Previews/`

### 5. State, Serialization, And Hydration

Check that runtime state survives round trips consistently.

Look for:

- table state mismatches across requests
- default state shape differing from hydrated state shape
- nullable versus non-nullable drift
- dirty tracking that misses nested changes
- runtime state keys that do not match Blade bindings

Important areas:

- `packages/table/src/Livewire/TableStateSynthesizer.php`
- `packages/table/src/Concerns/WithTable.php`
- `packages/forms/src/Forms/Runtime/StateManager.php`
- `packages/core/src/Core/State/`

### 6. Query, Filter, And Metadata Translation

Check that user-facing table config becomes the expected core query behavior.

Look for:

- filter class supports a mode that query translation ignores
- sort or search options declared in columns but missing in query conversion
- metadata assumptions that break relations/accessors
- exported data path differing from rendered data path

Important areas:

- `packages/table/src/Concerns/TableQueryService.php`
- `packages/table/src/Columns/`
- `packages/table/src/Filters/`
- `packages/core/src/Core/Query/`
- `packages/core/src/Core/Metadata/`

### 7. Plugin And Macro Wiring

Check extension points, especially in sortable and core plugins.

Look for:

- plugin registered but not booted
- macro exposed but not consumed
- runtime checks for flags that no macro/config path sets
- behavior enabled in docs/examples but absent in actual boot path

Important areas:

- `packages/core/src/Core/Plugin/PluginManager.php`
- `packages/sortable/src/WireSortableServiceProvider.php`
- `packages/sortable/src/SortablePlugin.php`
- `packages/table/src/Table.php`

### 8. Tests Versus Behavior

Check that tests cover the contracts with the highest blast radius.

Look for:

- public API changes without matching tests
- runtime seams with only unit tests and no integration coverage
- docs/workbench examples that rely on untested behavior
- deleted or moved docs/features without test cleanup

Critical suites:

- `composer test:core`
- `composer test:forms`
- `composer test:table`
- `composer test:sortable`
- `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`

### 9. Docs And Example Drift

Check authored docs and previews only after code seams are understood.

Look for:

- docs referencing removed package docs or old APIs
- preview routes/components using outdated method names
- install instructions not matching actual package/provider behavior

Important areas:

- `docs/`
- `docs-site/`
- `workbench/app/Livewire/Docs/`
- `workbench/app/Livewire/Previews/`

## Package-Specific Audit Focus

### Core

Prioritize:

- provider registration drift
- Blade namespace consistency
- action/modal/notification contract mismatches
- plugin lifecycle inconsistencies

### Forms

Prioritize:

- config versus runtime drift
- save lifecycle hook ordering
- field API versus rendered markup
- action/form integration mismatches

### Table

Prioritize:

- `Table.php` versus `WithTable.php` behavior drift
- query/filter/sort translation gaps
- state synthesizer mismatches
- column/filter classes diverging from partials

### Sortable

Prioritize:

- provider macro registration
- plugin boot lifecycle
- reorder flags versus runtime checks
- persisted order model versus UI behavior

## Useful Commands

Start narrow, then widen only if needed.

```bash
# Static analysis
composer analyse

# Package tests
composer test:core
composer test:forms
composer test:table
composer test:sortable

# Integration
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"

# Docs/workbench drift hints
npm run docs:changed -- --dry-run
```

Useful search patterns:

```bash
rg "macro\\(" packages/
rg "registerPropertySynthesizer|PluginManager|ActionMacros" packages/
rg "wire-actions|wire-modals|wire-notifications|wire-forms" packages/
rg "TODO|FIXME|deprecated|@deprecated" packages/ docs/ architecture/
```

## Reporting Format

When reporting findings:

1. List findings first, ordered by severity.
2. Include concrete file references.
3. State why the behavior is inconsistent or risky.
4. Note missing verification if tests were not run.
5. Keep summaries secondary.

Use this structure:

```text
Findings
- High: ...
- Medium: ...
- Low: ...

Open questions
- ...

Verification
- Ran ...
- Did not run ...
```

If no issues are found, state that explicitly and mention any residual risk or test gaps.
