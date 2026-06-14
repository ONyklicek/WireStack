# AI Blueprint

This file is the repository contract for AI assistants. It gives Claude/Codex a
stable mental model of the monorepo before changing code. Use it together with
`CLAUDE.md` and the package-specific files in `architecture/`.

## Objective

Make AI work in this repository like a maintainer who understands the Wire
ecosystem, not like a generic code generator.

The expected behavior is similar to a Filament/Nova-style blueprint:

- understand the package graph before editing
- identify the canonical owner of each behavior
- follow existing fluent, declarative APIs
- reuse shared concerns, contracts, value objects, views, and resolvers
- keep rendering surface-specific while sharing semantics centrally
- verify the smallest relevant test matrix after changes

## Blueprint Layers

Use these files as a layered AI operating system:

- `CLAUDE.md` — low-token routing and quick task navigation
- `AI_BLUEPRINT.md` — repository contract, package ownership, architecture rules
- `AI_RECIPES.md` — concrete implementation recipes for common changes
- `AI_COMPONENT_CATALOG.md` — index of existing components, concerns, views,
  tests, and runtime building blocks
- `AI_CHANGE_PROTOCOL.md` — strict before/during/after workflow for code changes

Read the recipe, catalog, and protocol files only when the task requires them.

## Repository Identity

`nyoncode/wire` is a Laravel/Livewire monorepo for enterprise-grade UI
components.

It contains four local Composer packages:

```text
wire-sortable -> wire-table -> wire-forms -> wire-core
```

Dependency direction matters:

- `wire-core` is the shared foundation and must not depend on downstream
  packages.
- `wire-forms` consumes `wire-core`.
- `wire-table` consumes `wire-core` and `wire-forms`.
- `wire-sortable` consumes `wire-table` and extends it with plugin/macro
  behavior.

Root `composer.json` loads all packages as local path repositories with symlinks.

## Required Reading Order

For every task:

1. Read `CLAUDE.md`.
2. Read this file.
3. Read exactly one package architecture file when the task is local:
   - `architecture/core.md`
   - `architecture/forms.md`
   - `architecture/table.md`
   - `architecture/sortable.md`
4. Read `architecture/integrations.md` when behavior crosses package boundaries.
5. Read `architecture/audit.md` only for full repo review, inconsistency hunting,
   or broad code audit.
6. Read ADRs in `architecture/decisions/` only when code and package docs do not
   explain why something exists.
7. Read tests for the area before making risky or shared changes.

Do not read generated or dependency output unless the task explicitly requires it:

```text
build/phpstan/
coverage/
docs-site/dist/
node_modules/
vendor/
```

## Package Ownership

### wire-core

Path: `packages/core`

Owns shared foundation and cross-package vocabulary:

- `Foundation/` concerns, contracts, colors, icons, base components, Blade view
  components, closure evaluation helpers
- actions, action groups, modal-backed actions, action lifecycle
- modals, confirmations, slide-overs, wizards
- notifications and drivers
- widgets
- audit logging
- `Core/` unified runtime: metadata, capabilities, query planning/execution,
  state, hydration, validation, action pipeline, events, plugins

Start files:

- `packages/core/src/WireCoreServiceProvider.php`
- `packages/core/src/Foundation/`
- `packages/core/src/Actions/BaseAction.php`
- `packages/core/src/Core/Plugin/PluginManager.php`

### wire-forms

Path: `packages/forms`

Owns standalone forms built on `wire-core`:

- field classes
- layout/display components
- form configuration API
- form runtime
- validation collection
- save lifecycle
- Livewire form integration

Runtime shape:

```text
Form
  -> ConfigBuilder
  -> FormConfig
  -> FormRuntime
  -> StateManager
  -> SaveHandler
```

Start files:

- `packages/forms/src/WireFormsServiceProvider.php`
- `packages/forms/src/Forms/Form.php`
- `packages/forms/src/Forms/WithForms.php`
- `packages/forms/src/Forms/Config/ConfigBuilder.php`
- `packages/forms/src/Forms/Runtime/FormRuntime.php`
- `packages/forms/src/Forms/Runtime/SaveHandler.php`
- `packages/forms/src/Integration/ActionMacros.php`

### wire-table

Path: `packages/table`

Owns the table system built on `wire-core` and `wire-forms`:

- table config API
- Livewire table runtime
- columns
- filters
- exports
- table state synthesis
- table views and partials

Start files:

- `packages/table/src/WireTableServiceProvider.php`
- `packages/table/src/Table.php`
- `packages/table/src/Concerns/WithTable.php`
- `packages/table/src/Concerns/TableQueryService.php`
- `packages/table/src/Columns/Column.php`
- `packages/table/src/Filters/Filter.php`
- `packages/table/src/Livewire/TableStateSynthesizer.php`
- `packages/table/resources/views/tables/index.blade.php`

### wire-sortable

Path: `packages/sortable`

Owns sortable extensions for `wire-table`:

- row reorderability
- optional column reorderability
- plugin registration
- table macros
- persisted reordered column state

Start files:

- `packages/sortable/src/WireSortableServiceProvider.php`
- `packages/sortable/src/SortablePlugin.php`
- `packages/sortable/src/SortableTable.php`
- `packages/sortable/src/Concerns/WithSortable.php`
- `packages/sortable/src/Models/ReorderableColumnOrder.php`

## Canonical Ownership Rules

Before implementing, answer these questions:

1. Which package owns this behavior?
2. Is this local to one package, or cross-cutting?
3. Is there already a shared concern, contract, enum, value object, resolver, or
   Blade component for it?
4. Should downstream code delegate to a shared owner instead of adding local
   logic?
5. Does rendering need separate surface-specific rules even if semantics are
   shared?

Rules:

- Shared reusable behavior belongs in the lowest dependency layer that can own it,
  usually `packages/core/src/Foundation/`.
- Prefer extending existing concerns such as `HasColor`, `HasIcon`, `HasSize`,
  `HasVisibility`, `HasLabel`, `HasState`, `HasDefault`, `HasAuthorization`,
  `HasExtraAttributes`, or equivalent local concerns.
- Do not create package-local helper APIs when a canonical foundation concept
  already exists.
- Avoid duplicate `match` maps, color/icon/size resolvers, state serialization
  logic, or parallel mini-APIs.
- If a new cross-cutting capability does not fit existing abstractions, introduce
  one canonical abstraction first, then wire downstream packages to it.
- Keep `core` dependency-light and downstream-agnostic.

## Public API Style

Public component APIs should feel close to Laravel Nova and Filament:

- fluent
- declarative
- owner-centric
- composable from actions, fields, columns, filters, widgets, and view
  components
- easy to use from Livewire classes

Copy the ergonomics, not the internals of Nova or Filament.

Good API direction:

```php
TextInput::make('name')
    ->label('Name')
    ->required()
    ->placeholder('Full name');

TextColumn::make('email')
    ->label('Email')
    ->searchable()
    ->sortable();
```

Avoid:

- ad-hoc Blade conditionals for reusable behavior
- package-local duplicated semantic logic
- config arrays where the existing package style uses fluent objects
- broad refactors unrelated to the task

## Rendering Rules

- Shared semantics can live in `core`; surface-specific rendering can stay in the
  owning package.
- Blade views should own HTML markup.
- PHP classes should own state, configuration, and resolved primitive values.
- Table columns should render through Blade partials under
  `packages/table/resources/views/tables/columns/`.
- Inline HTML returned directly from table column `renderCell()` is legacy debt,
  not the pattern to copy.
- Form field UI should update the component class and matching Blade view under
  `packages/forms/resources/views/`.
- When changing shared Blade primitives, check consumers in forms, table, and
  sortable.

## Cross-Package Seams

Read these first when a change touches more than one package:

- Forms into actions:
  `packages/forms/src/Integration/ActionMacros.php`
- Table into core query runtime:
  `packages/table/src/Concerns/TableQueryService.php`
- Table state into Livewire:
  `packages/table/src/Livewire/TableStateSynthesizer.php`
- Sortable into table and plugins:
  `packages/sortable/src/WireSortableServiceProvider.php`
  `packages/sortable/src/SortablePlugin.php`
  `packages/core/src/Core/Plugin/PluginManager.php`

## High-Risk Files

Treat these as high blast-radius files. Read surrounding tests before editing:

- `packages/core/src/WireCoreServiceProvider.php`
- `packages/forms/src/Forms/Form.php`
- `packages/forms/src/Forms/Runtime/SaveHandler.php`
- `packages/forms/src/Integration/ActionMacros.php`
- `packages/table/src/Concerns/WithTable.php`
- `packages/table/src/Concerns/TableQueryService.php`
- `packages/table/src/Columns/Column.php`
- `packages/table/src/Livewire/TableStateSynthesizer.php`
- `packages/table/resources/views/tables/index.blade.php`
- `packages/sortable/src/WireSortableServiceProvider.php`

## AI Workflow For Every Code Task

Use this workflow before editing:

1. Restate the target behavior in one or two sentences.
2. Identify the owning package.
3. Read `CLAUDE.md`, this blueprint, and the relevant package architecture file.
4. Inspect existing implementation and nearby tests.
5. Identify the existing pattern to follow.
6. List the files that need changes.
7. Make the smallest scoped change.
8. Add or update tests when behavior changes.
9. Run the narrowest relevant verification command.
10. Report changed files, behavior, tests run, and residual risk.

If unsure, do not invent structure. Search first.

## Test Matrix

Use the smallest matrix that covers the changed behavior.

Core changes:

```bash
composer test:core
composer test:forms
composer test:table
```

Forms changes:

```bash
composer test:forms
composer test:table
```

Table changes:

```bash
composer test:table
composer test:sortable
```

Sortable changes:

```bash
composer test:sortable
composer test:table
```

Cross-package runtime/state/provider/plugin changes:

```bash
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

Quality checks:

```bash
composer lint
composer analyse
```

Frontend/docs checks:

```bash
npm run build
php docs-site/build.php
npm run docs:changed -- --dry-run
```

## Common Commands

```bash
composer install
npm install

composer test
composer test:core
composer test:forms
composer test:table
composer test:sortable

composer lint
composer analyse

vendor/bin/testbench serve --host=127.0.0.1 --port=8085
npm run dev
npm run build
```

## Prompt Template For Future AI Sessions

Use this when starting a new Claude/Codex session:

```text
Use `CLAUDE.md` and `AI_BLUEPRINT.md`.
For implementation work also use `AI_CHANGE_PROTOCOL.md`, `AI_RECIPES.md`, and
`AI_COMPONENT_CATALOG.md`.

Task:
[describe the exact change]

Before editing:
1. Identify the owning package.
2. Read the relevant architecture file.
3. Find existing implementations/tests for the same pattern.
4. Explain the pattern you will follow.

Implementation rules:
- Keep changes scoped.
- Prefer canonical shared abstractions.
- Do not duplicate cross-package behavior.
- Do not refactor unrelated code.
- Update tests when behavior changes.

After editing:
- Run the narrowest relevant tests.
- Report changed files, behavior changed, tests run, and remaining risks.
```
