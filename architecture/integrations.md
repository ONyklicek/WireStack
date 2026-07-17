# Integrations

Use this file when a task crosses package boundaries or touches repo-level workflow.

## Package Flow

```text
wire-sortable -> wire-table -> wire-forms -> wire-core
```

Meaning:

- `core` defines shared runtime and base UI primitives
- `forms` consumes core and integrates forms into actions
- `table` consumes core + forms and adds table runtime/state/query behavior
- `sortable` consumes table and extends it through plugins and macros

## Main Seams

### Forms into Core Actions

Read:

- `packages/forms/src/Integration/ActionMacros.php`
- relevant files in `packages/core/src/Actions/`

Use this seam for:

- opening forms inside actions
- submit/save behavior from actions
- action/form modal interaction

### Table into Core Query Runtime

Read:

- `packages/table/src/Services/TableQueryService.php`
- `packages/core/src/Core/Query/`
- `packages/core/src/Core/Metadata/`

Use this seam for:

- filter translation
- sort translation
- metadata-driven query behavior
- planner/executor mismatches

### Table State into Livewire

Read:

- `packages/table/src/Livewire/TableStateSynthesizer.php`
- `packages/table/src/Concerns/WithTable.php`

Use this seam for:

- hydration bugs
- nested component state problems
- serialization mismatch across requests

### Sortable into Table + Core Plugins

Read:

- `packages/sortable/src/WireSortableServiceProvider.php`
- `packages/sortable/src/SortablePlugin.php`
- `packages/core/src/Core/Plugin/PluginManager.php`
- `packages/table/src/Table.php`

Use this seam for:

- missing sortable boot behavior
- macro registration issues
- plugin lifecycle problems
- reorderability not appearing in tables

## Service Provider Boot Order

Relevant providers:

- `NyonCode\WireCore\WireCoreServiceProvider`
- `NyonCode\WireForms\WireFormsServiceProvider`
- `NyonCode\WireTable\WireTableServiceProvider`
- `NyonCode\WireSortable\WireSortableServiceProvider`

When a behavior depends on registration or boot timing, inspect providers before changing downstream consumer code.

## Downstream Test Matrix

Use the smallest matrix that covers the seam you touched.

### Core changed

Run:

- `composer test:core`

Then usually:

- `composer test:forms`
- `composer test:table`

Add:

- `composer test:sortable`
  if plugins, table extensions, or shared runtime hooks changed

### Forms changed

Run:

- `composer test:forms`

Then usually:

- `composer test:table`

Add:

- `composer test:core`
  if action integration changed

### Table changed

Run:

- `composer test:table`

Then usually:

- `composer test:sortable`

Add:

- `composer test:forms`
  if action modals or shared form rendering changed

### Sortable changed

Run:

- `composer test:sortable`
- `composer test:table`

### Always add integration tests when these changed

- service provider registration
- runtime state shape
- Livewire synthesizer behavior
- plugin boot lifecycle
- action/form/table orchestration

Command:

```bash
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

## Workbench And Docs

The local manual-testing app lives in:

- `workbench/`

Most relevant paths:

- `workbench/routes/web.php`
- `workbench/app/Livewire/Docs/`
- `workbench/app/Livewire/Previews/`
  (registered with Livewire by `Workbench\App\Providers\WorkbenchServiceProvider` — enabled in
  `testbench.yaml` — so previews survive update roundtrips; without it, clicking anything in a
  preview fails with "Unable to find component". Interactive/mobile QA: `vendor/bin/testbench serve`
  + the `table-modal-*`, `table-actions-group`, `table-paginated`, `forms-tabs`, `forms-wizard` slugs.)
- `workbench/resources/views/docs/`
- `workbench/resources/views/previews/`

Use workbench when:

- Blade output changed
- preview pages changed
- interactive Livewire behavior needs manual verification

## Docs Build Flow

Source docs:

- `docs/`

Static site:

- `docs-site/build.php`
- `docs-site/assets/`
- `docs-site/templates/`

Generated output:

- `docs-site/dist/`

Helpers:

- `scripts/docs-changed.sh`
- `scripts/refresh-docs-site.sh`

Use `docs-changed.sh --dry-run` first to avoid unnecessary preview capture work.

## Token Discipline

For large-project work, avoid loading:

- `docs-site/dist/`
- `build/phpstan/`
- `node_modules/`
- `vendor/`
- screenshot assets

Open authored sources and package docs first, then drill into source files only in the affected package and seam.
