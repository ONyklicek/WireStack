# AI Component Catalog

Index of reusable building blocks in the Wire monorepo. Use this to find an
existing pattern before creating a new class, view, resolver, or concern.

This catalog is intentionally concise. Source files and package architecture
docs remain authoritative.

## Package Graph

```text
wire-sortable -> wire-table -> wire-forms -> wire-core
```

Prefer the lowest package that can own the reusable behavior.

## Core Foundation

Path: `packages/core/src/Foundation`

### Concerns

Use these before creating local field/column/action helpers:

- `BelongsToComponent`
- `CanBeLive`
- `CanBeReadOnly`
- `CanBeTyped`
- `HasAuthorization`
- `HasColor`
- `HasColumnSpan`
- `HasDebounce`
- `HasDefault`
- `HasExtraAttributes`
- `HasHelperText`
- `HasHint`
- `HasIcon`
- `HasId`
- `HasLabel`
- `HasLivewire`
- `HasName`
- `HasPlaceholder`
- `HasPrefixAndSuffix`
- `HasSize`
- `HasState`
- `HasTooltip`
- `HasVisibility`

Contracts:

- `Foundation\Contracts\HasIcon`
- `Foundation\Contracts\HasLabel`
- `Foundation\Contracts\HasVisibility`

Support:

- `Foundation\Support\EvaluatesClosures`
- `Foundation\Support\ArrayDotHelper`

Colors/icons:

- `Foundation\Colors\Color`
- `Foundation\Icons\Icon`
- `Foundation\Icons\IconManager`
- `Foundation\Icons\IconSet`
- `Foundation\Icons\ResolvedIcon`
- `Foundation\Icons\DefaultIconSet`
- `Foundation\Icons\HeroiconsOutlineSet`

Browser assets (the registry, the URL and the tag belong to the toolkit's
`PackageAssets`; what is core's is the declaration):

- `Foundation\Assets\Bundle` — `make($shippedFile)` for a declaration every package
  shares (classic/IIFE, no `defer`, `data-navigate-once`) and
  `servedByRoute($package)` for the `hasAssetFallback()` resolver
- `Foundation\View\FloatingAssets` — the dropdown bundle's URL, by the name a dozen
  partials already ask for it

Foundation Blade components:

- `packages/core/resources/views/foundation/badge.blade.php`
- `packages/core/resources/views/foundation/button.blade.php`
- `packages/core/resources/views/foundation/dropdown.blade.php`
- `packages/core/resources/views/foundation/icon.blade.php`

View classes:

- `Foundation\View\Badge`
- `Foundation\View\Button`
- `Foundation\View\Dropdown`
- `Foundation\View\Icon`

## Core Actions

Path: `packages/core/src/Actions`

Base/action classes:

- `BaseAction`
- `Action`
- `BulkAction`
- `HeaderAction`
- `ActionGroup`
- `ModalFooterAction`
- `ModalStep`
- `ActionHalt`

Preset actions:

- `DeleteAction`
- `EditAction`
- `ViewAction`
- `DeleteBulkAction`
- `ForceDeleteBulkAction`
- `RestoreBulkAction`

Action concerns:

- `Actions\Concerns\HasButtonStyles`
- `Actions\Concerns\HasColor`
- `Actions\Concerns\HasDynamicProperties`
- `Actions\Concerns\HasIcons`
- `Actions\Concerns\HasKeyboardShortcut`
- `Actions\Concerns\HasLifecycle`
- `Actions\Concerns\HasLoadingState`
- `Actions\Concerns\HasModal`
- `Actions\Concerns\HasVisibility`
- `Actions\Concerns\InteractsWithActions` — canonical, form-agnostic action runtime (payload resolver, pipeline, halt/notification/redirect, infolist actions). Composed by `WithTable` and by the standalone `WithActions` host.

Action views/components:

- `Actions\View\ButtonComponent`, `BulkButtonComponent`, `GroupComponent`, `ModalHostComponent`
- `packages/core/resources/views/actions/button.blade.php`
- `packages/core/resources/views/actions/bulk-button.blade.php`
- `packages/core/resources/views/actions/group.blade.php`
- `packages/core/resources/views/actions/dropdown-item.blade.php`
- `packages/core/resources/views/actions/modal-host.blade.php` (+ `partials/modal-host-*`)
- `packages/core/resources/views/actions/partials/button-content.blade.php`

## Core Modals

Path: `packages/core/src/Modals`

Objects:

- `Modal`
- `ConfirmationDialog`
- `SlideOver`
- `Wizard`

Concerns:

- `Modals\Concerns\HasFooterActions`
- `Modals\Concerns\HasModalProperties`

Views:

- `packages/core/resources/views/modals/modal.blade.php`
- `packages/core/resources/views/modals/confirmation.blade.php`
- `packages/core/resources/views/modals/slide-over.blade.php`

## Core Notifications

Path: `packages/core/src/Notifications`

Objects/managers:

- `Notification`
- `NotificationAction`
- `NotificationManager`
- `TableNotification`
- `TableNotificationManager`

Drivers:

- `CurrentComponentDriver` *(built-in default; decorates `SessionDriver`, resolves `Livewire::current()`)*
- `SessionDriver`
- `LivewireEventDriver`
- `FlasherDriver`
- `NullDriver`

View:

- `packages/core/resources/views/notifications/toast-container.blade.php`

## Core Runtime

Path: `packages/core/src/Core`

Read `architecture/core/unified-engine.md` before changing this area.

Main areas:

- `Actions/`: `ActionContext`, `ActionPipeline`, `ActionRegistry`,
  `ActionResult`
- `Capabilities/`: `Capability`, `CapabilityResolver`, `CapabilitySet`
- `Components/`: `DataComponent`, `TextComponent`, `BooleanComponent`,
  `DateComponent`, `SelectComponent`, `RelationComponent`
- `Hydration/`: `Hydrator`, `Dehydrator`, `CastResolver`, `MutationPipeline`,
  `ValueTransformer`
- `Metadata/`: model, column, relation, accessor metadata and registry/cache
- `Plugin/`: `PluginManager`
- `Query/`: planner, executor, clauses, definitions, joins, aliases
- `Relations/`: relation AST and graph builder
- `State/`: state container, hydrator, serializer, dirty tracking, path resolver
- `Validation/`: validation pipeline and result

## Core Widgets And Audit

Widgets:

- `Widget`
- `ChartWidget` (JS / Chart.js)
- `BarChartWidget` (pure-CSS bar chart)
- `ChartItem` (bar entry for `BarChartWidget`)
- `CustomWidget`
- `Stat`
- `StatsOverviewWidget`
- `TableWidget`
- `Widgets\Concerns\HasPolling`
- `Widgets\Concerns\WithWidgets`

Widget views:

- `packages/core/resources/views/widgets/chart.blade.php`
- `packages/core/resources/views/widgets/bar-chart.blade.php`
- `packages/core/resources/views/widgets/bar-chart/vertical-finance.blade.php`
- `packages/core/resources/views/widgets/bar-chart/vertical-system.blade.php`
- `packages/core/resources/views/widgets/bar-chart/horizontal-system.blade.php`
- `packages/core/resources/views/widgets/custom.blade.php`
- `packages/core/resources/views/widgets/stats-overview.blade.php`
- `packages/core/resources/views/widgets/table.blade.php`
- `packages/core/resources/views/widgets/widget-grid.blade.php`

Audit:

- `AuditEntry`
- `AuditLogger`
- `AuditEventSubscriber`
- `Audit\Concerns\HasAuditable`
- audit events under `packages/core/src/Audit/Events/`
- `packages/core/resources/views/audit/trail.blade.php`

## Forms Components

Path: `packages/forms/src/Components`

Base:

- `Field`

Fields:

- `BelongsToSelect`
- `Checkbox`
- `CheckboxList`
- `CodeEditor`
- `ColorPicker`
- `DateTimePicker`
- `FileUpload`
- `Hidden`
- `KeyValue`
- `MarkdownEditor`
- `MorphToSelect`
- `OtpInput`
- `Radio`
- `Rating`
- `Repeater` (card layout, or `table()` for row layout)
- `Builder` (extends `Repeater`; per-item `Block` type) + `Block`
- `RichEditor`
- `Select`
- `Slider`
- `Tags`
- `TextInput`
- `Textarea`
- `TimePicker` (mode-locked `DateTimePicker`; slot-list panel, own view)
- `TiptapEditor`
- `Toggle`

Display components:

- `Display\Alert`
- `Display\Html`
- `Display\Placeholder`
- `Display\ViewField`

Layout components:

- `Layout\Fieldset`
- `Layout\Grid`
- `Layout\Section`

Form-specific concerns/contracts:

- `Concerns\CanBeAutofocused`
- `Concerns\HasFormValidation`
- `Contracts\HasForms`
- `Contracts\HasValidation`

## Forms Runtime

Path: `packages/forms/src/Forms`

Public entry points:

- `Form`
- `WithForms`
- `Concerns\WithActions` — host trait to declare and run standalone actions (modal/slide-over/wizard/confirmation/form) in any Livewire component, no table. Composes the wire-core `InteractsWithActions` engine + the form bridge below.
- `Concerns\InteractsWithActionForms` — form-hosting half of the action runtime (Form build/validate, wizard steps, halt form). Composed by both `WithActions` and `WithTable`.

Config/runtime:

- `Config\ConfigBuilder`
- `Config\FormConfig`
- `Runtime\FormRuntime`
- `Runtime\StateManager`
- `Runtime\SaveHandler`
- `Runtime\RelationshipSaveHandler`
- `Validation\FormValidationResolver`
- `Rendering\FormRenderer`

Integration seam:

- `Integration\ActionMacros`

## Forms Views

Field/component views:

- `packages/forms/resources/views/components/text-input.blade.php`
- `packages/forms/resources/views/components/textarea.blade.php`
- `packages/forms/resources/views/components/select.blade.php`
- `packages/forms/resources/views/components/checkbox.blade.php`
- `packages/forms/resources/views/components/checkbox-list.blade.php`
- `packages/forms/resources/views/components/radio.blade.php`
- `packages/forms/resources/views/components/toggle.blade.php`
- `packages/forms/resources/views/components/date-time-picker.blade.php`
- `packages/forms/resources/views/components/color-picker.blade.php`
- `packages/forms/resources/views/components/file-upload.blade.php`
- `packages/forms/resources/views/components/key-value.blade.php`
- `packages/forms/resources/views/components/repeater.blade.php`
- `packages/forms/resources/views/components/rich-editor.blade.php`
- `packages/forms/resources/views/components/markdown-editor.blade.php`
- `packages/forms/resources/views/components/tiptap-editor.blade.php`
- `packages/forms/resources/views/components/code-editor.blade.php`
- `packages/forms/resources/views/components/rating.blade.php`
- `packages/forms/resources/views/components/slider.blade.php`
- `packages/forms/resources/views/components/tags.blade.php`
- `packages/forms/resources/views/components/otp-input.blade.php`
- `packages/forms/resources/views/components/belongs-to-select.blade.php`
- `packages/forms/resources/views/components/morph-to-select.blade.php`
- `packages/forms/resources/views/components/hidden.blade.php`
- `packages/forms/resources/views/components/alert.blade.php`
- `packages/forms/resources/views/components/html.blade.php`
- `packages/forms/resources/views/components/placeholder.blade.php`
- `packages/forms/resources/views/components/view-field.blade.php`

Layout/wrapper views:

- `packages/forms/resources/views/form.blade.php`
- `packages/forms/resources/views/layouts/grid.blade.php`
- `packages/forms/resources/views/layouts/section.blade.php`
- `packages/forms/resources/views/layouts/fieldset.blade.php`
- `packages/forms/resources/views/partials/field-wrapper-start.blade.php`
- `packages/forms/resources/views/partials/field-wrapper-end.blade.php`

## Table Columns

Path: `packages/table/src/Columns`

Base:

- `Column`

Columns:

- `TextColumn`
- `BadgeColumn`
- `BooleanColumn`
- `IconColumn`
- `ImageColumn`
- `ButtonColumn`
- `ToggleColumn`
- `CheckboxColumn`
- `PollColumn`
- `SelectColumn`
- `TextInputColumn`
- `SplitColumn`
- `StackedColumn`
- `ColorColumn`
- `RatingColumn`
- `TagsColumn`

Summaries:

- `Columns\SummaryType`
- `Concerns\CanBeSummarized` — config + fluent API only
- `Services\SummaryCalculator` — one summary, in SQL or in PHP
- `Services\SummaryFormatter` — rendering
- `Services\SummaryBatch` — many summaries in one aggregate query
- `Support\SummaryFormat`, `Support\SummaryTarget` — what the services need to know about a column

Column views:

- `packages/table/resources/views/tables/columns/text.blade.php`
- `packages/table/resources/views/tables/columns/badge.blade.php`
- `packages/table/resources/views/tables/columns/boolean.blade.php`
- `packages/table/resources/views/tables/columns/icon.blade.php`
- `packages/table/resources/views/tables/columns/image.blade.php`
- `packages/table/resources/views/tables/columns/button.blade.php`
- `packages/table/resources/views/tables/columns/toggle.blade.php`
- `packages/table/resources/views/tables/columns/poll.blade.php`
- `packages/table/resources/views/tables/columns/select.blade.php`
- `packages/table/resources/views/tables/columns/split.blade.php`
- `packages/table/resources/views/tables/columns/stacked.blade.php`
- `packages/table/resources/views/tables/columns/text-input-editable.blade.php`
- `packages/table/resources/views/tables/columns/text-input-readonly.blade.php`
- `packages/table/resources/views/tables/columns/responsive.blade.php`

Shared column partials:

- `copyable`
- `progress`
- filter UI partials under `tables/columns/partials/filter-*`

Canonical shared partials (owned in `core`, consumed cross-package):

- `packages/core/resources/views/partials/spinner.blade.php` — single source of
  the loading-spinner SVG (`$class`, optional `$wireTarget`).
- `packages/sortable/resources/views/partials/drag-handle.blade.php` — drag-handle
  markup, rendered by the `Table::getDragHandleHtml()` macro and injected into the
  sortable Alpine component.

## Table Filters

Path: `packages/table/src/Filters`

Base:

- `Filter`

Filters:

- `SelectFilter`
- `DateFilter`
- `NumberRangeFilter`
- `TernaryFilter`
- `TrashedFilter` (soft-delete scope, not a column constraint)

Filter views:

- `packages/table/resources/views/tables/filters/select.blade.php`
- `packages/table/resources/views/tables/filters/date.blade.php`
- `packages/table/resources/views/tables/filters/number-range.blade.php`
- `packages/table/resources/views/tables/filters/ternary.blade.php`
- `packages/table/resources/views/tables/filters/text.blade.php`
- `packages/table/resources/views/tables/filters/form-field.blade.php`

## Table Runtime

Path: `packages/table/src`

Public/config:

- `Table`

Main concerns:

- `Concerns\WithTable`
- `Concerns\HasGrouping`
- `Concerns\HasResponsive`
- `Concerns\HasSqlDebug`
- `Concerns\HasSubRows`
- `Concerns\HasTableActions` — which actions a table carries (row, bulk, header, empty state) and how the actions column presents them: position, alignment, label, width, `solid`/`quiet` style, the composition rule in `composeRowActions()`, and the compiled `getActionCellSkeleton()`
- `Concerns\CollapsesActionsOnMobile` — the phone's half of the same feature: the row, header and sub-row folds into one `ActionGroup` dropdown, the counting rules that decide whether to fold, and the breakpoint classes that swap the two halves
- `Concerns\HasRecordActions` — whole-row interaction (see *Record actions* below)
- `Concerns\HasRecordTriggers` — record-action trigger vocabulary (on the `RecordAction` wrapper)
- `Concerns\HasGestures` — the table's side of the gesture layer

Gesture layer (which desktop pointer/keyboard gestures a table offers — OPT-IN):

- `Support\TableGestures` — the canonical vocabulary: `keyboard` (3-state), `rangeSelection`, `dragSelect`, `contextMenu`, `shortcutHelp`, `fillHandle`; `defaults()` (shipped: keyboard + dragSelect OFF) / `all()` / `none()` / `fromConfig()`
- `Concerns\HasGestures` — `Table::gestures(bool|Closure|TableGestures)`, `getGestures()`, and the effective readers `usesDragSelect()` / `usesRangeSelection()` / `usesShortcutHelp()` / `usesActiveRowMarker()` / `getGestureConfig()`
- Consumers to keep honest: `Table::usesGridSemantics()`, `mountsRecordActionController()`, `hasRowContextMenu()`, `isFillHandleEnabled()`, `Support\TableShortcutLegend`
- Project default: `config('wire-table.defaults.gestures')` — `null` shipped default / `true` all / `false` none / map
- Rule: a capability is a **permission, not a trigger**, and an explicitly declared record action is outside the layer (only `onKey()` needs the keyboard)

Record actions (whole-row interaction — click/dblclick/right-click/keyboard):

- `Support\RecordAction` — the binding (wraps/refs an `Action`, holds triggers); fluent `Action::make()->onDoubleClick()` promotes to it via macros registered in `WireTableServiceProvider`
- `Support\RecordTrigger` — trigger value object (open registry: click/dblclick/contextmenu/key/custom)
- `Support\ResolvedRecordAction` — normalized binding the runtime/view consume
- `Actions\RecordActionResolver` — trigger→name pointer map, context-menu + row-button contributions, keyboard primary/secondary/shortcuts
- JS controller: `packages/table/resources/js/record-actions.js` → `wireRecordActions` (one per `<tbody>`); delivered via `packages/table/dist/wire-table-records.js` + `partials/record-actions-assets`
- Deprecated alias: `Table::rowContextMenu()` → prefer `recordAction()->onContextMenu()`
- Mobile fallback: `Table::getMobileRowActionsForDisplay()` / `hasMobileActions()` / `recordActionButtonsOnMobile(bool)` — behaviour-only bindings render as ordinary buttons on a stacked card (copies with `HasKeyboardShortcut::withoutKeyboardShortcut()`, since a rendered button binds its shortcut as a *window* listener)

Services:

- `Services\TableQueryService` — the table-to-core query seam

State:

- `Livewire\TableStateSynthesizer`

Exports:

- classes under `packages/table/src/Export/`
- PDF view: `packages/table/resources/views/export/pdf.blade.php`

Main table views:

- `packages/table/resources/views/tables/index.blade.php`
- action views under `tables/actions/`
- partials under `tables/partials/`

High-value partials:

- `action-modal`
- `halt-modal`
- `pagination`
- `filter-indicators`
- `summary-footer`
- `group-header`
- `group-subtotal`
- `sub-rows`
- `sub-row-toggle`
- `sub-rows-toolbar`
- `polling-indicator`

## Sortable

Path: `packages/sortable/src`

Classes:

- `WireSortableServiceProvider`
- `SortablePlugin`
- `SortableTable`
- `Concerns\WithSortable`
- `Models\ReorderableColumnOrder`

Views:

- `packages/sortable/resources/views/tables/index.blade.php`
- `packages/sortable/resources/views/partials/scripts.blade.php` — a thin `@assets`
  wrapper: the bundle's `<script>` tag (plus the optional `sortablejs_cdn` tag) and
  the `.wire-sortable-*` drag CSS. The `wireSortable` Alpine component itself lives
  in the bundle, not here.

Assets:

- `packages/sortable/resources/js/sortable.js` → `packages/sortable/dist/wire-sortable.js`
  (`npm run build:sortable-assets`; SortableJS compiled in), declared as a toolkit
  asset entry with the `wire-sortable.asset` route behind it as fallback

## Test Locations

Core:

- `packages/core/tests/Unit/Actions/`
- `packages/core/tests/Unit/Foundation/`
- `packages/core/tests/Unit/Modals/`
- `packages/core/tests/Unit/Notifications/`
- `packages/core/tests/Unit/Widgets/`
- `packages/core/tests/Unit/Audit/`

Forms:

- `packages/forms/tests/Unit/Components/`
- `packages/forms/tests/Unit/Config/`
- `packages/forms/tests/Unit/Runtime/`
- `packages/forms/tests/Unit/Validation/`
- `packages/forms/tests/Unit/Integration/`
- `packages/forms/tests/Standalone/`

Table:

- `packages/table/tests/Unit/Columns/`
- `packages/table/tests/Unit/Filters/`
- `packages/table/tests/Unit/Concerns/`
- `packages/table/tests/Unit/Export/`
- `packages/table/tests/Unit/Notifications/`

Sortable:

- `packages/sortable/tests/Unit/`

Cross-package:

- `tests/Integration/`

