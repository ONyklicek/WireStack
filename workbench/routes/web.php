<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Core\Resources\Workspace;
use Workbench\App\Livewire\Previews\CorePreview;
use Workbench\App\Livewire\Previews\FieldPreview;
use Workbench\App\Livewire\Previews\FormPreview;
use Workbench\App\Livewire\Previews\GestureLabPreview;
use Workbench\App\Livewire\Previews\InfolistPreview;
use Workbench\App\Livewire\Previews\LayoutPreview;
use Workbench\App\Livewire\Previews\ModalStackingPreview;
use Workbench\App\Livewire\Previews\PanelPreview;
use Workbench\App\Livewire\Previews\SortablePreview;
use Workbench\App\Livewire\Previews\SpaPlainPreview;
use Workbench\App\Livewire\Previews\SpaTablePreview;
use Workbench\App\Livewire\Previews\TablePreview;
use Workbench\App\Livewire\Previews\WidgetPreview;
use Workbench\App\Livewire\Resources\CreateInvoice;
use Workbench\App\Livewire\Resources\EditInvoice;
use Workbench\App\Livewire\Resources\ListDocuments;
use Workbench\App\Livewire\Resources\ListInvoices;
use Workbench\App\Livewire\Resources\ListTasks;
use Workbench\App\Livewire\Resources\ViewInvoice;

// One source of truth for the preview surface: every entry below registers its
// own route *and* is listed on the /previews index. A new variant needs a line
// here and nothing else — the index cannot fall behind the routes again.
$screens = [
    'forms-overview' => ['title' => 'Wire Forms', 'subtitle' => 'Schema-driven form layout preview.', 'component' => FormPreview::class, 'variant' => 'overview'],
    'forms-tabs' => ['title' => 'Wire Forms Tabs', 'subtitle' => 'Tabbed form layout.', 'component' => FormPreview::class, 'variant' => 'tabs'],
    'forms-wizard' => ['title' => 'Wire Forms Wizard', 'subtitle' => 'Standalone multi-step wizard layout.', 'component' => FormPreview::class, 'variant' => 'wizard'],
    'forms-wizard-live' => ['title' => 'Wire Forms Wizard (Live)', 'subtitle' => 'Per-step validation, dynamic steps, live select with create-option.', 'component' => FormPreview::class, 'variant' => 'wizard-live'],
    'forms-option-wizard' => ['title' => 'Wire Forms Option Wizard', 'subtitle' => 'A wizard inside a create-option modal, its navigation handed to the modal footer.', 'component' => FormPreview::class, 'variant' => 'option-wizard'],
    'forms-repeater' => ['title' => 'Wire Forms Repeater', 'subtitle' => 'Focused nested repeater preview.', 'component' => FormPreview::class, 'variant' => 'repeater'],
    'forms-repeater-table' => ['title' => 'Wire Forms Repeater (table layout)', 'subtitle' => 'The same repeater laid out as rows under one header: one column per schema field, the per-cell label hidden.', 'component' => FormPreview::class, 'variant' => 'repeater-table'],
    'forms-builder' => ['title' => 'Wire Forms Builder', 'subtitle' => 'Heterogeneous content: every item picks its own block type from the add picker and is edited with that block\'s schema.', 'component' => FormPreview::class, 'variant' => 'builder'],
    'forms-enum-defaults' => ['title' => 'Wire Forms Enum Defaults', 'subtitle' => 'Create-mode defaults: an enum-instance default, a clearable enum select, and a numeric default.', 'component' => FormPreview::class, 'variant' => 'enum-defaults'],
    'forms-default-on-null' => ['title' => 'Wire Forms defaultOnNull', 'subtitle' => 'Edit mode with an all-null record: only ->defaultOnNull() fields resurrect their default; a plain default keeps the null.', 'component' => FormPreview::class, 'variant' => 'default-on-null'],
    'table-overview' => ['title' => 'Wire Table', 'subtitle' => 'Live table preview with search, filters, and actions.', 'component' => TablePreview::class, 'variant' => 'overview'],
    'table-record-actions' => ['title' => 'Wire Table Record Actions', 'subtitle' => 'Double-click a row to open it, right-click for the context menu — one delegated controller.', 'component' => TablePreview::class, 'variant' => 'record-actions'],
    'table-record-actions-dual' => ['title' => 'Wire Table Record Actions (click + double-click)', 'subtitle' => 'Both gestures bound: a double-click must run only the double-click action, never the deferred single-click one.', 'component' => TablePreview::class, 'variant' => 'record-actions-dual'],
    'table-record-actions-keyboard' => ['title' => 'Wire Table Record Actions (keyboard)', 'subtitle' => 'Arrow-key navigation: ↑/↓ move the active row, Enter / Shift+Enter run the primary and secondary action, Space and Shift+↑/↓ select, Delete fires an onKey() binding.', 'component' => TablePreview::class, 'variant' => 'record-actions-keyboard'],
    'table-selection' => ['title' => 'Wire Table Selection', 'subtitle' => 'Selected-record state with bulk toolbar and active filters.', 'component' => TablePreview::class, 'variant' => 'selection'],
    'table-selection-gestures' => ['title' => 'Wire Table Selection Gestures', 'subtitle' => '40 rows on one page: checkbox anchors, Shift+arrow ranges, mod+A, keyboard-driven record actions.', 'component' => TablePreview::class, 'variant' => 'selection-gestures'],
    'table-selection-gestures-paged' => ['title' => 'Wire Table Selection Gestures (paged)', 'subtitle' => 'The same 40 rows split 20 a page: page-scoped gestures and the select-all-matching escalation.', 'component' => TablePreview::class, 'variant' => 'selection-gestures-paged'],
    'table-gestures-poll' => ['title' => 'Wire Table Gestures + poll()', 'subtitle' => 'The gesture table under a 1s poll that renders every tick: a half-typed search box, an open context menu, and the Stop control — all the client state a morph can stomp.', 'component' => TablePreview::class, 'variant' => 'gestures-poll'],
    'table-gestures-live' => ['title' => 'Wire Table Gestures + live()', 'subtitle' => 'The same table under live(): change detection skips the render, but the tick still carries a snapshot back — the half-typed search box is what that can stomp.', 'component' => TablePreview::class, 'variant' => 'gestures-live'],
    'table-gestures-live-broadcast' => ['title' => 'Wire Table Gestures + live(broadcast)', 'subtitle' => 'live() with the broadcast bridge: the poll wrapper is also an Alpine root, so pausing removes an x-data element wrapped around the whole table.', 'component' => TablePreview::class, 'variant' => 'gestures-live-broadcast'],
    'table-gestures-poll-url' => ['title' => 'Wire Table Gestures + poll() + queryString()', 'subtitle' => 'The everyday shape: paginated, polling, and the search term mirrored into the URL — the one piece of state read back from outside the component.', 'component' => TablePreview::class, 'variant' => 'gestures-poll-url'],
    'table-selection-only' => ['title' => 'Wire Table Selection Only', 'subtitle' => 'Selectable without any record action: the variant that proves grid semantics attach to selectable() itself.', 'component' => TablePreview::class, 'variant' => 'selection-only'],
    'table-stacked-selection' => ['title' => 'Wire Table Stacked Selection', 'subtitle' => 'Card layout with the always-visible select-all strip, the select-all-matching escalation, and mobile sorting.', 'component' => TablePreview::class, 'variant' => 'stacked-selection'],
    'table-subrows' => ['title' => 'Wire Table Sub-rows', 'subtitle' => 'Expandable invoice line items with sortable headers, row actions, and a subtotal.', 'component' => TablePreview::class, 'variant' => 'subrows'],
    'table-summary' => ['title' => 'Wire Table Summary', 'subtitle' => 'Rollup totals, a multi-aggregate footer, and the page/all scope toggle.', 'component' => TablePreview::class, 'variant' => 'summary'],
    'table-row-color' => ['title' => 'Wire Table Row Color', 'subtitle' => 'Conditional whole-row tint by record status, plus an emphasised row class.', 'component' => TablePreview::class, 'variant' => 'row-color'],
    'table-collapsible-groups' => ['title' => 'Wire Table Collapsible Groups', 'subtitle' => 'Fold a group away: its rows are not rendered at all, so every row gesture still sees one consistent list.', 'component' => TablePreview::class, 'variant' => 'collapsible-groups'],
    'table-saved-views' => ['title' => 'Wire Table Saved Views', 'subtitle' => 'Save the current sort, search, filters and columns under a name, then switch between them.', 'component' => TablePreview::class, 'variant' => 'saved-views'],
    'table-column-filters' => ['title' => 'Wire Table Column Filters', 'subtitle' => 'Per-column header filters: text, single-select, multi-select, and boolean.', 'component' => TablePreview::class, 'variant' => 'column-filters'],
    'table-empty-state' => ['title' => 'Wire Table Empty State', 'subtitle' => 'The empty state with a way out of it: a link action, and one that opens a modal form — record-less, and repeated in the stacked card layout.', 'component' => TablePreview::class, 'variant' => 'empty-state'],
    'table-column-surfaces' => ['title' => 'Wire Table Column Surfaces', 'subtitle' => 'ColorColumn (swatch, and a value it refuses to draw), RatingColumn with halves, TagsColumn with a "+N" overflow chip, and an inline-editable CheckboxColumn.', 'component' => TablePreview::class, 'variant' => 'column-surfaces'],
    'table-trashed-filter' => ['title' => 'Wire Table Trashed Filter', 'subtitle' => 'The soft-delete scope switch: live records, live + deleted, or deleted only.', 'component' => TablePreview::class, 'variant' => 'trashed-filter'],
    'table-image-gallery' => ['title' => 'Wire Table Image Gallery', 'subtitle' => 'ImageColumn: single image, an array as a gallery, and a stacked one capped with a "+N" chip.', 'component' => TablePreview::class, 'variant' => 'image-gallery'],
    'table-editable-fill' => ['title' => 'Wire Table Editable + Fill', 'subtitle' => 'Inline-editable text, select and toggle cells with the Excel-style fill handle. Email opts out via ->fillable(false).', 'component' => TablePreview::class, 'variant' => 'editable-fill'],
    'table-editable-fill-selectable' => ['title' => 'Wire Table Editable + Fill + Selection', 'subtitle' => 'The editable/fill table with selectable() on: the fill root nests inside the selection root and both gestures drag over the same rows.', 'component' => TablePreview::class, 'variant' => 'editable-fill-selectable'],
    'table-editable-fill-paged' => ['title' => 'Wire Table Editable + Fill + Pagination', 'subtitle' => 'Inline editing next to a page-size select: one Livewire commit carries both a cell edit (which skips the render) and the per-page change (which needs one).', 'component' => TablePreview::class, 'variant' => 'editable-fill-paged'],
    'table-editable-row-partials' => ['title' => 'Wire Table Row Partials', 'subtitle' => 'Table::rowPartials(): a cell save answers with that row alone, not the data region — the win islands cannot reach, since an island cannot be named per record.', 'component' => TablePreview::class, 'variant' => 'editable-row-partials'],
    'table-editable-live' => ['title' => 'Wire Table Live (multi-user)', 'subtitle' => 'live(broadcast: true): the poll keeps other sessions current, the broadcast bridge decides how soon. A stubbed window.Echo is what a driver can hold on to.', 'component' => TablePreview::class, 'variant' => 'editable-live'],
    'table-subrows-flatten' => ['title' => 'Wire Table Flatten', 'subtitle' => 'Flatten mode rendering every child as a regular row.', 'component' => TablePreview::class, 'variant' => 'subrows-flatten'],
    'table-subrows-limit' => ['title' => 'Wire Table Show More', 'subtitle' => 'Limited child rows with the "show more" affordance.', 'component' => TablePreview::class, 'variant' => 'subrows-limit'],
    'table-subrows-filter' => ['title' => 'Wire Table Sub-row Filters', 'subtitle' => 'Per-child interactive filter bar above the sub-row table.', 'component' => TablePreview::class, 'variant' => 'subrows-filter'],
    'table-modal-form' => ['title' => 'Wire Table Action Modal', 'subtitle' => 'Header-action form modal (default responsive dialog).', 'component' => TablePreview::class, 'variant' => 'modal-form'],
    'table-modal-slideover-mobile' => ['title' => 'Wire Table Modal · slideOverOnMobile', 'subtitle' => 'Form modal that renders as a bottom-sheet on mobile.', 'component' => TablePreview::class, 'variant' => 'modal-slideover-mobile'],
    'table-modal-slideover-mobile-tablet' => ['title' => 'Wire Table Modal · slideOverOnMobile · tablet', 'subtitle' => 'Same modal as a sheet up to md (tablet breakpoint).', 'component' => TablePreview::class, 'variant' => 'modal-slideover-mobile-tablet'],
    'table-modal-slideover-compose' => ['title' => 'Wire Table Modal · slideOver + slideOverOnMobile', 'subtitle' => 'Desktop slide-over that becomes a mobile bottom-sheet.', 'component' => TablePreview::class, 'variant' => 'modal-slideover-compose'],
    'table-modal-slideover-compose-tablet' => ['title' => 'Wire Table Modal · compose · tablet', 'subtitle' => 'Compose slide-over as a sheet up to md.', 'component' => TablePreview::class, 'variant' => 'modal-slideover-compose-tablet'],
    'table-modal-fullscreen-mobile' => ['title' => 'Wire Table Modal · fullScreenOnMobile', 'subtitle' => 'Form modal that fills the viewport on mobile.', 'component' => TablePreview::class, 'variant' => 'modal-fullscreen-mobile'],
    'table-modal-wizard' => ['title' => 'Wire Table Wizard Modal', 'subtitle' => 'Multi-step action modal with step indicator and wizard footer.', 'component' => TablePreview::class, 'variant' => 'modal-wizard'],
    'table-modal-nested' => ['title' => 'Wire Table Nested Modal', 'subtitle' => 'A footer action stacks a second modal on top of the first.', 'component' => TablePreview::class, 'variant' => 'modal-nested'],
    'table-actions-quiet' => ['title' => 'Wire Table Quiet Actions', 'subtitle' => 'Quiet row actions: neutral at rest, colour on hover/focus, a solid Approve, and a legible Delete.', 'component' => TablePreview::class, 'variant' => 'actions-quiet'],
    'table-actions-group' => ['title' => 'Wire Table Action Group', 'subtitle' => 'Row actions collapsed into a dropdown group.', 'component' => TablePreview::class, 'variant' => 'actions-group'],
    'table-actions-group-lazy' => ['title' => 'Wire Table Action Group · lazy', 'subtitle' => 'Action group whose menu is built client-side on first open (lazyMenu()).', 'component' => TablePreview::class, 'variant' => 'actions-group-lazy'],
    'table-actions-group-tablet' => ['title' => 'Wire Table Action Group · tablet', 'subtitle' => 'Action group as a sheet up to md (tablet breakpoint).', 'component' => TablePreview::class, 'variant' => 'actions-group-tablet'],
    'table-stacked-actions-collapse' => ['title' => 'Wire Table Stacked · collapse actions', 'subtitle' => 'Mobile stacked cards that collapse three row actions into one dropdown (collapseActionsOnMobile).', 'component' => TablePreview::class, 'variant' => 'stacked-actions-collapse'],
    'table-stacked-actions-collapse-two' => ['title' => 'Wire Table Stacked · collapse two actions (lazy)', 'subtitle' => 'Lazy-loaded stacked cards with only two row actions, collapsed at threshold 1 — guards the Alpine bundles reaching a lazy() table.', 'component' => TablePreview::class, 'variant' => 'stacked-actions-collapse-two'],
    'table-header-actions-collapse' => ['title' => 'Wire Table Toolbar · collapse header actions', 'subtitle' => 'Three toolbar header actions folded into one dropdown below the mobile breakpoint (collapseHeaderActionsOnMobile).', 'component' => TablePreview::class, 'variant' => 'header-actions-collapse'],
    'table-paginated' => ['title' => 'Wire Table Pagination', 'subtitle' => 'Paginated table with per-page selector and page links.', 'component' => TablePreview::class, 'variant' => 'paginated'],
    'sortable-overview' => ['title' => 'Wire Sortable', 'subtitle' => 'Full reorderable task table preview.', 'component' => SortablePreview::class, 'variant' => 'overview'],
    'sortable-detail' => ['title' => 'Wire Sortable Detail', 'subtitle' => 'Closer reorder-surface preview.', 'component' => SortablePreview::class, 'variant' => 'detail'],
    'sortable-columns' => ['title' => 'Wire Sortable Columns', 'subtitle' => 'Drag a header to reorder columns, on a table that also has a selection column and row handles.', 'component' => SortablePreview::class, 'variant' => 'columns'],
    'sortable-morph' => ['title' => 'Wire Sortable Morph', 'subtitle' => 'A column-reorderable table with a search box and an editable cell — what the drag controller is allowed to keep a Livewire morph from doing.', 'component' => SortablePreview::class, 'variant' => 'morph'],
    'forms-field-partials' => ['title' => 'Wire Forms', 'subtitle' => 'A live field under Form::fieldPartials(): a plain commit answers with nothing, a dependent sibling comes back as a region, and a field appearing falls back to a full render.', 'component' => FormPreview::class, 'variant' => 'field-partials'],
    'sortable-partials' => ['title' => 'Wire Sortable Partials', 'subtitle' => 'Row reordering and rowPartials() on one table: an inline save answers with the row alone, morphed by a path that never reaches the hook the drag handles are rebuilt from.', 'component' => SortablePreview::class, 'variant' => 'partials'],
    'sortable-everything' => ['title' => 'Wire Sortable · every control', 'subtitle' => 'Row handles, draggable headers, search, pagination and a 3s poll on one table: search and paging stay live inside reorder mode, and a drag on page two only permutes page two.', 'component' => SortablePreview::class, 'variant' => 'everything'],
    'gesture-lab' => ['title' => 'Gesture Lab', 'subtitle' => 'Every selection gesture, record action, the shortcut help and column reordering on one table, with a live state read-out.', 'component' => GestureLabPreview::class, 'variant' => 'lab'],
    'gesture-lab-paged' => ['title' => 'Gesture Lab (paged)', 'subtitle' => 'The same lab over 20 rows a page — what "select all matching" needs to mean anything.', 'component' => GestureLabPreview::class, 'variant' => 'paged'],
    'gesture-lab-click' => ['title' => 'Gesture Lab (single click)', 'subtitle' => 'A table whose only record action is a single click opening a modal.', 'component' => GestureLabPreview::class, 'variant' => 'click-only'],
    'gesture-lab-default' => ['title' => 'Gesture Lab (shipped default)', 'subtitle' => 'The same table with no gestures() call at all — what a consumer gets out of the box: checkboxes, the declared record actions and the right-click menu, but no keyboard grid, no ranges and no sweep.', 'component' => GestureLabPreview::class, 'variant' => 'default'],
    'gesture-lab-plain' => ['title' => 'Gesture Lab (gestures off)', 'subtitle' => 'The same table with gestures(false): no keyboard grid, no ranges, no sweep, no right-click menu, no ? help — the declared record actions and the checkboxes stay.', 'component' => GestureLabPreview::class, 'variant' => 'plain'],
    'spa-navigate' => ['title' => 'SPA Navigation · page A', 'subtitle' => 'A page with no table, no dropdown and no sortable surface — none of the package bundles are in this document. It links to page B with wire:navigate.', 'component' => SpaPlainPreview::class, 'variant' => 'plain'],
    'spa-navigate-table' => ['title' => 'SPA Navigation · page B', 'subtitle' => 'Selection, record actions, the fill handle, the context menu and column reordering on one table — every client-side controller at once, first arriving on a wire:navigate.', 'component' => SpaTablePreview::class, 'variant' => 'table'],
    'actions-modal-stacking' => ['title' => 'Wire Actions · Nested Modal Stacking', 'subtitle' => 'Six live configurations of the nested-modal frame stack — create-and-select ($setParent), deep $setFrame, inline registerActions, slide-over, and a stacked wizard.', 'component' => ModalStackingPreview::class, 'variant' => 'gallery'],
    'core-overview' => ['title' => 'Wire Core', 'subtitle' => 'Stats, actions, and shared primitives.', 'component' => CorePreview::class, 'variant' => 'overview'],
    'core-modal' => ['title' => 'Wire Core Modal', 'subtitle' => 'Real modal surface from the core runtime.', 'component' => CorePreview::class, 'variant' => 'modal'],
    'core-dropdown' => ['title' => 'Wire Core Dropdown', 'subtitle' => 'Generic dropdown that becomes a bottom sheet on mobile.', 'component' => CorePreview::class, 'variant' => 'dropdown'],
    'core-toasts' => ['title' => 'Wire Core Toasts', 'subtitle' => 'Toast countdown bar, actions, collapsible stack, max-visible cap, and a11y support.', 'component' => CorePreview::class, 'variant' => 'toasts'],
    'core-open-on' => ['title' => 'Wire Core openOn Modals', 'subtitle' => 'Modal, confirmation, and slide-over opened by a window event — no wire:model binding.', 'component' => CorePreview::class, 'variant' => 'open-on'],
    'widgets-polling' => ['title' => 'Wire Core Widgets', 'subtitle' => 'A four-widget dashboard where one widget polls: the tick must answer with that widget alone, not with the grid around it.', 'component' => WidgetPreview::class, 'variant' => 'polling'],
    'widgets-overview' => ['title' => 'Wire Core Widgets', 'subtitle' => 'Stats overview and a chart widget composed into a dashboard grid.', 'component' => WidgetPreview::class, 'variant' => 'overview'],
    'widgets-chart' => ['title' => 'Wire Core Chart Widget', 'subtitle' => 'A single interactive Chart.js widget with heading and filter.', 'component' => WidgetPreview::class, 'variant' => 'chart'],
    'widgets-bar-chart' => ['title' => 'Wire Core Bar Chart Widget', 'subtitle' => 'Pure-CSS bar chart with finance, system, and horizontal progress modes.', 'component' => WidgetPreview::class, 'variant' => 'bar-chart'],
    'infolists-overview' => ['title' => 'Wire Core Infolist', 'subtitle' => 'Read-only record display with sections, a column grid, and formatted entries.', 'component' => InfolistPreview::class, 'variant' => 'overview'],
    'infolists-entries' => ['title' => 'Wire Core Infolist Entries', 'subtitle' => 'Gallery of every built-in infolist entry type bound to one record.', 'component' => InfolistPreview::class, 'variant' => 'entries'],
    'infolists-order' => ['title' => 'Wire Core Infolist Order Detail', 'subtitle' => 'A real order detail: Flex layout, badge/boolean/list entries, and header/entry/per-row actions.', 'component' => InfolistPreview::class, 'variant' => 'order'],
    'panels-editable' => ['title' => 'Wire Core Editable Panel', 'subtitle' => 'A Model-backed record panel: toggle, select, and text edits write straight to the row with optimistic UI. Read-only email entry mixed in.', 'component' => PanelPreview::class, 'variant' => 'default'],
];

$fieldPreviews = [
    'text-input' => 'Text Input',
    'textarea' => 'Textarea',
    'tiptap' => 'TipTap (core)',
    'tiptap-tables' => 'TipTap (with tables)',
    'tiptap-default' => 'TipTap · default document',
    'rich-editor' => 'Rich Editor',
    'markdown-editor' => 'Markdown Editor',
    'select' => 'Select',
    'select-floating' => 'Select · floating on mobile (opt-out)',
    'select-bp-lg' => 'Select · per-component breakpoint lg',
    'checkbox-list-responsive' => 'CheckboxList · per-breakpoint columns',
    'checkbox' => 'Checkbox',
    'checkbox-list' => 'Checkbox List',
    'radio' => 'Radio',
    'radio-color' => 'Radio Color',
    'radio-sizes' => 'Radio Sizes',
    'radio-segmented' => 'Radio Segmented',
    'radio-buttons' => 'Radio Buttons',
    'toggle' => 'Toggle',
    'color-picker' => 'Color Picker',
    'slider' => 'Slider',
    'tags' => 'Tags',
    'rating' => 'Rating',
    'checkbox-list-choices' => 'Checkbox List · segmented & buttons',
    'otp-input' => 'OTP Input',
    'key-value' => 'Key-Value',
    'date-time-picker' => 'Date-Time Picker',
    'date-time-picker-bounds' => 'Date-Time Picker · min/max bounds',
    'time-picker' => 'Time Picker',
    'file-upload' => 'File Upload',
    'file-upload-auto' => 'File Upload (centre crop)',
];

// Pages that are not a captured component preview but still belong on the index.
$utilityPages = [
    'mobile' => ['Live mobile index', 'Curated phone/tablet walkthrough — open it over the LAN on a real device.'],
    'palette' => ['Colour palette', 'The full Tailwind palette rendered through the canonical HasColor resolvers.'],
    'layout-tags' => ['Layout Blade tags', 'Standalone <x-wire::grid|split|section|fieldset|callout|empty-state>.'],
    'layout-live' => ['Layout tags · live', 'Interactive standalone Tabs + Wizard on a Livewire host.'],
];

// The owner layer on a real entity. Their own routes rather than a $screens
// entry because the capture view mounts every preview with `['variant' => …]`,
// and an edit or view page takes a record key instead — which is the point:
// these are the framework's real pages, not something the workbench wraps.
$resourcePages = [
    'resource-list' => ['Invoices (list)', 'A resource\'s ListPage: columns, search and sort declared once on the resource.', ListInvoices::class, []],
    'resource-create' => ['Invoice (create)', 'CreatePage over the same form() the edit page uses.', CreateInvoice::class, []],
    'resource-edit' => ['Invoice (edit)', 'EditPage seeded from the record, with the line-items relation manager embedded.', EditInvoice::class, ['record' => 1]],
    'resource-view' => ['Invoice (view)', 'ViewPage: a read-only infolist plus the same relation manager.', ViewInvoice::class, ['record' => 1]],
];

// The workspace shell: one sidebar built from Workspace::navigation() over every
// registered resource, and the selected resource's list page beside it. Keyed by
// resource key, because that is what the navigation entries are keyed by and
// what the sidebar turns into a link.
$workspacePages = [
    'invoices' => ListInvoices::class,
    'tasks' => ListTasks::class,
    'documents' => ListDocuments::class,
];

// Pages the workbench serves that are neither a captured component preview nor a
// resource page. They were missing from the index for as long as they existed —
// the palette and all four resource pages — while the index page claimed "every
// route registered by the workbench is listed here", so they are collected here
// rather than left to a route call further down.
$standalonePages = [
    'workspace' => ['Workspace navigation', 'The sidebar an application builds from Workspace::navigation(): declared groups with their own heading, icon and order, sorted entries, badges, and wire:navigate between the resources.'],
    'global-search' => ['Global search palette', 'The ⌘K command palette over every registered resource.'],
];

// Index sections in display order. A screen lands in the first section whose
// prefix its slug matches, so new slugs group themselves.
$previewSections = [
    'forms-' => 'Wire Forms',
    'field-' => 'Form fields',
    'table-' => 'Wire Table',
    'sortable-' => 'Wire Sortable',
    'gesture-lab' => 'Gesture lab',
    'spa-' => 'SPA navigation',
    'actions-' => 'Actions & modals',
    'core-' => 'Wire Core',
    'widgets-' => 'Widgets',
    'infolists-' => 'Infolists',
    'panels-' => 'Panels',
    'resource-' => 'Resources (owner layer)',
    'workspace' => 'Resources (owner layer)',
    'global-search' => 'Wire Core',
    'layout-' => 'Utility pages',
    'palette' => 'Utility pages',
    'mobile' => 'Utility pages',
];

Route::get('/', function (): RedirectResponse {
    return redirect('/previews');
});

Route::get('/previews', function () use ($screens, $fieldPreviews, $utilityPages, $resourcePages, $standalonePages, $previewSections) {
    $rows = [];

    foreach ($screens as $slug => $screen) {
        $rows[$slug] = ['label' => $screen['title'], 'copy' => $screen['subtitle']];
    }

    foreach ($fieldPreviews as $field => $label) {
        $rows['field-'.$field] = [
            'label' => $label,
            'copy' => 'Single '.$label.' field rendered through the Wire Forms runtime.',
        ];
    }

    foreach ($resourcePages as $slug => [$label, $copy]) {
        $rows[$slug] = ['label' => $label, 'copy' => $copy];
    }

    foreach ([...$standalonePages, ...$utilityPages] as $slug => [$label, $copy]) {
        $rows[$slug] = ['label' => $label, 'copy' => $copy];
    }

    $sections = array_fill_keys([...array_values($previewSections), 'Other'], []);

    foreach ($rows as $slug => $row) {
        $section = 'Other';

        foreach ($previewSections as $prefix => $candidate) {
            if (str_starts_with($slug, $prefix)) {
                $section = $candidate;

                break;
            }
        }

        $sections[$section][] = $row + ['slug' => $slug];
    }

    return view('previews.index', [
        'sections' => array_filter($sections),
        'total' => count($rows),
    ]);
});

// Curated live index of every mobile/tablet preview (open on a phone via LAN).
Route::get('/previews/mobile', fn () => view('previews.mobile-index', [
    'lanUrl' => 'http://192.168.0.218:8085/previews/mobile',
]));

// Standalone layout Blade tags (<x-wire::grid|split|section|fieldset|callout|empty-state>).
Route::get('/previews/layout-tags', fn () => view('previews.layout-tags'));

// Full Tailwind palette rendered through the canonical HasColor resolvers.
Route::get('/previews/palette', fn () => view('previews.palette'));

// Interactive standalone Tabs + Wizard (Livewire host → Alpine + core JS bundle).
Route::get('/previews/layout-live', fn () => view('previews.capture', [
    'component' => LayoutPreview::class,
    'variant' => 'tabs-wizard',
    'title' => 'Layout tags · live',
    'subtitle' => 'Standalone Tabs + Wizard',
]));

foreach ($screens as $slug => $screen) {
    Route::get('/previews/'.$slug, fn () => view('previews.capture', $screen));
}

// The command palette is mounted in a layout, not as a variant of a preview
// component, so it gets a page of its own with the trigger an application would
// write beside it.
Route::get('/previews/global-search', fn () => view('previews.global-search'));

// One route for every resource in the shell, plus the bare /previews/workspace
// the index links to. The key→page map is the application's: the registry routes
// nothing, so this array is where a menu entry becomes a URL.
Route::get('/previews/workspace/{resource?}', function (?string $resource = null) use ($workspacePages, $standalonePages) {
    $resource ??= array_key_first($workspacePages);

    abort_unless(isset($workspacePages[$resource]), 404);

    return view('previews.workspace', [
        'title' => $standalonePages['workspace'][0],
        'subtitle' => $standalonePages['workspace'][1],
        'groups' => app(Workspace::class)->navigation(),
        'urls' => array_combine(
            array_keys($workspacePages),
            array_map(fn (string $key): string => '/previews/workspace/'.$key, array_keys($workspacePages)),
        ),
        'active' => $resource,
        'component' => $workspacePages[$resource],
    ]);
});

foreach ($resourcePages as $slug => [$title, $subtitle, $component, $params]) {
    Route::get('/previews/'.$slug, fn () => view('previews.resource', [
        'title' => $title,
        'subtitle' => $subtitle,
        'component' => $component,
        'params' => $params,
    ]));
}

foreach ($fieldPreviews as $field => $label) {
    Route::get('/previews/field-'.$field, fn () => view('previews.capture', [
        'title' => $label.' field',
        'subtitle' => 'Single '.$label.' field rendered through the Wire Forms runtime.',
        'component' => FieldPreview::class,
        'variant' => $field,
    ]));
}

Route::redirect('/previews/forms', '/previews/forms-overview');
Route::redirect('/previews/table', '/previews/table-overview');
Route::redirect('/previews/sortable', '/previews/sortable-overview');
Route::redirect('/previews/core', '/previews/core-overview');
Route::redirect('/previews/widgets', '/previews/widgets-overview');
Route::redirect('/previews/infolists', '/previews/infolists-overview');

// The bundled Laravel Echo client, served only when the workbench is running
// with a Reverb behind it. Workbench-only: Echo is an application's dependency,
// never the package's, so no wire-* asset route carries it.
Route::get('/workbench/echo-bootstrap.js', function () {
    $file = dirname(__DIR__).'/resources/dist/echo-bootstrap.js';

    abort_unless(is_file($file), 404);

    return response()->file($file, ['Content-Type' => 'application/javascript']);
})->name('workbench.echo-bootstrap');
