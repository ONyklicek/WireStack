# CLAUDE.md

Token-efficient routing file for Claude/Codex in this repository.

## Read Order

Use the smallest useful context first.

1. Read this file.
2. Read one package doc only if the task is local:
   - `architecture/core.md`
   - `architecture/forms.md`
   - `architecture/table.md`
   - `architecture/sortable.md`
3. Read `architecture/integrations.md` only for cross-package behavior.
4. Read `architecture/audit.md` only for full analysis, inconsistency hunting, or code review.
5. Read `architecture/decisions/` only when the current behavior is unclear or a design tradeoff matters.
6. Read generated output only if the task explicitly targets generated assets.

## Repo Graph

```text
wire-sortable -> wire-table -> wire-forms -> wire-core
```

Monorepo packages are loaded from root `composer.json` as local path repositories with symlinks.

## Start Here By Task

- Core actions, modals, notifications, icons, widgets:
  `architecture/core.md`
- Form field behavior, save lifecycle, validation, form runtime:
  `architecture/forms.md`
- Table UI, columns, filters, exports, state, query flow:
  `architecture/table.md`
- Sortable behavior and table extension points:
  `architecture/sortable.md`
- Anything spanning package boundaries:
  `architecture/integrations.md`
- Full analysis, inconsistency review, bug-hunting, or audit:
  `architecture/audit.md`
- Docs, previews, workbench, screenshot refresh:
  `architecture/integrations.md`

## Cross-Package Seams

Read these first when a change crosses package boundaries:

- `packages/forms/src/Integration/ActionMacros.php`
- `packages/table/src/Concerns/TableQueryService.php`
- `packages/table/src/Livewire/TableStateSynthesizer.php`
- `packages/core/src/Core/Plugin/PluginManager.php`
- `packages/sortable/src/WireSortableServiceProvider.php`

## Cross-Package Change Checklist

1. Identify the owning package and the first downstream consumer.
2. Read the package doc plus `architecture/integrations.md`.
3. Change the seam before changing downstream callers when the contract changes.
4. Run the narrow owning-package tests first.
5. Run downstream package tests next.
6. Run `tests/Integration/` if state, rendering, macros, plugins, or runtime wiring changed.
7. Refresh previews/docs only if views, docs assets, or preview routes changed.

## Hot Files

These are large or high-blast-radius files:

- `packages/table/src/Concerns/WithTable.php`
- `packages/table/src/Columns/Column.php`
- `packages/table/resources/views/tables/index.blade.php`
- `packages/forms/src/Forms/Form.php`
- `packages/forms/src/Forms/Runtime/SaveHandler.php`
- `packages/core/src/WireCoreServiceProvider.php`

## Ignore By Default

Do not read these unless the task explicitly requires them:

```text
build/phpstan/
docs-site/dist/
node_modules/
vendor/
```

Usually also avoid preview PNGs and other generated screenshots unless the task is visual verification.

## Fast Commands

```bash
composer install
npm install

composer test
composer test:core
composer test:forms
composer test:table
composer test:sortable

vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"

composer lint
composer analyse

vendor/bin/testbench serve --host=127.0.0.1 --port=8085
npm run dev
npm run build

php docs-site/build.php
npm run docs:changed -- --dry-run
npm run docs:refresh
```

## Deep Docs

- `architecture/README.md`
- `architecture/core.md`
- `architecture/forms.md`
- `architecture/table.md`
- `architecture/sortable.md`
- `architecture/integrations.md`
- `architecture/audit.md`
- `docs/project-map.md`
- `docs/configuration.md`
- `docs/getting-started.md`
