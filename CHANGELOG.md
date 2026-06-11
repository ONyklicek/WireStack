# Changelog

All notable changes to the Wire ecosystem will be documented in this file.

## [1.1.0]

### Added
- **Sub-row scoped table filters** – `Filter::subRows()` targets the child records of `Table::subRows()`: parents reduce to those with a matching child (`whereHas`), expanded panels show only matching children, and rollup aggregates (`->sums()`, `->counts()`, …) plus their footer grand totals count only the matching children.
- **`DateFilter::month()`** – Month/Year filtering (`whereYear` + `whereMonth`) rendered as a native month picker; combines with `subRows()` for filtering by the month of child records.
- **`DateTimePicker::asMonth()`** (wire-forms) – month mode rendering a native `<input type="month">`.
- **URL query-string persistence** – `Table::queryString()` keeps search, sort, per-page, and filter state in the URL (shareable/bookmarkable table views). Incoming values are validated against the table config; an optional prefix (`queryString('orders_')`) avoids collisions between multiple tables on one page.
- **Filter indicator chips** – active filters render as removable chips under the toolbar with per-type labels (option labels, range bounds, translated month names); customizable via `Filter::indicator(string|Closure)`, removable via the new `removeTableFilter()` action.

### Fixed
- Multiple-select filter (`SelectFilter::multiple()`) no longer crashes the select view when an array value is active, and renders the selected options correctly.
- **Action modal forms no longer lose typed text.** Action forms forced `wire:model.live` on every field, so each keystroke pause triggered a full component re-render whose DOM morph raced further typing and erased it. Fields now default to deferred `wire:model` (values are sent with the submit call), eliminating both the text loss and the per-keystroke server roundtrip + table re-render. Fields that drive reactive behavior opt in per field via `->live()`.

## [1.0.0] – 2026-06-11

### Added
- **`wire-sortable` package** – drag & drop row sorting as a table plugin (`SortablePlugin`, `SortableTable`).
- **Plugin system** in `wire-core` (`PluginManager`) with string and typed hooks (e.g. `form.saving`) and plugin toolbar widgets.
- **Widget system** with toolbar widgets and workbench previews.
- **Audit subsystem** (`HasAuditable`) and **authorization trait** (`HasAuthorization`).
- **Table exports** with optional exporter drivers.
- **Bundled Heroicons** solid and outline sets (`outline:` prefix) in `DefaultIconSet`; `IconManager` is now resolved via DI.
- **Table summaries & subrows** – new summary types, numeric formatting with prefix/suffix, footer scope toggle, sortable subrows with actions and eager loading (N+1 fix).
- **Row polling** (`poll()`) and stable table identifiers for multi-table pages.
- **Macroable support** and `ActionMacros` cross-package integration.
- **Relation aggregate columns** (rollup attributes such as `items_sum_total`).
- **docs-site** with per-field live previews and six new guides: custom fields, testing, theming, cookbook, troubleshooting, upgrade.

### Changed
- **Design-system consolidation** – canonical color/size vocabulary now lives in `Foundation/Concerns` (`HasColor`, `HasSize`, `HasIcon`); table columns and forms delegate to it, inline SVGs replaced by `<x-wire::icon>`.
- Nine custom-UI table columns migrated from inline `renderCell()` to Blade partials via `HasView::renderView()`.
- Forms UI refreshed.

### Fixed
- Livewire `morph.updating` bug.
- `poll()` no longer accepts invalid polling interval values.
- `ImageColumn::size()` LSP violation that caused a fatal error.
- `ColorPicker::swatches()` `TypeError` when passing a `Closure` (missing import).
- Latent fatal in `HasColor` consumers – modal view components now define `getColor()`.

## [0.1.0] – 2026-04-18

### Added
- **Monorepo structure** with three packages: `wire-core`, `wire-forms`, `wire-table`.
- `wire-core` package with shared traits, actions, modals, notifications, icons, colors.
- `wire-forms` package with 20+ field types, layout components, standalone form support.
- GitHub Actions CI (tests matrix PHP 8.2/8.3/8.4 × Laravel 10/11/12, PHPStan, Pint).
- Monorepo split workflow for per-package releases.
- ADR documentation for architectural decisions.

### Breaking Changes

#### Namespace Changes

The following classes have moved to new namespaces. Update your `use` statements:

| Before | After |
|--------|-------|
| `NyonCode\WireTable\Actions\Action` | `NyonCode\WireCore\Actions\Action` |
| `NyonCode\WireTable\Actions\BulkAction` | `NyonCode\WireCore\Actions\BulkAction` |
| `NyonCode\WireTable\Actions\HeaderAction` | `NyonCode\WireCore\Actions\HeaderAction` |
| `NyonCode\WireTable\Actions\ActionGroup` | `NyonCode\WireCore\Actions\ActionGroup` |
| `NyonCode\WireTable\Actions\ActionHalt` | `NyonCode\WireCore\Actions\ActionHalt` |
| `NyonCode\WireTable\Actions\DeleteAction` | `NyonCode\WireCore\Actions\DeleteAction` |
| `NyonCode\WireTable\Actions\DeleteBulkAction` | `NyonCode\WireCore\Actions\DeleteBulkAction` |
| `NyonCode\WireTable\Actions\EditAction` | `NyonCode\WireCore\Actions\EditAction` |
| `NyonCode\WireTable\Actions\ViewAction` | `NyonCode\WireCore\Actions\ViewAction` |
| `NyonCode\WireTable\Actions\ModalStep` | `NyonCode\WireCore\Actions\ModalStep` |
| `NyonCode\WireTable\Actions\ModalFooterAction` | `NyonCode\WireCore\Actions\ModalFooterAction` |
| `NyonCode\WireTable\Notifications\TableNotification` | `NyonCode\WireCore\Notifications\TableNotification` |
| `NyonCode\WireTable\Notifications\TableNotificationManager` | `NyonCode\WireCore\Notifications\TableNotificationManager` |
| `NyonCode\WireTable\Notifications\Contracts\NotificationDriver` | `NyonCode\WireCore\Notifications\Contracts\NotificationDriver` |
| `NyonCode\WireTable\Notifications\Drivers\SessionDriver` | `NyonCode\WireCore\Notifications\Drivers\SessionDriver` |
| `NyonCode\WireTable\Notifications\Drivers\LivewireEventDriver` | `NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver` |
| `NyonCode\WireTable\Notifications\Drivers\FlasherDriver` | `NyonCode\WireCore\Notifications\Drivers\FlasherDriver` |
| `NyonCode\WireTable\Forms\Fields\Field` | `NyonCode\WireForms\Components\Field` |
| `NyonCode\WireTable\Forms\Fields\TextInput` | `NyonCode\WireForms\Components\TextInput` |
| `NyonCode\WireTable\Forms\Fields\Textarea` | `NyonCode\WireForms\Components\Textarea` |
| `NyonCode\WireTable\Forms\Fields\Select` | `NyonCode\WireForms\Components\Select` |
| `NyonCode\WireTable\Forms\Fields\Checkbox` | `NyonCode\WireForms\Components\Checkbox` |
| `NyonCode\WireTable\Forms\Fields\CheckboxList` | `NyonCode\WireForms\Components\CheckboxList` |
| `NyonCode\WireTable\Forms\Fields\Radio` | `NyonCode\WireForms\Components\Radio` |
| `NyonCode\WireTable\Forms\Fields\Toggle` | `NyonCode\WireForms\Components\Toggle` |
| `NyonCode\WireTable\Forms\Fields\DatePicker` | `NyonCode\WireForms\Components\DateTimePicker` (use `->asDate()`) |
| `NyonCode\WireTable\Forms\Fields\DateTimePicker` | `NyonCode\WireForms\Components\DateTimePicker` |
| `NyonCode\WireTable\Forms\Fields\TimePicker` | `NyonCode\WireForms\Components\DateTimePicker` (use `->asTime()`) |
| `NyonCode\WireTable\Forms\Fields\ColorPicker` | `NyonCode\WireForms\Components\ColorPicker` |
| `NyonCode\WireTable\Forms\Fields\FileUpload` | `NyonCode\WireForms\Components\FileUpload` |
| `NyonCode\WireTable\Forms\Fields\RichEditor` | `NyonCode\WireForms\Components\RichEditor` |
| `NyonCode\WireTable\Forms\Fields\Hidden` | `NyonCode\WireForms\Components\Hidden` |
| `NyonCode\WireTable\Forms\Fields\Section` | `NyonCode\WireForms\Components\Layout\Section` |
| `NyonCode\WireTable\Forms\Fields\Fieldset` | `NyonCode\WireForms\Components\Layout\Fieldset` |
| `NyonCode\WireTable\Forms\Fields\Grid` | `NyonCode\WireForms\Components\Layout\Grid` |
| `NyonCode\WireTable\Forms\Fields\Placeholder` | `NyonCode\WireForms\Components\Display\Placeholder` |
| `NyonCode\WireTable\Forms\Fields\Alert` | `NyonCode\WireForms\Components\Display\Alert` |
| `NyonCode\WireTable\Forms\Fields\Html` | `NyonCode\WireForms\Components\Display\Html` |
| `NyonCode\WireTable\Forms\Fields\ViewField` | `NyonCode\WireForms\Components\Display\ViewField` |

#### Composer Changes
- `nyoncode/wire-table` now requires `nyoncode/wire-core` and `nyoncode/wire-forms`.
- `nyoncode/engine-core` dependency removed (absorbed into `wire-core`).

#### Config Changes
- Notification driver config keys in `wire-table.php` now reference `NyonCode\WireCore\Notifications\Drivers\*`.

### Migration Guide

1. Update `composer.json`:
   ```bash
   composer require nyoncode/wire-table:^0.1
   ```
   This will automatically install `wire-core` and `wire-forms`.

2. Find and replace namespaces in your codebase using the table above. Most common replacements:
   ```
   NyonCode\WireTable\Actions\ → NyonCode\WireCore\Actions\
   NyonCode\WireTable\Forms\Fields\ → NyonCode\WireForms\Components\
   NyonCode\WireTable\Notifications\ → NyonCode\WireCore\Notifications\
   ```

3. Layout components moved to sub-namespace:
   ```
   NyonCode\WireForms\Components\Layout\Section
   NyonCode\WireForms\Components\Layout\Grid
   NyonCode\WireForms\Components\Layout\Fieldset
   ```

4. Display components moved to sub-namespace:
   ```
   NyonCode\WireForms\Components\Display\Alert
   NyonCode\WireForms\Components\Display\Html
   NyonCode\WireForms\Components\Display\Placeholder
   NyonCode\WireForms\Components\Display\ViewField
   ```

5. DatePicker/TimePicker unified into `DateTimePicker`:
   ```php
   // Before:
   DatePicker::make('birth_date')
   TimePicker::make('start_time')

   // After:
   DateTimePicker::make('birth_date')->asDate()
   DateTimePicker::make('start_time')->asTime()
   ```

6. Blade render syntax changed:
   ```blade
   {{-- Before --}}
   {!! $this->getTable() !!}

   {{-- After --}}
   {{ $this->table }}
   ```

7. If you customized notification driver config, update class references to `NyonCode\WireCore\Notifications\Drivers\*`.
