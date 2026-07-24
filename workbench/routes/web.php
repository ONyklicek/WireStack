<?php

declare(strict_types=1);

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Workbench\App\Livewire\Previews\CorePreview;
use Workbench\App\Livewire\Previews\FieldPreview;
use Workbench\App\Livewire\Previews\FormPreview;
use Workbench\App\Livewire\Previews\InfolistPreview;
use Workbench\App\Livewire\Previews\LayoutPreview;
use Workbench\App\Livewire\Previews\ModalStackingPreview;
use Workbench\App\Livewire\Previews\PanelPreview;
use Workbench\App\Livewire\Previews\SortablePreview;
use Workbench\App\Livewire\Previews\TablePreview;
use Workbench\App\Livewire\Previews\WidgetPreview;

Route::get('/', function (): RedirectResponse {
    return redirect('/previews');
});

Route::get('/previews', function () {
    $screens = [
        [
            'slug' => 'forms-overview',
            'title' => 'Wire Forms',
            'label' => 'Forms overview',
            'copy' => 'Full schema-driven form layout with sections, grid, toggle, and repeater.',
            'component' => FormPreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'forms-repeater',
            'title' => 'Wire Forms',
            'label' => 'Forms repeater',
            'copy' => 'Focused repeater surface with nested items, ordering controls, and add action.',
            'component' => FormPreview::class,
            'variant' => 'repeater',
        ],
        [
            'slug' => 'table-overview',
            'title' => 'Wire Table',
            'label' => 'Table overview',
            'copy' => 'Search, filters, row actions, pagination, and responsive table chrome.',
            'component' => TablePreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'table-actions-quiet',
            'title' => 'Wire Table',
            'label' => 'Table quiet actions',
            'copy' => 'Quiet row actions: neutral at rest, colour on hover/focus, a solid "Approve", and a legible Delete.',
            'component' => TablePreview::class,
            'variant' => 'actions-quiet',
        ],
        [
            'slug' => 'table-record-actions',
            'title' => 'Wire Table',
            'label' => 'Table record actions',
            'copy' => 'Whole-row interaction: double-click a row to open it, right-click for the context menu — one delegated controller, no per-row listeners.',
            'component' => TablePreview::class,
            'variant' => 'record-actions',
        ],
        [
            'slug' => 'table-selection',
            'title' => 'Wire Table',
            'label' => 'Table selection',
            'copy' => 'Bulk selection state with active filter and selected-record toolbar.',
            'component' => TablePreview::class,
            'variant' => 'selection',
        ],
        [
            'slug' => 'table-stacked-selection',
            'title' => 'Wire Table',
            'label' => 'Stacked selection',
            'copy' => 'Card layout with the select-all strip, the select-all-matching escalation and mobile sorting.',
            'component' => TablePreview::class,
            'variant' => 'stacked-selection',
        ],
        [
            'slug' => 'table-subrows',
            'title' => 'Wire Table',
            'label' => 'Table sub-rows',
            'copy' => 'Expandable invoice line items with sortable headers, row actions, and a subtotal.',
            'component' => TablePreview::class,
            'variant' => 'subrows',
        ],
        [
            'slug' => 'table-summary',
            'title' => 'Wire Table',
            'label' => 'Table summary',
            'copy' => 'Rollup totals per row, a multi-aggregate footer, and the page/all scope toggle.',
            'component' => TablePreview::class,
            'variant' => 'summary',
        ],
        [
            'slug' => 'table-column-filters',
            'title' => 'Wire Table',
            'label' => 'Table column filters',
            'copy' => 'Per-column header filters: text, single-select, multi-select, and boolean.',
            'component' => TablePreview::class,
            'variant' => 'column-filters',
        ],
        [
            'slug' => 'table-image-gallery',
            'title' => 'Wire Table',
            'label' => 'Table image gallery',
            'copy' => 'ImageColumn: a single image, an array rendered as a gallery, and a stacked one capped with a "+N" chip.',
            'component' => TablePreview::class,
            'variant' => 'image-gallery',
        ],
        [
            'slug' => 'table-editable-fill',
            'title' => 'Wire Table',
            'label' => 'Table editable + fill',
            'copy' => 'Inline-editable text, select and toggle cells with the Excel-style fill handle.',
            'component' => TablePreview::class,
            'variant' => 'editable-fill',
        ],
        [
            'slug' => 'table-row-color',
            'title' => 'Wire Table',
            'label' => 'Table row color',
            'copy' => 'Whole-row tint driven by a per-record condition, plus an emphasised row class.',
            'component' => TablePreview::class,
            'variant' => 'row-color',
        ],
        [
            'slug' => 'table-subrows-flatten',
            'title' => 'Wire Table',
            'label' => 'Sub-rows flatten',
            'copy' => 'Flatten mode rendering every child record as a regular table row.',
            'component' => TablePreview::class,
            'variant' => 'subrows-flatten',
        ],
        [
            'slug' => 'table-subrows-limit',
            'title' => 'Wire Table',
            'label' => 'Sub-rows show more',
            'copy' => 'Limited child rows with the per-parent "show more" affordance.',
            'component' => TablePreview::class,
            'variant' => 'subrows-limit',
        ],
        [
            'slug' => 'table-subrows-filter',
            'title' => 'Wire Table',
            'label' => 'Sub-row filters',
            'copy' => 'Per-child interactive filter bar above the sub-row table.',
            'component' => TablePreview::class,
            'variant' => 'subrows-filter',
        ],
        [
            'slug' => 'sortable-overview',
            'title' => 'Wire Sortable',
            'label' => 'Sortable overview',
            'copy' => 'Full reorderable task table rendered through the sortable runtime.',
            'component' => SortablePreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'sortable-detail',
            'title' => 'Wire Sortable',
            'label' => 'Sortable detail',
            'copy' => 'Closer reorder surface focused on rows, handles, and ordering affordances.',
            'component' => SortablePreview::class,
            'variant' => 'detail',
        ],
        [
            'slug' => 'actions-modal-stacking',
            'title' => 'Wire Actions',
            'label' => 'Nested modal stacking',
            'copy' => 'Six live configurations of the nested-modal frame stack: create-and-select, deep $setFrame, inline registerActions, slide-over, and a stacked wizard.',
            'component' => ModalStackingPreview::class,
            'variant' => 'gallery',
        ],
        [
            'slug' => 'core-overview',
            'title' => 'Wire Core',
            'label' => 'Core overview',
            'copy' => 'Stats, actions, and shared runtime building blocks without docs chrome.',
            'component' => CorePreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'core-modal',
            'title' => 'Wire Core',
            'label' => 'Core modal',
            'copy' => 'Real modal surface captured from the shared core component set.',
            'component' => CorePreview::class,
            'variant' => 'modal',
        ],
        [
            'slug' => 'core-toasts',
            'title' => 'Wire Core',
            'label' => 'Core toasts',
            'copy' => 'Toast notifications: countdown bar, actions, collapsible stack, max-visible cap, a11y.',
            'component' => CorePreview::class,
            'variant' => 'toasts',
        ],
        [
            'slug' => 'widgets-overview',
            'title' => 'Wire Core',
            'label' => 'Widgets dashboard',
            'copy' => 'Stats overview cards with sparklines and a Chart.js chart widget composed in a grid.',
            'component' => WidgetPreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'widgets-chart',
            'title' => 'Wire Core',
            'label' => 'Chart widget',
            'copy' => 'A single interactive chart widget with heading, description, and a quarter filter.',
            'component' => WidgetPreview::class,
            'variant' => 'chart',
        ],
        [
            'slug' => 'widgets-bar-chart',
            'title' => 'Wire Core',
            'label' => 'Bar chart widget',
            'copy' => 'Pure-CSS bar chart: vertical finance bars, vertical system metrics with grid lines, and horizontal progress.',
            'component' => WidgetPreview::class,
            'variant' => 'bar-chart',
        ],
        [
            'slug' => 'infolists-overview',
            'title' => 'Wire Core',
            'label' => 'Infolist overview',
            'copy' => 'Read-only record display with sections, a column grid, and formatted text/icon/badge entries.',
            'component' => InfolistPreview::class,
            'variant' => 'overview',
        ],
        [
            'slug' => 'infolists-entries',
            'title' => 'Wire Core',
            'label' => 'Infolist entries',
            'copy' => 'Gallery of every built-in entry: text, badge, list, boolean icon, color, key-value, and repeatable.',
            'component' => InfolistPreview::class,
            'variant' => 'entries',
        ],
        [
            'slug' => 'infolists-order',
            'title' => 'Wire Core',
            'label' => 'Infolist order detail',
            'copy' => 'A real order detail: Flex layout, badge/boolean/list entries, and header/entry/per-row actions doing real work.',
            'component' => InfolistPreview::class,
            'variant' => 'order',
        ],
    ];

    return view('previews.index', ['screens' => $screens]);
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

foreach ([
    'forms-overview' => ['title' => 'Wire Forms', 'subtitle' => 'Schema-driven form layout preview.', 'component' => FormPreview::class, 'variant' => 'overview'],
    'forms-tabs' => ['title' => 'Wire Forms Tabs', 'subtitle' => 'Tabbed form layout.', 'component' => FormPreview::class, 'variant' => 'tabs'],
    'forms-wizard' => ['title' => 'Wire Forms Wizard', 'subtitle' => 'Standalone multi-step wizard layout.', 'component' => FormPreview::class, 'variant' => 'wizard'],
    'forms-wizard-live' => ['title' => 'Wire Forms Wizard (Live)', 'subtitle' => 'Per-step validation, dynamic steps, live select with create-option.', 'component' => FormPreview::class, 'variant' => 'wizard-live'],
    'forms-repeater' => ['title' => 'Wire Forms Repeater', 'subtitle' => 'Focused nested repeater preview.', 'component' => FormPreview::class, 'variant' => 'repeater'],
    'forms-enum-defaults' => ['title' => 'Wire Forms Enum Defaults', 'subtitle' => 'Create-mode defaults: an enum-instance default, a clearable enum select, and a numeric default.', 'component' => FormPreview::class, 'variant' => 'enum-defaults'],
    'forms-default-on-null' => ['title' => 'Wire Forms defaultOnNull', 'subtitle' => 'Edit mode with an all-null record: only ->defaultOnNull() fields resurrect their default; a plain default keeps the null.', 'component' => FormPreview::class, 'variant' => 'default-on-null'],
    'table-overview' => ['title' => 'Wire Table', 'subtitle' => 'Live table preview with search, filters, and actions.', 'component' => TablePreview::class, 'variant' => 'overview'],
    'table-record-actions' => ['title' => 'Wire Table Record Actions', 'subtitle' => 'Double-click a row to open it, right-click for the context menu — one delegated controller.', 'component' => TablePreview::class, 'variant' => 'record-actions'],
    'table-selection' => ['title' => 'Wire Table Selection', 'subtitle' => 'Selected-record state with bulk toolbar and active filters.', 'component' => TablePreview::class, 'variant' => 'selection'],
    'table-stacked-selection' => ['title' => 'Wire Table Stacked Selection', 'subtitle' => 'Card layout with the always-visible select-all strip, the select-all-matching escalation, and mobile sorting.', 'component' => TablePreview::class, 'variant' => 'stacked-selection'],
    'table-subrows' => ['title' => 'Wire Table Sub-rows', 'subtitle' => 'Expandable invoice line items with sortable headers, row actions, and a subtotal.', 'component' => TablePreview::class, 'variant' => 'subrows'],
    'table-summary' => ['title' => 'Wire Table Summary', 'subtitle' => 'Rollup totals, a multi-aggregate footer, and the page/all scope toggle.', 'component' => TablePreview::class, 'variant' => 'summary'],
    'table-row-color' => ['title' => 'Wire Table Row Color', 'subtitle' => 'Conditional whole-row tint by record status, plus an emphasised row class.', 'component' => TablePreview::class, 'variant' => 'row-color'],
    'table-column-filters' => ['title' => 'Wire Table Column Filters', 'subtitle' => 'Per-column header filters: text, single-select, multi-select, and boolean.', 'component' => TablePreview::class, 'variant' => 'column-filters'],
    'table-image-gallery' => ['title' => 'Wire Table Image Gallery', 'subtitle' => 'ImageColumn: single image, an array as a gallery, and a stacked one capped with a "+N" chip.', 'component' => TablePreview::class, 'variant' => 'image-gallery'],
    'table-editable-fill' => ['title' => 'Wire Table Editable + Fill', 'subtitle' => 'Inline-editable text, select and toggle cells with the Excel-style fill handle. Email opts out via ->fillable(false).', 'component' => TablePreview::class, 'variant' => 'editable-fill'],
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
    'table-paginated' => ['title' => 'Wire Table Pagination', 'subtitle' => 'Paginated table with per-page selector and page links.', 'component' => TablePreview::class, 'variant' => 'paginated'],
    'sortable-overview' => ['title' => 'Wire Sortable', 'subtitle' => 'Full reorderable task table preview.', 'component' => SortablePreview::class, 'variant' => 'overview'],
    'sortable-detail' => ['title' => 'Wire Sortable Detail', 'subtitle' => 'Closer reorder-surface preview.', 'component' => SortablePreview::class, 'variant' => 'detail'],
    'actions-modal-stacking' => ['title' => 'Wire Actions · Nested Modal Stacking', 'subtitle' => 'Six live configurations of the nested-modal frame stack — create-and-select ($setParent), deep $setFrame, inline registerActions, slide-over, and a stacked wizard.', 'component' => ModalStackingPreview::class, 'variant' => 'gallery'],
    'core-overview' => ['title' => 'Wire Core', 'subtitle' => 'Stats, actions, and shared primitives.', 'component' => CorePreview::class, 'variant' => 'overview'],
    'core-modal' => ['title' => 'Wire Core Modal', 'subtitle' => 'Real modal surface from the core runtime.', 'component' => CorePreview::class, 'variant' => 'modal'],
    'core-dropdown' => ['title' => 'Wire Core Dropdown', 'subtitle' => 'Generic dropdown that becomes a bottom sheet on mobile.', 'component' => CorePreview::class, 'variant' => 'dropdown'],
    'core-toasts' => ['title' => 'Wire Core Toasts', 'subtitle' => 'Toast countdown bar, actions, collapsible stack, max-visible cap, and a11y support.', 'component' => CorePreview::class, 'variant' => 'toasts'],
    'widgets-overview' => ['title' => 'Wire Core Widgets', 'subtitle' => 'Stats overview and a chart widget composed into a dashboard grid.', 'component' => WidgetPreview::class, 'variant' => 'overview'],
    'widgets-chart' => ['title' => 'Wire Core Chart Widget', 'subtitle' => 'A single interactive Chart.js widget with heading and filter.', 'component' => WidgetPreview::class, 'variant' => 'chart'],
    'widgets-bar-chart' => ['title' => 'Wire Core Bar Chart Widget', 'subtitle' => 'Pure-CSS bar chart with finance, system, and horizontal progress modes.', 'component' => WidgetPreview::class, 'variant' => 'bar-chart'],
    'infolists-overview' => ['title' => 'Wire Core Infolist', 'subtitle' => 'Read-only record display with sections, a column grid, and formatted entries.', 'component' => InfolistPreview::class, 'variant' => 'overview'],
    'infolists-entries' => ['title' => 'Wire Core Infolist Entries', 'subtitle' => 'Gallery of every built-in infolist entry type bound to one record.', 'component' => InfolistPreview::class, 'variant' => 'entries'],
    'infolists-order' => ['title' => 'Wire Core Infolist Order Detail', 'subtitle' => 'A real order detail: Flex layout, badge/boolean/list entries, and header/entry/per-row actions.', 'component' => InfolistPreview::class, 'variant' => 'order'],
    'panels-editable' => ['title' => 'Wire Core Editable Panel', 'subtitle' => 'A Model-backed record panel: toggle, select, and text edits write straight to the row with optimistic UI. Read-only email entry mixed in.', 'component' => PanelPreview::class, 'variant' => 'default'],
] as $slug => $screen) {
    Route::get('/previews/'.$slug, fn () => view('previews.capture', $screen));
}

$fieldPreviews = [
    'text-input' => 'Text Input',
    'textarea' => 'Textarea',
    'tiptap' => 'TipTap (core)',
    'tiptap-tables' => 'TipTap (with tables)',
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
    'otp-input' => 'OTP Input',
    'key-value' => 'Key-Value',
    'date-time-picker' => 'Date-Time Picker',
    'file-upload' => 'File Upload',
];

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
