# Table Package

Owner package: `packages/table`

## What It Owns

`wire-table` is the table system built on top of `wire-core` and `wire-forms`.

It owns:

- table config API
- Livewire table runtime
- columns
- filters
- exports
- table state synthesis
- table views and partials

## First Files To Read

- `packages/table/src/WireTableServiceProvider.php`
- `packages/table/src/Table.php`
- `packages/table/src/Concerns/WithTable.php`
- `packages/table/src/Concerns/TableQueryService.php`
- `packages/table/src/Columns/Column.php`
- `packages/table/src/Filters/Filter.php`
- `packages/table/src/Livewire/TableStateSynthesizer.php`
- `packages/table/resources/views/tables/index.blade.php`

## Provider Responsibilities

`WireTableServiceProvider` mainly registers:

- `TableStateSynthesizer` with Livewire `HandleComponents`

That means state shape and hydration changes can have Livewire-wide effects for table consumers.

## Main Areas

### `Table.php`

Top-level fluent config object. Start here when the task is about table API shape.

### `Concerns/WithTable.php`

Primary Livewire trait and one of the highest-risk files in the repo.

Use here for:

- user interaction flow
- sorting/pagination/search wiring
- row actions and selection behavior
- modal/open-close table behavior
- orchestration across sub-features

### `Concerns/TableQueryService.php`

This is the main table-to-core query seam.

It translates table columns, filters, and sorts into definitions consumed by the core runtime. If a bug looks like "table config is right but query/result is wrong", start here before changing column/filter classes.

### `Columns/`

Column base plus concrete columns.

Most shared behavior lives in:

- `packages/table/src/Columns/Column.php`

Concrete columns own focused behavior such as:

- display formatting
- inline editing
- toggles/selects/text inputs
- stacked/split composition

Architecture rule:

- every renderable column UI should have its own Blade partial
- column classes should own state/configuration and delegate markup rendering to Blade
- inline HTML returned directly from `renderCell()` is legacy debt, not the pattern to copy
- if a concrete column renders custom UI, add or update the matching Blade partial under `packages/table/resources/views/tables/columns/`

Mechanism: the base `Column` uses `Concerns\HasView`, so every `renderCell()`
returns `$this->renderView('tables.columns.<name>', [...])` — it resolves an
explicit `->view()` override first, then `wire-table::tables.columns.<name>`,
then an app-level view. The column resolves all state/config in PHP and passes
plain primitives; the partial holds the HTML. No column returns inline HTML.

Current partials under `packages/table/resources/views/tables/columns/`:

- `text.blade.php` — base text cell (styling span, icon, URL link, copyable,
  tooltip, description); reuses `partials/copyable.blade.php`
- `responsive.blade.php` — mobile/desktop wrapper
- `badge` · `boolean` · `icon` · `image` · `button` · `toggle` · `poll` ·
  `split` · `stacked` · `select` · `text-input-editable` · `text-input-readonly`
- shared `partials/` — `spinner` (optional `$class`), `progress`, `copyable`

Many UI changes also require touching these partials under
`packages/table/resources/views/tables/columns/`.

### `Filters/`

Filter base plus concrete filters:

- `SelectFilter`
- `DateFilter`
- `NumberRangeFilter`
- `TernaryFilter`

Rendered filter views live under:

- `packages/table/resources/views/tables/filters/`

### `Export/`

CSV, Excel, and PDF export flow.

Relevant files:

- `TableExport.php`
- `ExportAction.php`
- `CsvExporter.php`
- `ExcelExporter.php`
- `PdfExporter.php`

PDF markup:

- `packages/table/resources/views/export/pdf.blade.php`

### `Livewire/TableStateSynthesizer.php`

Important when table state serialization or hydration breaks across requests or nested components.

## Hot Files

- `packages/table/src/Concerns/WithTable.php`
- `packages/table/src/Columns/Column.php`
- `packages/table/resources/views/tables/index.blade.php`

Read surrounding tests before major edits here.

## Typical Changes

- table API:
  `Table.php`
- query/filter/sort bugs:
  `TableQueryService.php`
- row interaction or Livewire orchestration:
  `WithTable.php`
- shared column behavior:
  `Columns/Column.php`
- one column type:
  concrete column class + matching Blade partials
- filter behavior:
  concrete filter class + matching filter view
- exports:
  `Export/` plus PDF view if markup output changes
- state serialization:
  `TableStateSynthesizer.php`

## Downstream Impact

Table changes frequently affect:

- forms, through action modals and shared field rendering
- sortable, through table macros and reorder behavior
- workbench previews and docs screenshots

## Tests To Run

Start with:

- `composer test:table`

Then add as needed:

- sortable behavior:
  `composer test:sortable`
- form-in-table actions or shared field rendering:
  `composer test:forms`
- cross-package state/runtime behavior:
  `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`

Useful authored docs:

- `docs/table/overview.md`
- `docs/table/actions.md`
- `docs/table/columns.md`
- `docs/table/filters.md`
- `docs/table/exports.md`
- `docs/table/summaries.md`
- `docs/table/grouping.md`
- `docs/table/sub-rows.md`
