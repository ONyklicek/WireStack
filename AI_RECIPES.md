# AI Recipes

Concrete implementation recipes for AI-assisted changes in this repository.
Read this only after `CLAUDE.md`, `AI_BLUEPRINT.md`, and the relevant package
architecture file.

## Purpose

This file turns the architecture blueprint into repeatable code-generation and
code-change workflows. Treat each recipe as a checklist, not as permission to
skip reading existing code.

Before using any recipe:

1. Find the closest existing implementation.
2. Read its test.
3. Follow its naming, namespace, fluent API, view, and test style.
4. Keep the new behavior owned by the lowest correct package.

## Add A Shared Foundation Concern

Use when a capability is cross-cutting across fields, columns, actions, widgets,
or other component-like objects.

Read first:

- `packages/core/src/Foundation/Concerns/`
- similar concern tests in `packages/core/tests/Unit/Foundation/`
- downstream consumers that will use the concern

Implementation shape:

1. Add or extend a trait in `packages/core/src/Foundation/Concerns/`.
2. Store raw configured values privately/protected.
3. Accept scalar, enum, array, object, `Closure`, or `null` only when the existing
   concern vocabulary supports that style.
4. Resolve dynamic values with `EvaluatesClosures` if the owner already supports
   closure evaluation.
5. Add a getter that returns a resolved primitive/value object for views.
6. Add a focused unit test in `packages/core/tests/Unit/Foundation/`.
7. Wire downstream classes to the concern instead of duplicating logic.

Do not:

- add a duplicate resolver to forms/table/sortable when a foundation concern can
  own it
- add broad dependencies from `core` to downstream packages
- change public method names casually; fluent APIs are package contract

Verification:

```bash
composer test:core
composer test:forms
composer test:table
```

## Add A Core Action

Use when adding a reusable row, bulk, header, modal, or lifecycle-backed action.

Read first:

- `packages/core/src/Actions/BaseAction.php`
- nearest existing action such as `Action`, `DeleteAction`, `EditAction`,
  `BulkAction`, or `HeaderAction`
- `packages/core/tests/Unit/Actions/`
- action views in `packages/core/resources/views/actions/`

Implementation shape:

1. Add the class under `packages/core/src/Actions/`.
2. Extend the closest existing action base.
3. Provide `static make(string $name = '...')` if that is the local pattern.
4. Use existing concerns for color, icon, visibility, modal, lifecycle, loading,
   keyboard shortcut, and button styles.
5. Add default label/icon/color only if they are part of the action's semantic
   identity.
6. Add tests for fluent config, execution/lifecycle behavior, and modal behavior
   if applicable.

If the action needs a form:

- implement through the existing action form integration surface
- read `packages/forms/src/Integration/ActionMacros.php`
- do not hard-code forms logic into `core` if that would make `core` depend on
  `forms`

Verification:

```bash
composer test:core
composer test:forms
```

## Add A Form Field

Use when adding a new component to `wire-forms`.

Read first:

- `packages/forms/src/Components/Field.php`
- closest existing field class
- matching existing field Blade view
- `packages/forms/tests/Unit/Components/`
- `architecture/forms.md`

Files usually changed:

- `packages/forms/src/Components/{FieldName}.php`
- `packages/forms/resources/views/components/{field-name}.blade.php`
- `packages/forms/tests/Unit/Components/{FieldName}Test.php`
- docs only if the public API is user-facing

Implementation shape:

1. Extend `Field` unless the component is display/layout-only.
2. Use `static make(string $name): static` if matching field pattern requires it.
3. Reuse shared concerns for label, state, default, placeholder, helper text,
   hint, tooltip, icon, color, size, visibility, authorization, debounce,
   read-only, extra attributes, prefix/suffix, and live behavior.
4. Keep configuration fluent and declarative.
5. Resolve dynamic values in PHP and pass simple values to Blade.
6. Put markup in the Blade view.
7. Use existing field wrapper partials unless the component is intentionally
   wrapperless.
8. Add tests for default configuration, fluent setters/getters, validation/state
   interaction, and rendering if markup behavior matters.

Do not:

- add one-off wrapper markup when a field wrapper already exists
- add save/runtime behavior to a field class if it belongs in `Forms/Runtime/`
- bypass `FormValidationResolver` for validation behavior

Verification:

```bash
composer test:forms
```

Add table tests if the field is used in filters, table actions, or inline editing:

```bash
composer test:table
```

## Add A Form Layout Or Display Component

Use for sections, grids, fieldsets, alerts, placeholders, HTML/view displays.

Read first:

- `packages/forms/src/Components/Layout/`
- `packages/forms/src/Components/Display/`
- `packages/forms/resources/views/layouts/`
- `packages/forms/resources/views/components/`

Implementation shape:

1. Choose whether the component is layout, display, or field-like.
2. Reuse foundation concerns for label, visibility, authorization, column span,
   extra attributes, and helper text where meaningful.
3. Keep child schema handling consistent with `Grid`, `Section`, `Fieldset`, or
   `Repeater`.
4. Add a matching Blade view.
5. Add tests under `packages/forms/tests/Unit/Components/`.

Verification:

```bash
composer test:forms
```

## Add A Table Column

Use when adding a new renderable table cell type.

Read first:

- `packages/table/src/Columns/Column.php`
- closest existing column class
- `packages/table/resources/views/tables/columns/`
- `packages/table/tests/Unit/Columns/`
- `architecture/table.md`

Files usually changed:

- `packages/table/src/Columns/{Name}Column.php`
- `packages/table/resources/views/tables/columns/{name}.blade.php`
- `packages/table/tests/Unit/Columns/{Name}ColumnTest.php`

Implementation shape:

1. Extend `Column` or the closest existing concrete column.
2. Keep configuration fluent and typed.
3. Reuse column/base concerns and foundation semantics.
4. Resolve record/state/config in PHP.
5. Render through `renderView('tables.columns.{name}', [...])`.
6. Add or update the matching Blade partial.
7. Add tests for configuration, state resolution, rendering, and table query
   integration if the column is searchable/sortable/filter-aware.

Do not:

- return custom inline HTML directly from `renderCell()`
- duplicate table query translation logic in a column if it belongs in
  `TableQueryService`
- encode shared color/icon/size semantics locally

Verification:

```bash
composer test:table
```

Add downstream sortable tests if column ordering/reordering is affected:

```bash
composer test:sortable
```

## Add A Table Filter

Use when adding a user-facing filter type.

Read first:

- `packages/table/src/Filters/Filter.php`
- existing filters: `SelectFilter`, `DateFilter`, `NumberRangeFilter`,
  `TernaryFilter`
- filter views in `packages/table/resources/views/tables/filters/`
- `packages/table/src/Services/TableQueryService.php`
- `packages/table/tests/Unit/Filters/`

Files usually changed:

- `packages/table/src/Filters/{Name}Filter.php`
- `packages/table/resources/views/tables/filters/{name}.blade.php`
- `packages/table/tests/Unit/Filters/{Name}FilterTest.php`

Implementation shape:

1. Extend `Filter`.
2. Define state shape explicitly.
3. Add fluent configuration methods for user-facing options.
4. Render with a dedicated Blade partial.
5. Keep query translation aligned with `TableQueryService`.
6. Add filter indicators if the filter has a visible active state.
7. Add tests for state schema, indicator output, authorization/visibility if
   relevant, and query translation.

Verification:

```bash
composer test:table
```

## Add Or Change Table Runtime Behavior

Use for sorting, pagination, search, row actions, selection, modals, grouping,
sub-rows, polling, summaries, and Livewire state behavior.

Gesture-layer rule (keyboard nav, ranges, drag sweep, context menu, `?` help,
fill handle): never add a local flag for one. `Support\TableGestures` +
`Concerns\HasGestures` own the decision, the layer is **opt-in** (keyboard and
drag sweep are off until a table calls `->gestures()`), and any fixture, preview
or test that asserts grid semantics has to ask for them first. A new capability
is a setter plus an `allows*()` reader there, an effective `uses*()` reader that
folds in the prerequisite, and a line in `getGestureConfig()` if the client
needs it.

Read first:

- `packages/table/src/Concerns/WithTable.php`
- `packages/table/src/Table.php`
- relevant concern under `packages/table/src/Concerns/`
- relevant partial under `packages/table/resources/views/tables/partials/`
- nearby tests in `packages/table/tests/Unit/Concerns/`

Implementation shape:

1. Identify whether the change is config API, runtime orchestration, query flow,
   view rendering, or Livewire state serialization.
2. Put config API in `Table.php` or the appropriate trait/value object.
3. Put interaction orchestration in `WithTable.php` or a focused concern.
4. Put query changes in `TableQueryService`.
5. Put serialization changes in `TableStateSynthesizer`.
6. Keep views as partials; do not bury complex markup in PHP.
7. Add tests around the exact interaction path.

Verification:

```bash
composer test:table
```

If runtime state, Livewire synthesizer, or cross-package orchestration changed:

```bash
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

## Add Or Change Sortable Behavior

Use for row reorderability, column reorderability, sortable plugin wiring, table
macros, and persisted order state.

Read first:

- `packages/sortable/src/WireSortableServiceProvider.php`
- `packages/sortable/src/SortablePlugin.php`
- `packages/sortable/src/SortableTable.php`
- `packages/sortable/src/Concerns/WithSortable.php`
- `packages/table/src/Table.php`
- `architecture/sortable.md`
- `architecture/integrations.md`

Implementation shape:

1. Decide whether the change belongs to plugin boot, table macro API, table
   runtime behavior, UI script/view behavior, or persisted model state.
2. Keep table-extension API registered from sortable, not hard-coded into table,
   unless the table package owns the generic extension point.
3. Preserve plugin registration through `PluginManager`.
4. Add tests in `packages/sortable/tests/Unit/`.
5. Add table tests if the change depends on table runtime behavior.

Verification:

```bash
composer test:sortable
composer test:table
```

## Add A Plugin Extension Point

Use when behavior must be extensible by packages or consumers.

Read first:

- `packages/core/src/Core/Plugin/PluginManager.php`
- `architecture/core/plugins.md`
- `architecture/decisions/0014-plugin-architecture.md`
- existing sortable plugin integration

Implementation shape:

1. Add the extension point to `core` only if it is package-agnostic.
2. Keep plugin contracts stable and minimal.
3. Register plugins through provider/config flow.
4. Add integration tests for boot/register ordering.
5. Update `architecture/integrations.md` if the seam changes.

Verification:

```bash
composer test:core
composer test:sortable
vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"
```

## Add Or Change Docs/Workbench Preview

Use when public docs, previews, screenshots, or static docs site output changes.

Read first:

- `docs/`
- `docs-site/README.md`
- `docs-site/build.php`
- `workbench/routes/web.php`
- relevant `workbench/app/Livewire/Docs/` or `workbench/app/Livewire/Previews/`
- `architecture/integrations.md`

Implementation shape:

1. Update source docs, not generated `docs-site/dist/`, unless explicitly asked.
2. Update workbench preview only when behavior or visual output changed.
3. Run a dry-run changed-docs check before refreshing screenshots.

Verification:

```bash
php docs-site/build.php
npm run docs:changed -- --dry-run
```

If frontend assets changed:

```bash
npm run build
```

## Add A Test

Use the narrowest test layer that proves the behavior.

Patterns:

- foundation concern or value object: `packages/core/tests/Unit/Foundation/`
- core action/modal/notification/widget: `packages/core/tests/Unit/`
- form field/config/runtime: `packages/forms/tests/Unit/`
- standalone form behavior: `packages/forms/tests/Standalone/`
- table column/filter/export/runtime: `packages/table/tests/Unit/`
- sortable plugin/macro/runtime: `packages/sortable/tests/Unit/`
- provider boot, plugin lifecycle, Livewire state, cross-package orchestration:
  `tests/Integration/`

Do not add broad integration coverage for behavior that can be proven with a
focused unit test.

## Refactor Existing Behavior

Use when consolidating duplication or moving behavior to a canonical owner.

Workflow:

1. Locate all duplicate implementations.
2. Identify the lowest correct owner.
3. Add or extend the canonical abstraction.
4. Move one caller at a time.
5. Preserve public APIs unless the task explicitly permits breaking changes.
6. Delete compatibility layers only when no supported caller requires them.
7. Run owner tests first, downstream tests second.

Stop and ask only if the refactor requires choosing between incompatible public
APIs or breaking documented behavior.

