# CLAUDE.md

Token-efficient routing file for Claude/Codex in this repository.

## Read Order

Use the smallest useful context first.

1. Read this file.
2. Read `AI_CODING_STANDARD.md` before writing or changing any code. It is
   binding: composition over inheritance, interfaces before implementations,
   no business logic in traits, `InteractsWith*` / `CanBe*` trait naming, and
   the `Contracts/ Concerns/ Actions/ Services/ Managers/ Support/` layout.
3. Read `AI_BLUEPRINT.md` for the repo contract and package ownership rules.
4. Read one package doc only if the task is local:
   - `architecture/core.md`
   - `architecture/forms.md`
   - `architecture/table.md`
   - `architecture/sortable.md`
5. Read `architecture/integrations.md` only for cross-package behavior.
6. Read `architecture/audit.md` only for full analysis, inconsistency hunting, or code review.
7. Read `architecture/decisions/` only when the current behavior is unclear or a design tradeoff matters.
8. Read generated output only if the task explicitly targets generated assets.

## Repo Graph

```text
wire-sortable -> wire-table -> wire-forms -> wire-core
```

Monorepo packages are loaded from root `composer.json` as local path repositories with symlinks.

## Architectural Invariants

Prefer one canonical owner for every reusable behavior.

- If a capability is shared across packages or component types, extend the existing canonical abstraction instead of creating a local variant.
- Canonical shared abstractions should usually live in the lowest dependency layer that can own them, most often `packages/core/src/Foundation/`.
- Prefer extending existing `Foundation/Concerns/*`, `Foundation/Contracts/*`, enums, value objects, or shared support classes before adding package-local helpers.
- Downstream packages (`forms`, `table`, `sortable`) should consume or delegate to shared foundations. Avoid duplicating `match` maps, resolver methods, or parallel mini-APIs for the same concept.
- When behavior already exists as a concern such as `HasColor`, `HasIcon`, `HasSize`, `HasVisibility`, or similar shared trait vocabulary, treat that concern as the first extension point unless package docs explicitly say otherwise.
- Treat shared Foundation concerns as binding architectural extension points, not optional helpers. If a concern can be modeled universally, it should have one canonical owner in shared Foundation-level code.
- Apply this rule broadly, not just to `HasColor`: colors, icons, size, visibility, labels, state, defaults, shared options, reusable resolvers, value objects, enums, and owner-facing render helpers should all centralize once when they are truly cross-cutting.
- Reference example: `packages/core/src/Foundation/Concerns/HasColor.php` is the canonical shared color resolver. Follow the same pattern for any other concern that can be owned universally instead of treating `HasColor` as a one-off exception.
- Domain ownership examples: `HasColor` owns color semantics, `HasIcon` / `HasIcons` own icon semantics, and equivalent shared concerns should own their domain the same way. Downstream code should delegate rather than re-encode the same rules locally.
- When changing canonical color resolvers or Tailwind-facing utility vocabularies, preserve compatibility with the lowest supported consumer Tailwind version defined by `architecture/decisions/0005-tailwind-4-support.md`. Do not add Tailwind-version-specific color names or utility assumptions to shared resolvers unless the support policy is explicitly changed first.
- If a new cross-cutting capability does not fit an existing abstraction, add a new canonical abstraction first, then wire downstream callers to it.
- During refactors, prefer consolidation over compatibility layers unless backwards compatibility is explicitly required.

### Nova/Filament-Like Design Bias

Prefer APIs that feel closer to Laravel Nova / Filament:

- Public component APIs should be fluent, declarative, owner-centric, and easy to compose from actions, fields, columns, filters, and view components.
- Reusable UI behavior should not live as ad-hoc Blade conditionals or package-local `match` maps when it can be expressed as a shared owner or resolver.
- Shared semantics should be centralized once, while surface-specific rendering stays modular. For design-system work, prefer:
  - semantic registries / canonical vocabularies,
  - reusable per-surface resolvers or value objects,
  - owner-facing render helpers for Blade/views.
- Do not collapse distinct UI surfaces into one universal helper. A `button`, `link`, `badge`, `toggle`, `dropdown item`, `banner`, or similar surface may share semantics while still needing separate reusable rendering rules.
- Anything added for one package should be evaluated for reuse by other packages. Favor abstractions that can be consumed from `core`, `forms`, `table`, `sortable`, and future packages without copy-paste.
- New abstractions must stay modular and portable: minimal package assumptions, stable contracts, testable in isolation, and usable from other contexts besides the original caller.
- Copy Nova/Filament ergonomics, not their internals verbatim. The target is the same quality of composition and reuse, adapted to this repository's package graph.

Before changing shared behavior, ask:

1. What is the canonical owner of this concern?
2. Is there already a shared trait, contract, enum, or support class for it?
3. Should downstream code delegate to the shared owner instead of implementing its own logic?
4. Is this behavior really one surface, or should it be modeled as multiple reusable surface-specific resolvers?
5. Will this abstraction still make sense if another package needs the same behavior later?

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
- Shared design-system ownership, canonical UI semantics, color/size/icon surface work:
  `architecture/plans/canonical-ownership-consolidation.md`
- Full analysis, inconsistency review, bug-hunting, or audit:
  `architecture/audit.md`
- Docs, previews, workbench, screenshot refresh:
  `architecture/integrations.md`
- Implementation recipe for adding/changing fields, columns, filters, actions, plugins:
  `AI_RECIPES.md`
- Existing component/concern/view/test catalog:
  `AI_COMPONENT_CATALOG.md`
- Strict workflow for code changes, reviews, refactors, and test selection:
  `AI_CHANGE_PROTOCOL.md`

## Cross-Package Seams

Read these first when a change crosses package boundaries:

- `packages/forms/src/Integration/ActionMacros.php`
- `packages/table/src/Services/TableQueryService.php`
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

# Coverage gate (CI runs this on PRs and on pushes to 1.x/2.x).
# Every line you add or edit must be covered; no package may drop below its
# floor in scripts/coverage-floors.json.
composer coverage:verify
php scripts/verify-coverage.php build/clover.xml --diff=origin/1.x  # as CI checks it
composer coverage:floors                                            # raise a floor you improved

vendor/bin/testbench serve --host=127.0.0.1 --port=8085
npm run dev
npm run build

# Browser gate. The CDP drivers in workbench/scripts are the only check over
# Alpine/Livewire behaviour — Pest sees the markup, not what the browser does
# with it. Starts its own preview server, or reuses one already running.
npm run verify:drivers                # all of them
npm run verify:drivers -- selection   # only those matching a name

php docs-site/build.php
npm run docs:changed -- --dry-run
npm run docs:refresh
```

## Deep Docs

- `AI_CODING_STANDARD.md`
- `AI_BLUEPRINT.md`
- `AI_CHANGE_PROTOCOL.md`
- `AI_RECIPES.md`
- `AI_COMPONENT_CATALOG.md`
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
