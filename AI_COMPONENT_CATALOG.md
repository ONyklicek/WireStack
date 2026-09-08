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

### Value Objects

- `MoneyFormat` — the currency vocabulary (precision, separators, placement) plus `format()`/`parse()`/minor units. Read by `FormatsState::money()` (every `TextColumn`, `MoneyColumn`, `TextEntry`), `WireForms\Components\MoneyInput` and `TextInputColumn::money()`
- `DialingCodes` / `DialingCode` — the dialling-code table, prefix matching and how a number is written. Read by `WireForms\Components\PhoneInput`, `WireTable\Columns\PhoneColumn` and the phone rule
- `ChangeSet` — a before/after diff as display rows, and the one rule for how a stored value reads once it is a change (`null` stays null so a renderer can say *(empty)*, a boolean is `true`/`false` not `1`, an array is readable JSON). Read by `Infolists\Components\ChangesEntry`, the `wire-core::audit.trail` slide-over and `WireModuleAudit\Support\Changes`. In Foundation because `Audit` and `Infolists` are both L2 and may not see each other
- `PollDirective`, `ShortcutHint`

### Mentions

- `Foundation\Mentions\MentionRenderer` — the canonical owner for reading rich text back. A stored mention is an identity (`data-mention-type` + `data-id`, written from `getMorphClass()`), never a name or a link, so this resolves every one of them on every render: one query per stored *type*, and content with no mentions is returned byte-for-byte. Read by `<x-wire::rich-content>`, `Infolists\Components\HtmlEntry` and `WireTable\Columns\TextColumn::richContent()`. Never print editor output any other way once a field declares mentions
- `Foundation\Mentions\Contracts\Mentionable` — how a model you own says its own fresh label and URL. `Foundation\Mentions\MentionRegistry` is the escape hatch for models you do not, and is also where viewer-scoped visibility belongs (`modifyQueryUsing()`): an excluded record is simply not found, so *deleted* and *not allowed to see* share the one path that leaks nothing
- `Foundation\Support\MorphedModels` — the model class behind a stored polymorphic type, class name or morph alias. One owner for a question every reader of a morph-typed column asks; `WireModuleAudit\Support\AuditedRecords` delegates to it
- `Foundation\Contracts\ResolvesRecordUrls` — "where can this record be read", asked from Foundation without reaching up at `Core\Resources`. Answered by `Core\Resources\ResourceRecordUrls` (a resource's own view page, then its edit page), rebindable by an application that routes its own public screens

### Concerns

Use these before creating local field/column/action helpers:

- `BelongsToComponent`
- `CanBeLive`
- `CanBeNullable`
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

- `Foundation\Contracts\HasAvatar` — a record that can show a picture of itself.
  The shell asks this and never a module, so an application resolving Gravatar or
  an identity provider is drawn the same as one storing a path
- `Foundation\Contracts\HasIcon`
- `Foundation\Contracts\HasLabel`
- `Foundation\Contracts\HasVisibility`

Chrome the shell renders for packages that cannot reach into its layout:

- `Foundation\View\PageChrome` — `add($view)` for the end of the document
  (`BODY`, the default: modals, hosts), `add($view, PageChrome::TOPBAR)` for the
  top bar (things that have to be *seen*: a team switcher, a tenant picker),
  `add($view, PageChrome::USER_MENU, sort: 10)` for the signed-in user's own menu
  (the users module's profile link, the auth module's sign-out). One registry,
  three regions; lower sorts first, equal sorts in registration order, because
  provider order is composer's discovery order and not a contract
- `Foundation\View\MenuItem` — `<x-wire::menu-item :href icon type>`, one row of
  that menu: an `href` makes it a link, no `href` makes it the submit button of
  the form around it. In core rather than the shell because two packages outside
  the shell contribute rows; `<x-wire-admin::menu-item>` delegates here

Registration and routing (ADR 0026 — one seam for the menu, the router and the
search palette; **never inject a registry into a new surface**):

- `Foundation\Registration\Catalog` — everything registered, whatever kind it is;
  `implementing($contract)` is how each surface filters to its own opt-in
- `Foundation\Registration\Contracts\RegistrySource` — how a registry joins it
- `Foundation\Registration\Contracts\HasRegistryKey` — the key everything is addressed by
- `Foundation\Routing\Contracts\ProvidesPages` — which components render this
- `Foundation\Routing\Contracts\ConfiguresRoutes` — its own prefix/domain/middleware
- `Foundation\Routing\RoutePage` — one page's permission, middleware or URI
- `Foundation\Routing\Contracts\ResolvesPageUrls` — where a key's page is;
  `UnroutedPageUrls` answers null, `wire-panels` answers for real
- `Foundation\Routing\Contracts\RegistersPageRoutes` — called by core once the
  registries are full, so config-driven routing cannot read an empty catalogue
- `Foundation\Routing\Zone` — which mount point a page is in, as a route-name
  prefix (ADR 0027). `current()` is a **full-page-render** call: it answers
  nothing during a Livewire update, so read it in `mount()` and keep it in a
  public property rather than asking again

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
  `servedByRoute($package)` for the `hasAssetFallback()` resolver, plus
  `serve($package, $dist)` — the `{package}.asset` route that answers it, owned once
  instead of copied into each provider
- `Foundation\View\FloatingAssets` — the dropdown bundle's URL, by the name a dozen
  partials already ask for it
- `Foundation\View\Sparkline` — a numeric series as SVG polyline geometry (`of()` /
  `points()` / `viewBox()`), shared by the stats widget and `WireTable\Columns\MetricColumn`

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
- `Infolists\Components\HtmlEntry` — stored rich text printed as markup, with its mentions resolved through `Foundation\Mentions\MentionRenderer`. Its own entry rather than a flag on `TextEntry`, because a text entry escapes and must keep escaping; never render-memoised, since a resolved mention belongs to the row it was looked up for
- `Infolists\Components\ChangesEntry` — a before/after diff as **one** table, a row per field, over `Foundation\ValueObjects\ChangeSet`. Takes either the `{old, new}` map an audit entry produces or rows already shaped `{field, before, after}`; `dense()` for a diff inside a slide-over. Never draw a diff as a `RepeatableEntry` of labelled cards — that repeats three headings per row
- `Actions\Concerns\InteractsWithActions` — canonical, form-agnostic action runtime (payload resolver, pipeline, halt/notification/redirect, infolist actions). Composed by `WithTable` and by the standalone `WithActions` host.
- `Actions\Concerns\InteractsWithHalt` — "stop, ask, continue" on its own, for a component with no actions at all: the halt state bag, `halt($halt, then: 'method')`, `submitHaltModal()`, `closeHaltModal()`, and a resume that is a method name because a closure does not cross a request. Composed by `WithActions`, which overrides only the resume (a halted *action* is re-run instead). Rendered by `<x-wire-actions::halt-host>`, which the action modal host includes.

Action views/components:

- `Actions\View\ButtonComponent`, `BulkButtonComponent`, `GroupComponent`, `ModalHostComponent`, `HaltHostComponent`
- `packages/core/resources/views/actions/button.blade.php`
- `packages/core/resources/views/actions/bulk-button.blade.php`
- `packages/core/resources/views/actions/group.blade.php`
- `packages/core/resources/views/actions/dropdown-item.blade.php`
- `packages/core/resources/views/actions/modal-host.blade.php` (+ `partials/modal-host-*`)
- `packages/core/resources/views/actions/halt-host.blade.php` (+ `partials/halt-modal.blade.php`)
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
- `Foundation\Concerns\HasModalProperties` (used by Modals *and* `Actions\ActionHalt`)

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
- `Resources/`: `ResourceRegistry`, `Workspace`, `DescribesResource` /
  `ProvidesNavigation` contracts, `NavigationItem` (which carries a `url()`,
  filled by `Workspace` from `ResolvesPageUrls`)
- `Workflow/`: `WorkflowState` (the transition seam, ADR 0018)
- `State/`: state container, hydrator, serializer, dirty tracking, path resolver
- `Validation/`: validation pipeline and result

## Core Global Search

Path: `packages/core/src/GlobalSearch`

- `GlobalSearch`: the search over every registered resource (per-resource cap,
  LIKE escaping, per-record policy check)
- `GlobalSearchPalette`: the ⌘K Livewire component (`wire-global-search`)
- `GlobalSearchResult`: one already-resolved row
- `Contracts/GloballySearchable`: the per-resource opt-in

Docs: `docs/core/global-search.md`.

## Core Widgets And Audit

Widgets:

- `Widget`
- `ChartWidget` (JS / Chart.js)
- `BarChartWidget` (pure-CSS bar chart)
- `ChartItem` (bar entry for `BarChartWidget`)
- `CustomWidget`
- `Stat`
- `StatsOverviewWidget`
- `ProgressWidget` (pure-CSS progress rows)
- `ProgressItem` (row entry for `ProgressWidget`)
- `ListWidget` (pure-CSS feed of records/events)
- `ListItem` (entry for `ListWidget`)
- `TableWidget`
- `Widgets\Concerns\HasPolling`
- `Widgets\Concerns\CanBeLazy`
- `Widgets\Concerns\HasWidgetFilter`
- `Widgets\Concerns\HasWidgetItems`
- `Widgets\Concerns\WithWidgets`
- `Widgets\Console\MakeDashboardCommand`, `Widgets\Console\MakeWidgetCommand`
- `Foundation\Contracts\RunsComponentActions` (the Widgets↔Actions seam)
- `Actions\Support\ComponentActionRunner`, `Actions\Support\ActionCallbackInvoker`

Widget views:

- `packages/core/resources/views/widgets/chart.blade.php`
- `packages/core/resources/views/widgets/bar-chart.blade.php`
- `packages/core/resources/views/widgets/bar-chart/vertical-finance.blade.php`
- `packages/core/resources/views/widgets/bar-chart/vertical-system.blade.php`
- `packages/core/resources/views/widgets/bar-chart/horizontal-system.blade.php`
- `packages/core/resources/views/widgets/custom.blade.php`
- `packages/core/resources/views/widgets/stats-overview.blade.php`
- `packages/core/resources/views/widgets/progress.blade.php`
- `packages/core/resources/views/widgets/list.blade.php`
- `packages/core/resources/views/widgets/table.blade.php`
- `packages/core/resources/views/widgets/widget-grid.blade.php`
- `packages/core/resources/views/widgets/widget-cell.blade.php`
- `packages/core/resources/views/widgets/partials/widget-header.blade.php`
- `packages/core/resources/views/widgets/partials/widget-filter.blade.php`
- `packages/core/resources/views/widgets/partials/widget-actions.blade.php`
- `packages/core/resources/views/widgets/partials/widget-placeholder.blade.php`
- `packages/core/resources/views/widgets/partials/list-item.blade.php`

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
- `CheckboxList` — `searchable()`, `bulkToggleable()` (both toggles act on what the search left, and leave the rest of the selection alone), `groups()` and `showSelected()` (the chosen options as removable chips above the list, read off the entangled state so a filter cannot hide them). Reach for it over a multiple `Select` wherever the question is "what else is there" rather than "what did I choose" — a permission list is the type case
- `CodeEditor`
- `ColorPicker`
- `DateRangePicker` (a LayoutComponent composing two `DateTimePicker`s over two columns)
- `DateTimePicker`
- `FileUpload`
- `Hidden`
- `KeyValue`
- `MarkdownEditor`
- `MoneyInput` (extends `TextInput`; currency vocabulary from `Foundation\ValueObjects\MoneyFormat`)
- `MorphToSelect`
- `OtpInput`
- `PhoneInput` (dialling-code select + national number over one E.164 value; `Foundation\ValueObjects\DialingCodes`)
- `Radio`
- `Rating`
- `Repeater` (card layout, or `table()` for row layout)
- `Builder` (extends `Repeater`; per-item `Block` type) + `Block`
- `RichEditor`
- `Select`
- `SignaturePad` (pointer-drawn canvas; data URI, or a PNG on a disk via `storeOn()`)
- `Slider`
- `Tags`
- `TextInput` (`nullable()` from `Foundation\Concerns\CanBeNullable`; a `type=number` input nullifies an emptied value without being asked)
- `Textarea`
- `TimePicker` (mode-locked `DateTimePicker`; slot-list panel, own view)
- `TiptapEditor` (`mentions(Mention...)` — `@`/`#` triggers, each standing for one *or several* models via `Mention\Source`; the document stores a morph type + id, read back by `MentionRenderer`. Third ESM entry, loaded only when declared)
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
- `Runtime\StateDehydrator` — what a field's state becomes on the way out, for every host (a save, and an action modal via `dehydrateMountedActionFormData()`)
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

- `Column` — cell rendering, state resolution and per-record visibility. Every
  other capability is a named concern it composes, listed below.

Column capabilities (`packages/table/src/Concerns`, each `@phpstan-require-extends Column`):

- `CanBeSearchable` · `CanBeSorted` — what the search box and the header sort
  reach, read by `Services\TableQueryService`
- `CanBeFiltered` (+ `Services\ColumnFilterFactory`) — column-level filters
- `HasAggregate` — the `counts()`/`sums()`/`averages()`/`mins()`/`maxes()`
  rollup triple; applied by `Services\AggregateSubqueries`
- `CanBeEdited` — inline-edit config and its two gates; the write itself is
  `Services\CellEditPipeline` + `Services\CellValueWriter`
- `HasResponsive` — everything about the column across viewport widths:
  breakpoint visibility, the `onlyOn*` shortcuts, per-width content closures
- `HasMobileSlot` — the column's slot in the stacked mobile card
  (`Support\MobileCard`; host side is `Concerns\StacksOnMobile`)
- `HasAlignment` · `HasWidth` · `CanBeTruncated` · `HasTextStyling` ·
  `HasDescription` — cell presentation
- `CanBeSummarized` — see Summaries below

Composed from core Foundation rather than re-implemented: `HasColor`,
`HasDefault`, `HasFontWeight`, `HasIcon`, `HasPlaceholder`, `HasSize`,
`HasTooltip`, `HasVisibility` (which brings `HasAuthorization`), `CanBeCopyable`
(the column widens only `copyable()`, to carry a confirmation message).
`CanBeDisabled` is deliberately absent — a column is never disabled.

Columns:

- `TextColumn`
- `MoneyColumn` — `TextColumn` with money's defaults: right-aligned (so `MobileCard` picks it as the stacked card's metric), `tabular-nums`, no wrap. Formatting stays in `Foundation\Concerns\FormatsState::money()`; the figure defaults are `Concerns\RendersAsFigure`, shared with `MetricColumn`. There is **no `StatusColumn`** — `BadgeColumn` already resolves an enum's color, icon and label through `EnumResolver`
- `MetricColumn` — an aggregate figure (dot notation already does the `withCount`/`withSum`) plus an optional per-record trend, drawn by `Foundation\View\Sparkline`
- `PhoneColumn` — `TextColumn` that writes a stored E.164 number the way `PhoneInput` does and links it as `tel:`; the grammar is `Foundation\ValueObjects\DialingCodes`, shared by both
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
- `Concerns\HasTableActions` — which actions a table carries (row, bulk, header, empty state) and how the actions column presents them: position, alignment, label, width, `solid`/`quiet` style, `stickyActions()`, the composition rule in `composeRowActions()`, and the compiled `getActionCellSkeleton()`
- `Support\StickyColumn` — the canonical owner of a column pinned against the horizontal scroll: `on(string $side)` / `none()` / `forActions(Table)`, the two z tiers (`z-[1]` body, `z-10` header), and `layers()` — the opaque surface plus the two `bg-inherit` layers that carry the row's stripe, hover and selection into the pinned cell without a second colour vocabulary. Consumed by five surfaces through `Support\ActionRenderPlan`; `Column::sticky()` is its intended second consumer
- `Concerns\CollapsesActionsOnMobile` — the phone's half of the same feature: the row, header and sub-row folds into one `ActionGroup` dropdown, the counting rules that decide whether to fold, and the breakpoint classes that swap the two halves
- `Concerns\StacksOnMobile` — `stackedOnMobile()`, the `mobileCard()` override hook, the per-column-set memo of `getMobileCard()`, the two literal breakpoint classes that swap table for cards, `getRowCardClasses()`, and `getMobileCardSkeleton()` (compiled per shape, keyed by `MobileCard::shapeSignature()`). The slot vocabulary itself lives in `Support\MobileCard` / `Support\MobileCardConfig`; the fill in `Support\CardRenderer`
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

Host concerns (composed into `Concerns\WithTable`, one feature each):

- `Concerns\CanSelectRecords`, `Concerns\CanExpandSubRows`, `Concerns\CanFillCells`
- `Concerns\CanGroupRecords` — the host's half of grouping: `applyGroupOrdering()` (prepends the group order so every other sort applies within a group; stands aside when the viewer sorts by the group column), `tableHasGroupSummaries()`, `computeGroupSummaries()` (in memory, `query`/`page` scopes only) and `getGroupRecords()` over `Support\GroupPartitions`
- `Concerns\InteractsWithTableActions`, `Concerns\InteractsWithTableModals`

Services:

- `Services\TableQueryService` — the table-to-core query seam

Support:

- `Support\GroupPartitions` — the page split by normalised group key, in page order. Carries the identity of the record set it split (`describes()`), so paging inside one request cannot leave subtotals describing the page before

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

