@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    use Illuminate\Support\Collection;
    use NyonCode\WireTable\Table;

    assert($table instanceof Table);

    /** @var LengthAwarePaginator|Collection $records */
    /** @var mixed $component */

    // What this render resolved, in PHP. Asked for here rather than handed in,
    // because a view may reconfigure the table before this one renders — see
    // WithTable::tableRenderPlan(), and wire-sortable's column order for the case
    // that proves it.
    $plan = $component->tableRenderPlan();

    // The frame around the rows — whether it renders yet (lazy), how it keeps
    // itself current (poll, and the broadcast channel `live(broadcast: true)`
    // adds), and which optional regions exist at all. All resolved in
    // ShellRenderPlan, including the three feature guards this block used to
    // write as `&&` pairs.
    $isLazy = $plan->shell()->isLazy;
    $isTableReady = $plan->shell()->isTableReady;
    $lazyPlaceholder = $plan->shell()->lazyPlaceholder;
    $pollingConfig = $plan->shell()->pollingConfig;
    $pollingAttribute = $plan->shell()->pollingAttribute;
    $liveChannel = $plan->shell()->liveChannel;
    $filters = $plan->shell()->filters;
    $hasFilters = $plan->shell()->hasFilters;
    $hasSubRows = $plan->shell()->hasSubRows;
    $isSubRowsExpandable = $plan->shell()->isSubRowsExpandable;
    $allRowsExpanded = $plan->shell()->allRowsExpanded;
    $hasGrouping = $plan->shell()->hasGrouping;
    $hasGroupSummaries = $plan->shell()->hasGroupSummaries;
    // The view menu earns its place from either section it can hold.
    $hasViewMenu = $plan->shell()->hasViewMenu;
    $viewMenuLabel = $plan->shell()->viewMenuLabel;

    // Table state and everything derived from it now comes resolved, from
    // TableRenderPlan — including which filters count as ACTIVE, a rule with a
    // sharp edge (see the class) that used to live here as a recursive closure.
    // The magic-property hazard this block used to warn about goes with it: the
    // plan reads the state container, so there is nothing here to reach for.
    $tableFilters = $plan->state()->filters;
    $columnFilterValues = $plan->state()->columnFilters;
    $activeTableFilters = $plan->state()->activeFilters;
    $activeColumnFilters = $plan->state()->activeColumnFilters;
    $sortColumn = $plan->state()->sortColumn;
    $sortDirection = $plan->state()->sortDirection;
    $perPage = $plan->state()->perPage;
    $hasActiveFilters = $plan->state()->hasActiveFilters;

    // Floating filter/column-toggle panels present as a bottom sheet on mobile
    // unless disabled via Table::sheetOnMobile(false) or the global config. The
    // five class strings all derive from the one breakpoint — LayoutRenderPlan
    // resolves them together so a partial cannot recompute them differently.
    $sheetOnMobile = $plan->layout()->sheetOnMobile;
    $sheetBp = $plan->layout()->sheetBreakpoint;
    $sheetBpPx = $plan->layout()->sheetBreakpointPx;
    $sheetPanel = $plan->layout()->sheetPanel;
    $sheetMotion = $plan->layout()->sheetMotion;
    $sheetBackdrop = $plan->layout()->sheetBackdrop;
    // Actions — the four surfaces (row, bulk, header, mobile card), their
    // collapsed dropdown forms, and the click resolvers that keep wire-core's
    // action views host-agnostic. All resolved in ActionRenderPlan.
    $actions = $plan->actions()->row;
    $bulkActions = $plan->actions()->bulk;
    $headerActions = $plan->actions()->header;
    $hasActions = $plan->actions()->hasAny;
    $mobileActions = $plan->actions()->mobile;
    $hasMobileActions = $plan->actions()->hasMobile;
    $collapseMobileActions = $plan->actions()->collapseMobile;
    $mobileActionGroup = $plan->actions()->mobileGroup;
    $collapseHeaderActions = $plan->actions()->collapseHeader;
    $mobileHeaderActionGroup = $plan->actions()->mobileHeaderGroup;
    $headerActionClick = $plan->actions()->headerClick;
    $actionClick = $plan->actions()->click;

    // Row interaction — the pointer bindings, the two independently switchable
    // halves of the gesture layer, the active-row marker and the `?` shortcut
    // help. All resolved in InteractionRenderPlan, including folding the help
    // event into the keyboard config (which this block used to write back after
    // the fact). $shortcutLegend and $shortcutHelpEvent look unused below: they
    // reach partials.shortcut-help-modal through @include scope inheritance.
    $rowContextMenuEnabled = $plan->interaction()->rowContextMenuEnabled;
    $recordActionBindings = $plan->interaction()->recordActionBindings;
    $keyboardNav = $plan->interaction()->keyboardNav;
    $tableRole = $plan->interaction()->tableRole;
    $recordKeyboardConfig = $plan->interaction()->recordKeyboardConfig;
    $recordActionsRootEnabled = $plan->interaction()->recordActionsRootEnabled;
    $gestureConfig = $plan->interaction()->gestureConfig;
    $activeRowConfig = $plan->interaction()->activeRowConfig;
    $shortcutLegend = $plan->interaction()->shortcutLegend;
    $shortcutHelpEvent = $plan->interaction()->shortcutHelpEvent;
    $hasBulkActions = $plan->actions()->hasBulk;
    $hasHeaderActions = $plan->actions()->hasHeader;
    // The row and everything selection needs — the compiled <tr>, the checkbox
    // cell, the per-row class binding and the live-region sentences. Resolved in
    // RowRenderPlan; see it for why the markup is compiled once per TABLE.
    $isSelectable = $plan->row()->isSelectable;
    $selectCheckIcon = $plan->row()->selectCheckIcon;
    $hasSummaries = $plan->row()->hasSummaries;
    $rowClassBinding = $plan->row()->rowClassBinding;
    $pageRecordKeys = $plan->row()->pageRecordKeys;
    $selectionSyncLive = $plan->row()->selectionSyncLive;
    $isPaginated = $plan->paging()->isPaginated;

    // Columns, and everything derived from them — which visible column set the
    // page has, the per-column render metadata (including each column's compiled
    // <td> skeleton), and the lists the fill handle, the column filters, the
    // column toggles and the mobile sort control each read. All resolved once,
    // in TableRenderPlan; see the class for why the metadata is per-column
    // rather than per-cell.
    $visibleColumns = $plan->columns()->visible;
    $hasVisibleColumns = $plan->columns()->hasVisible;
    $columnMeta = $plan->columns()->meta;
    $fillColumns = $plan->columns()->fillable;
    $isFillEnabled = $plan->columns()->isFillEnabled;
    $filterableColumns = $plan->columns()->filterable;
    $hasColumnFilters = $plan->columns()->hasFilters;
    $subRowColumns = $plan->columns()->subRow;
    $visibleSubRowColumns = $plan->columns()->visibleSubRow;
    $hasCopyableColumn = $plan->columns()->hasCopyable;
    $colSpan = $plan->columns()->colSpan;
    $toggleableColumns = $plan->columns()->toggleable;
    $visibleToggleableCount = $plan->columns()->visibleToggleableCount;
    $hasColumnToggles = $plan->columns()->hasToggles;
    $mobileSortableColumns = $plan->columns()->mobileSortable;
    $hasMobileSort = $plan->columns()->hasMobileSort;

    // Action configuration
    $actionsPosition = $plan->actions()->position; // 'start' or 'end'
    // ($plan->actions()->alignment is the raw 'left'/'center'/'right'; nothing in
    // any view reads it — the class below is what gets rendered.)
    $actionsAlignmentClass = $plan->actions()->alignmentClass; // literal text-* utility
    $actionsJustifyClass = $plan->actions()->justifyClass; // literal justify-* utility
    $actionsColumnLabel = $plan->actions()->columnLabel;
    $actionsColumnWidth = $plan->actions()->columnWidth;

    // Table styling. Row hover/striping/tint is composed in
    // Table::getRowClasses($record, $rowIndex), not here. Table::isCompact() is
    // not carried: it feeds the padding maps below and no view reads it directly.
    $isBordered = $plan->layout()->isBordered;
    $cellPadding = $plan->layout()->cellPadding;
    $headerPadding = $plan->layout()->headerPadding;

    // The body cell is compiled once per column, into $columnMeta[...]['cell'] —
    // see ColumnRenderPlan::meta(), which owns it now.

    // Responsive layout — literal Tailwind class names, never interpolated.
    $isStackedOnMobile = $plan->layout()->isStackedOnMobile;
    $tableHiddenClass = $plan->layout()->tableHiddenClass;
    $cardsVisibleClass = $plan->layout()->cardsVisibleClass;

    // Where this page sits in the whole result set — read by the footer's
    // "from - to of total" line and, before it, by aria-rowindex, since an ARIA
    // row index counts through the entire grid rather than the page. Resolved in
    // PagingRenderPlan, which is also where the rule lives that only a
    // length-aware paginator may be asked for a total or an item offset.
    $hasPaginator = $plan->paging()->hasPaginator;
    $recordCount = $plan->paging()->recordCount;
    $isEmptyDueToFilter = $plan->paging()->isEmptyDueToFilter;
    $rangeFrom = $plan->paging()->rangeFrom;
    $rangeTo = $plan->paging()->rangeTo;
    $headerRowCount = $plan->paging()->headerRowCount;

    $rowSkeleton = $plan->row()->rowSkeleton;
    $selectionCellSkeleton = $plan->row()->selectionCellSkeleton;
    $selectionAnnouncements = $plan->row()->selectionAnnouncements;
@endphp

{{-- Lazy loading: trigger load when visible --}}
@if($isLazy && !$isTableReady)
    {{-- This used to force all three bundles out with the PLACEHOLDER render,
         because each registered its Alpine components from an `alpine:init`
         listener and that event fires exactly once, when Alpine boots: a bundle
         first emitted by the deferred render subscribed to an event that would
         never fire again and registered nothing.

         The bundles now register unconditionally (see AI_CODING_STANDARD.md
         § Rendering and js-asset-registration.md §3.A), so arriving late is safe,
         and Livewire already guarantees they are not late in the way that would
         matter here: on an AJAX round trip it awaits `payload.intercept` — which
         loads and runs the new @assets to completion — before `handleSuccess`
         morphs the markup in. The factory therefore exists before the deferred
         table is ever initialised, and pre-emitting would only pull bundles onto
         a page whose whole point is to defer them. --}}
    <div
            x-data="{ loaded: false }"
            x-intersect.once="if (!loaded) { loaded = true; $wire.loadTable(); }"
            class="w-full"
            wire:key="table-lazy-wrapper"
    >
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700">
            <div class="p-8 flex flex-col items-center justify-center min-h-[300px]">
                @if($lazyPlaceholder)
                    {!! $lazyPlaceholder !!}
                @else
                    {{-- Default loading skeleton --}}
                    {{--<div class="w-full max-w-3xl space-y-4 animate-pulse">--}}
                    <div class="w-full space-y-4 animate-pulse">
                        {{-- Header skeleton --}}
                        <div
                                class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg w-64"></div>
                            <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg w-32"></div>
                        </div>

                        {{-- Table header skeleton --}}
                        <div class="flex gap-4 py-3">
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                        </div>

                        {{-- Row skeletons --}}
                        @for($i = 0; $i < 5; $i++)
                            <div class="flex gap-4 py-4 border-t border-gray-100 dark:border-gray-700/50">
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                            </div>
                        @endfor

                        {{-- Footer skeleton --}}
                        <div
                                class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-32"></div>
                            <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-48"></div>
                        </div>
                    </div>

                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.loading_table') }}</p>
                @endif
            </div>
        </div>
    </div>
@else
    {{-- Polling wrapper. Also the live listener's root, so `busy()` sees exactly
         the cells this table owns and a nested table cannot be mistaken for it.

         Which couples the two halves of live(): this wrapper only renders while
         shouldPoll() is true, so pausing the poll (the Stop control, or a
         pollWhen() condition turning false) takes the broadcast listener with it
         — and refreshTable() would refuse the nudge anyway. Intended for the
         Stop control, where "stop the table changing under me" should mean both.
         Not obviously right for pollWhen(), which is a cost condition rather than
         a statement of intent; a table combining it with broadcast: true loses
         the push exactly while the condition is false. Documented in
         docs/table/advanced.md rather than worked around here, because splitting
         them needs a second wrapper and a push-only host method. --}}
    @if($liveChannel)
        @include('wire-table::tables.partials.live-assets')
    @endif
    @if($pollingAttribute)
        <div {!! $pollingAttribute !!}
             @if($liveChannel) x-data="wireTableLive(@js(['channel' => $liveChannel]))" @endif
        >
            @endif

            <div
                    class="w-full"
                    wire:key="table-wrapper"
                    @if($isSelectable)
                        data-page-keys="{{ json_encode($pageRecordKeys) }}"
                        data-matching="{{ $recordCount }}"
                        data-selection-root
                        {{-- Contract marker between this (publishable) view and the
                             packaged JS: the bundled record-actions controller refuses a
                             stale published view out loud instead of selecting wrong
                             ranges silently. Bump only with the selection contract. --}}
                        data-selection-version="1"
                        {{-- One shared selection component (wireRecordSelection, shipped in
                             the package bundle): the checkboxes, both select-all toggles,
                             the bulk bar, the mobile cards and the keyboard gestures all
                             drive this state. PHP hands over the semantics — the state
                             path and the commit policy — so they stay assertable here. --}}
                        x-data="wireRecordSelection({ statePath: 'tableState.selection', syncLive: {{ $selectionSyncLive ? 'true' : 'false' }}, commitDelay: 350, announcements: @js($selectionAnnouncements) })"
                    @endif
            >
                @if($isSelectable)
                    {{-- Selection announcements. Deliberately NOT in the bulk bar: that
                         is behind x-show, and a hidden live region announces nothing —
                         worse, it disappears at zero selected, so "selection cleared"
                         could never be heard. This one is in the DOM from the first
                         render and empty, which is what a live region needs to work. --}}
                    <div
                            class="sr-only"
                            aria-live="polite"
                            aria-atomic="true"
                            data-testid="selection-live"
                            x-text="announcement"
                    ></div>

                    {{-- Inside the selectable wrapper, NOT inside the table body: the
                         body is not rendered without visible columns, but the selection
                         is live in the stacked cards too. --}}
                    @once
                        @include('wire-table::tables.partials.selection-assets')
                    @endonce
                @endif
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700">

                    {{-- Header --}}
                    <div class="px-4 lg:px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            {{-- Left side: Search & Filters --}}
                            <div class="flex flex-1 items-center gap-3">
                                {{-- Global Search --}}
                                @if($table->isSearchable())
                                    <div class="relative flex-1 max-w-xs">
                                        <div
                                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            {!! icon('outline:magnifying-glass', 'h-4 w-4', 'text-gray-400') !!}
                                        </div>
                                        <input
                                                type="search"
                                                wire:model.live.debounce.300ms="tableState.search"
                                                placeholder="{{ __('wire-table::messages.search') }}..."
                                                aria-label="{{ __('wire-table::messages.search') }}"
                                                data-testid="table-search"
                                                class="block w-full rounded-lg border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 pl-9 pr-3 py-2 text-sm placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:text-white dark:placeholder-gray-500"
                                        >
                                    </div>
                                @endif

                                {{-- Filters Toggle --}}
                                @if($hasFilters)
                                    @include('wire-core::partials.floating-assets')

                                    <div x-data="wireDropdown({ placement: 'bottom-start', offset: 8{{ $sheetOnMobile ? ', sheetOnMobile: true, sheetBreakpoint: '.$sheetBpPx : '' }} })" @keydown.escape.window="close()" class="relative">
                                        <button
                                                x-ref="trigger"
                                                @click="toggle()"
                                                type="button"
                                                data-testid="table-filters-trigger"
                                                aria-label="{{ __('wire-table::messages.filters') }}"
                                                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                        >
                                            {!! icon('outline:funnel', 'h-4 w-4') !!}
                                            <span>{{ __('wire-table::messages.filters') }}</span>
                                            @if($activeTableFilters !== [])
                                                <span
                                                        class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-primary-600 rounded-full">
                                        {{ count($activeTableFilters) }}
                                    </span>
                                            @endif
                                        </button>

                                        {{-- Filters dropdown: floating panel from sm up, bottom sheet on
                                             a phone (max-sm: classes; wireDropdown skips Floating UI). --}}
                                        <template x-teleport="body">
                                            <div>
                                                @if($sheetOnMobile)
                                                {{-- Backdrop: mobile-only, taps to close. --}}
                                                <div
                                                        x-show="open"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-150"
                                                        x-transition:enter-start="opacity-0"
                                                        x-transition:enter-end="opacity-100"
                                                        x-transition:leave="transition ease-in duration-100"
                                                        x-transition:leave-start="opacity-100"
                                                        x-transition:leave-end="opacity-0"
                                                        @click="close()"
                                                        class="fixed inset-0 z-40 bg-gray-500/60 dark:bg-gray-900/70 {{ $sheetBackdrop }}"
                                                ></div>
                                                @endif

                                                <div
                                                        x-ref="panel"
                                                        x-show="open"
                                                        @click.outside="$clickedInside($event) || close()"
                                                        @if($sheetOnMobile) x-focus-trap="open" tabindex="-1" data-sheet-bp="{{ $sheetBpPx }}" @endif
                                                        x-transition:enter="transition ease-out duration-100"
                                                        x-transition:enter-start="opacity-0 scale-95 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                                                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                                        x-transition:leave="transition ease-in duration-75"
                                                        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                                        x-transition:leave-end="opacity-0 scale-95 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                                                        @class([
                                                            'absolute top-0 left-0 z-50 w-72 origin-top-left rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700',
                                                            $sheetPanel => $sheetOnMobile,
                                                        ])
                                                        x-cloak
                                                        style="display: none;"
                                                >
                                                    @if($sheetOnMobile)
                                                        @include('wire-core::partials.sheet-grabber', ['dismiss' => 'close()', 'breakpoint' => $sheetBp])
                                                    @endif
                                                    <div class="p-4 space-y-4">
                                                        @foreach($filters as $filter)
                                                            @if($filter->canView())
                                                                {!! $filter->render($tableFilters[$filter->getName()] ?? null) !!}
                                                            @endif
                                                        @endforeach

                                                        <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                                                            <button
                                                                    type="button"
                                                                    wire:click="resetTableFilters"
                                                                    class="w-full text-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                                            >
                                                                {{ __('wire-table::messages.filter_reset') }}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </div>

                            {{-- Right side: Polling, Header Actions, Column Toggle --}}
                            <div class="flex items-center gap-2">
                                {{-- Polling Indicator --}}
                                @include('wire-table::tables.partials.polling-indicator')

                                {{-- Sort, for the stacked card view only: the header row that
                                     carries the sort buttons is hidden at this width, which
                                     left a phone with no way to sort at all. --}}
                                @if($hasMobileSort)
                                    @include('wire-table::tables.partials.mobile-sort', [
                                        'table' => $table,
                                        'component' => $component,
                                        'sortableColumns' => $mobileSortableColumns,
                                        'sortColumn' => $sortColumn,
                                        'sortDirection' => $sortDirection,
                                        'visibleClass' => $cardsVisibleClass,
                                        'sheetOnMobile' => $sheetOnMobile,
                                        'sheetBpPx' => $sheetBpPx,
                                        'sheetBp' => $sheetBp,
                                        'sheetBackdrop' => $sheetBackdrop,
                                        'sheetPanel' => $sheetPanel,
                                        'sheetMotion' => $sheetMotion,
                                    ])
                                @endif

                                {{-- Plugin Toolbar Widgets --}}
                                @if(method_exists($component, 'getTableToolbarWidgets'))
                                    @foreach($component->getTableToolbarWidgets() as $widget)
                                        {!! $widget !!}
                                    @endforeach
                                @endif

                                {{-- Header Actions. With collapseHeaderActionsOnMobile() the
                                     buttons move into a wrapper that hides below the mobile
                                     breakpoint and one dropdown trigger takes their place;
                                     without it they sit in the toolbar flex unwrapped, as
                                     they always did. --}}
                                @if($hasHeaderActions)
                                    @if($collapseHeaderActions)
                                        <div class="{{ $table->getInlineHeaderActionsClass() }} items-center gap-2">
                                            @include('wire-table::tables.partials.header-actions', ['headerActions' => $headerActions])
                                        </div>

                                        <div class="{{ $table->getMobileHeaderActionsVisibleClass() }}" data-testid="table-header-actions-mobile">
                                            {!! $mobileHeaderActionGroup->render(null, $headerActionClick) !!}
                                        </div>
                                    @else
                                        @include('wire-table::tables.partials.header-actions', ['headerActions' => $headerActions])
                                    @endif
                                @endif

                                {{-- View menu: column visibility + sub-row expansion, the two
                                     "how I look at this table" settings. Opens as a bottom sheet
                                     on a phone, which is where the master chevron cannot follow
                                     (the stacked card layout has no header row). --}}
                                @if($hasViewMenu)
                                    @include('wire-core::partials.floating-assets')

                                    <div
                                            x-data="wireDropdown({ placement: 'bottom-end'{{ $sheetOnMobile ? ', sheetOnMobile: true, sheetBreakpoint: '.$sheetBpPx : '' }} })"
                                            @keydown.escape.window="close()"
                                            class="relative"
                                    >
                                        <button
                                                x-ref="trigger"
                                                @click="toggle()"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                title="{{ $viewMenuLabel }}"
                                                aria-label="{{ $viewMenuLabel }}"
                                                data-testid="table-column-toggle"
                                        >
                                            {!! icon('outline:view-columns', 'h-5 w-5') !!}
                                        </button>

                                        {{-- Column toggle: floating panel from sm up, bottom sheet on a
                                             phone (max-sm: classes; wireDropdown skips Floating UI). --}}
                                        <template x-teleport="body">
                                            <div>
                                                @if($sheetOnMobile)
                                                {{-- Backdrop: mobile-only, taps to close. --}}
                                                <div
                                                        x-show="open"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-150"
                                                        x-transition:enter-start="opacity-0"
                                                        x-transition:enter-end="opacity-100"
                                                        x-transition:leave="transition ease-in duration-100"
                                                        x-transition:leave-start="opacity-100"
                                                        x-transition:leave-end="opacity-0"
                                                        @click="close()"
                                                        class="fixed inset-0 z-40 bg-gray-500/60 dark:bg-gray-900/70 {{ $sheetBackdrop }}"
                                                ></div>
                                                @endif

                                                <div
                                                    x-ref="panel"
                                                    x-show="open"
                                                    @click.outside="$clickedInside($event) || close()"
                                                    @if($sheetOnMobile) x-focus-trap="open" tabindex="-1" data-sheet-bp="{{ $sheetBpPx }}" @endif
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="transform opacity-0 scale-95 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                                                    x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
                                                    x-transition:leave="transition ease-in duration-75"
                                                    x-transition:leave-start="transform opacity-100 scale-100 translate-y-0"
                                                    x-transition:leave-end="transform opacity-0 scale-95 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                                                    @class([
                                                        'absolute top-0 left-0 origin-top-right w-56 rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 z-50 max-h-80 overflow-y-auto',
                                                        $sheetPanel => $sheetOnMobile,
                                                    ])
                                                    x-cloak
                                                    style="display: none;"
                                            >
                                                @if($sheetOnMobile)
                                                    @include('wire-core::partials.sheet-grabber', ['dismiss' => 'close()', 'breakpoint' => $sheetBp])
                                                @endif
                                                <div class="p-2">
                                                @if($hasColumnToggles)
                                                <div
                                                        class="px-3 py-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700 mb-1">
                                                    {{ __('wire-table::messages.columns_section') }}
                                                </div>
                                                @endif
                                                @foreach($toggleableColumns as $column)
                                                    @php
                                                        $isVisible = $component->isColumnVisible($column->getName());
                                                        $isLastVisible = $isVisible && $visibleToggleableCount <= 1;
                                                    @endphp
                                                    <label
                                                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer select-none {{ $isLastVisible ? 'opacity-50' : '' }}">
                                                        <div class="flex items-center justify-center w-5 h-5 shrink-0">
                                                            <input
                                                                    type="checkbox"
                                                                    @if(!$isLastVisible)
                                                                        wire:click="toggleColumn('{{ $column->getName() }}')"
                                                                    @endif
                                                                    @checked($isVisible)
                                                                    @disabled($isLastVisible)
                                                                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:bg-gray-700 {{ $isLastVisible ? 'cursor-not-allowed' : 'cursor-pointer' }}"
                                                            >
                                                        </div>
                                                        <span
                                                                class="text-sm text-gray-700 dark:text-gray-300">{{ $column->getLabel() }}</span>
                                                    </label>
                                                @endforeach
                                                {{-- Sub-row expansion: the baseline that survives paging,
                                                     and the only bulk expand/collapse a phone gets. --}}
                                                @if($isSubRowsExpandable)
                                                    <div
                                                            class="px-3 py-2 mt-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider {{ $hasColumnToggles ? 'border-t border-gray-100 dark:border-gray-700' : 'border-b border-gray-100 dark:border-gray-700 mb-1' }}">
                                                        {{ __('wire-table::messages.details_section') }}
                                                    </div>
                                                    <label
                                                            class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer select-none">
                                                        <div class="flex items-center justify-center w-5 h-5 shrink-0">
                                                            <input
                                                                    type="checkbox"
                                                                    wire:click="toggleAllRowExpansion"
                                                                    @checked($allRowsExpanded)
                                                                    data-testid="subrows-expand-all-rows"
                                                                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:bg-gray-700 cursor-pointer"
                                                            >
                                                        </div>
                                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                                            {{ __('wire-table::messages.expand_all_rows') }}
                                                        </span>
                                                    </label>
                                                @endif

                                                {{-- Reset to the configured defaults (clears the saved layout). --}}
                                                @if($hasColumnToggles && $table->getRememberColumnsKey() !== null)
                                                    <button
                                                            type="button"
                                                            wire:click="resetColumns"
                                                            class="mt-1 flex w-full items-center gap-3 border-t border-gray-100 dark:border-gray-700 px-3 py-2 text-left text-sm text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-lg"
                                                    >
                                                        {!! icon('outline:arrow-path', 'h-4 w-4') !!}
                                                        {{ __('wire-table::messages.reset_columns') }}
                                                    </button>
                                                @endif
                                                </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Active Filter Indicators --}}
                    @if($hasFilters)
                        @include('wire-table::tables.partials.filter-indicators', ['component' => $component])
                    @endif

                    {{-- Selection Bar (Alpine-driven — appears instantly, no roundtrip) --}}
                    @if($isSelectable)
                        <div
                                x-show="selectedCount > 0"
                                x-cloak
                                data-testid="table-bulk-bar"
                                class="px-4 lg:px-6 py-3 bg-primary-50 dark:bg-primary-900/20 border-b border-primary-100 dark:border-primary-800/30">
                            {{-- Stacks on mobile so multiple bulk-action buttons wrap instead of overflowing. --}}
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                            class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-800/50">
                                <span
                                        class="text-sm font-semibold text-primary-700 dark:text-primary-300 tabular-nums" x-text="selectedCount"></span>
                                    </div>
                                    <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                            {{-- Plural forms resolved client-side: representative counts cover {1} / [2,4] / [5,*] --}}
                            <span x-show="selectedCount === 1">{{ trans_choice('{1} record selected|[2,*] records selected', 1) }}</span>
                            <span x-show="selectedCount >= 2 && selectedCount <= 4">{{ trans_choice('{1} record selected|[2,*] records selected', 2) }}</span>
                            <span x-show="selectedCount >= 5">{{ trans_choice('{1} record selected|[2,*] records selected', 5) }}</span>
                        </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    {{-- Bulk Actions in selection bar --}}
                                    @if($hasBulkActions)
                                        @foreach($bulkActions as $bulkAction)
                                            @if($bulkAction->canExecute())
                                                {!! $bulkAction->render() !!}
                                            @endif
                                        @endforeach
                                    @endif

                                    {{-- Deselect button --}}
                                    <button
                                            type="button"
                                            x-on:click="deselectAll()"
                                            data-testid="table-deselect"
                                            aria-label="{{ __('wire-table::messages.deselect') }}"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary-700 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200 hover:bg-primary-100 dark:hover:bg-primary-800/50 rounded-lg transition-colors"
                                    >
                                        {!! icon('outline:x-mark', 'w-4 h-4') !!}
                                        {{ __('wire-table::messages.deselect') }}
                                    </button>
                                </div>
                            </div>

                            {{-- Scope: this page, or every row the filter matches. Always
                                 present while something is selected, so the wider reach is
                                 never a surprise the user has to discover. --}}
                            @if($recordCount > count($pageRecordKeys))
                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-primary-700 dark:text-primary-300"
                                     data-testid="table-selection-scope">
                                    <template x-if="selectsAll">
                                        <span>{{ __('wire-table::messages.selection_all_matching', ['count' => $recordCount]) }}</span>
                                    </template>
                                    <template x-if="!selectsAll">
                                        <span x-text="@js(__('wire-table::messages.selection_on_page')).replace(':count', selectedCount)"></span>
                                    </template>

                                    <button
                                            type="button"
                                            x-show="!selectsAll"
                                            x-on:click="selectAllMatching()"
                                            data-testid="table-select-all-matching"
                                            class="font-semibold underline underline-offset-2 hover:no-underline"
                                    >
                                        {{ __('wire-table::messages.selection_select_all_matching', ['count' => $recordCount]) }}
                                    </button>
                                    <button
                                            type="button"
                                            x-show="selectsAll"
                                            x-cloak
                                            x-on:click="selectOnlyPage()"
                                            data-testid="table-select-only-page"
                                            class="font-semibold underline underline-offset-2 hover:no-underline"
                                    >
                                        {{ __('wire-table::messages.selection_only_this_page') }}
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Table --}}
                    {{-- `relative` is the positioning context the fill handle and its
                         range overlay are placed against, so they scroll with the table. --}}
                    <div class="relative overflow-x-auto {{ $tableHiddenClass }}"
                         @if($isFillEnabled)
                             x-data="wireFillHandle()"
                             data-fill-root
                             data-fill-columns="{{ json_encode($fillColumns) }}"
                             data-fill-max="{{ $table->getFillMaxRecords() }}"
                         @endif
                    >
                        @if($hasVisibleColumns)
                            <table
                                    @if($tableRole)
                                        role="{{ $tableRole }}"
                                        {{-- Counts the whole result set plus the header rows, not
                                             the page: a grid tells assistive tech how much there is
                                             to page through, and the row indices below match it. --}}
                                        aria-rowcount="{{ $recordCount + $headerRowCount }}"
                                        @if($isSelectable) aria-multiselectable="true" @endif
                                    @endif
                                    class="w-full {{ $isBordered ? 'border-collapse' : '' }} {{ $table->getTableClass() }}">
                                <thead
                                        class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400 uppercase {{ $table->getHeaderClass() }}">
                                <tr @if($tableRole) aria-rowindex="1" @endif>
                                    {{-- Select All Checkbox --}}
                                    @if($isSelectable)
                                        <th scope="col" class="w-12 {{ $headerPadding }}">
                                            <div class="flex items-center justify-center">
                                                <button
                                                        type="button"
                                                        x-on:click="toggleAll()"
                                                        role="checkbox"
                                                        :aria-checked="allSelected ? 'true' : (someSelected ? 'mixed' : 'false')"
                                                        aria-label="{{ __('wire-table::messages.select_all') }}"
                                                        data-testid="table-select-all"
                                                        class="relative h-4 w-4 rounded border focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
                                                        :class="(allSelected || someSelected) ? 'bg-primary-600 border-primary-600' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600'"
                                                >
                                                    <span x-show="allSelected" x-cloak>
                                                        {!! icon('check', 'h-4 w-4', 'absolute inset-0 text-white') !!}
                                                    </span>
                                                    <span x-show="someSelected" x-cloak>
                                                        {!! icon('minus', 'h-4 w-4', 'absolute inset-0 text-white') !!}
                                                    </span>
                                                </button>
                                            </div>
                                        </th>
                                    @endif

                                    {{-- Sub-row Toggle Header: master expand/collapse, directly
                                         above the row chevrons it drives. --}}
                                    @if($hasSubRows)
                                        <th scope="col" class="w-10 {{ $headerPadding }}">
                                            @if($isSubRowsExpandable)
                                                @include('wire-table::tables.partials.sub-rows-master-toggle', [
                                                    'allRowsExpanded' => $allRowsExpanded,
                                                    'label' => $table->getSubRowsToggleLabel(),
                                                ])
                                            @else
                                                {{ $table->getSubRowsToggleLabel() ?? '' }}
                                            @endif
                                        </th>
                                    @endif

                                    {{-- Actions Header (Start Position) --}}
                                    @if($hasActions && $actionsPosition === 'start')
                                        <th
                                                scope="col"
                                                class="{{ $headerPadding }} font-semibold {{ $actionsAlignmentClass }}"
                                                @if($actionsColumnWidth) style="width: {{ $actionsColumnWidth }}" @endif
                                        >
                                            {{ $actionsColumnLabel }}
                                        </th>
                                    @endif

                                    {{-- Column Headers --}}
                                    @foreach($visibleColumns as $column)
                                        @php $hm = $columnMeta[$column->getName()]; @endphp
                                        <th
                                                scope="col"
                                                data-column="{{ $column->getName() }}"
                                                class="{{ $headerPadding }} {{ $hm['alignment'] }} font-semibold {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} {{ $hm['responsive'] }}"
                                                @if($column->getWidth()) style="width: {{ $column->getWidth() }}" @endif
                                                @if($hm['extraHeader']) {!! $hm['extraHeader'] !!} @endif
                                        >
                                            @if($column->isSortable() && $table->isSortable())
                                                <button
                                                        type="button"
                                                        wire:click="sortTable('{{ $column->getName() }}')"
                                                        data-testid="table-sort-{{ $column->getName() }}"
                                                        class="group inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200"
                                                >
                                                    <span>{{ $column->getLabel() }}</span>
                                                    <span class="flex-none">
                                                @if($sortColumn === $column->getName())
                                                            @if($sortDirection === 'asc')
                                                                {!! icon('outline:chevron-up', 'h-4 w-4', 'text-gray-500 dark:text-gray-400') !!}
                                                            @else
                                                                {!! icon('outline:chevron-down', 'h-4 w-4', 'text-gray-500 dark:text-gray-400') !!}
                                                            @endif
                                                        @else
                                                            {!! icon('outline:chevron-up-down', 'h-4 w-4', 'text-gray-500 dark:text-gray-400 opacity-0 group-hover:opacity-100') !!}
                                                        @endif
                                            </span>
                                                </button>
                                            @else
                                                {{ $column->getLabel() }}
                                            @endif
                                        </th>
                                    @endforeach

                                    {{-- Actions Header (End Position - Default) --}}
                                    @if($hasActions && $actionsPosition === 'end')
                                        <th
                                                scope="col"
                                                class="{{ $headerPadding }} font-semibold {{ $actionsAlignmentClass }}"
                                                @if($actionsColumnWidth) style="width: {{ $actionsColumnWidth }}" @endif
                                        >
                                            {{ $actionsColumnLabel }}
                                        </th>
                                    @endif
                                </tr>

                                {{-- Row Filters --}}
                                @if($hasColumnFilters)
                                    <tr @if($tableRole) aria-rowindex="2" @endif
                                        class="bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-100 dark:border-gray-700/50">
                                        @if($isSelectable)
                                            <th class="{{ $headerPadding }}"></th>
                                        @endif

                                        {{-- Sub-row Toggle Filter Cell --}}
                                        @if($hasSubRows)
                                            <th class="{{ $headerPadding }}"></th>
                                        @endif

                                        {{-- Actions Filter Cell (Start Position) --}}
                                        @if($hasActions && $actionsPosition === 'start')
                                            <th class="{{ $headerPadding }}"></th>
                                        @endif

                                        @foreach($visibleColumns as $column)
                                            <th class="{{ $headerPadding }}" @if($column->isFilterable()) data-testid="table-filter-{{ $column->getName() }}" @endif>
                                                @if($column->isFilterable())
                                                    {!! $column->renderFilter($columnFilterValues[$column->getName()] ?? null) !!}
                                                @endif
                                            </th>
                                        @endforeach

                                        {{-- Actions Filter Cell (End Position) --}}
                                        @if($hasActions && $actionsPosition === 'end')
                                            <th class="{{ $headerPadding }} text-right">
                                                @if($activeColumnFilters !== [])
                                                    <button
                                                            type="button"
                                                            wire:click="resetColumnFilters"
                                                            class="inline-flex items-center justify-center p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                                            title="{{ __('wire-table::messages.filter_reset_column') }}"
                                                    >
                                                        {!! icon('outline:x-mark', 'w-4 h-4') !!}
                                                    </button>
                                                @endif
                                            </th>
                                        @endif
                                    </tr>
                                @endif
                                </thead>

                                <tbody
                                        class="divide-y divide-gray-100 dark:divide-gray-700"
                                        @if($recordActionsRootEnabled)
                                            x-data="wireRecordActions({ bindings: @js($recordActionBindings), contextMenu: {{ $rowContextMenuEnabled ? 'true' : 'false' }}, keyboard: @js($recordKeyboardConfig), active: @js($activeRowConfig), gestures: @js($gestureConfig) })"
                                            {{-- Bound whenever the controller is mounted, not only for pointer
                                                 bindings: a click also moves the active row, which is what makes a
                                                 clicked row visibly the one the arrow keys continue from. --}}
                                            @click="onPointer('click', $event)"
                                            @dblclick="onPointer('dblclick', $event)"
                                            @if($rowContextMenuEnabled)
                                                @contextmenu="onContextMenu($event)"
                                            @endif
                                            @if($keyboardNav)
                                                @keydown="onKeydown($event)"
                                                @focusin="onRowFocus($event)"
                                            @endif
                                        @endif
                                >
                                @if($recordActionsRootEnabled)
                                    @once
                                        @include('wire-table::tables.partials.record-actions-assets')
                                    @endonce
                                @endif
                                @if($rowContextMenuEnabled)
                                    {{-- Core dropdown bundle for any nested action-group dropdown inside a
                                         context-menu item; emitted once per request, not once per row. --}}
                                    @once
                                        @include('wire-core::partials.floating-assets')
                                    @endonce
                                @endif
                                @forelse($records as $record)
                                    @php
                                        $recordKey = $record->{$table->getPrimaryKey()};
                                        $recordUrl = $table->getRecordUrl($record);
                                        $rowIndex = $loop->index;

                                        $groupValue = $hasGrouping ? $table->getGroupComparisonKey($record) : null;
                                        $prevRecord = $hasGrouping && $rowIndex > 0 ? $records[$rowIndex - 1] : null;
                                        $nextRecord = $hasGrouping ? ($records[$rowIndex + 1] ?? null) : null;
                                        $isGroupStart = $hasGrouping && ($prevRecord === null || $table->getGroupComparisonKey($prevRecord) !== $groupValue);
                                        $isGroupEnd = $hasGrouping && ($nextRecord === null || $table->getGroupComparisonKey($nextRecord) !== $groupValue);

                                        // Right-click context menu. The Table owns both halves: the
                                        // panel's shape (a skeleton compiled once for the table) and
                                        // the decision that a row with no visible action gets none.
                                        //
                                        // The `@if` at the emit site therefore looks redundant — the
                                        // string is already empty — and it is NOT. It emits a pair of
                                        // Livewire morph markers around the <template>, and those are
                                        // load-bearing: the row's children CAN change between renders
                                        // (a column reorder rewrites the cell list), and morphdom needs
                                        // that block boundary to pair them up. Removing the `@if` to
                                        // save two comment nodes per row breaks the reorder — the
                                        // header reorders, the body does not. Both fuses stayed green
                                        // through that; only verify-gesture-lab.mjs caught it
                                        // ("dragging a column header moves that column, in the header
                                        // and the body alike").
                                        $rowContextMenuPanel = $table->getRowContextMenuPanel($record);

                                        // Per-record sub-rows (subRowsVisible): decides this row's
                                        // chevron and panel only — the expander cell itself still
                                        // renders, empty, or the columns stop lining up.
                                        $recordHasSubRows = $hasSubRows && $table->hasSubRowsFor($record);
                                    @endphp

                                    {{-- Group header. Compiled once for the table; this group
                                         supplies its label. --}}
                                    @if($isGroupStart){!! $table->getGroupHeaderRow($record, $colSpan) !!}@endif
                                    @php
                                        $recordKeyJs = \Illuminate\Support\Js::from((string) $recordKey)->toHtml();
                                        // Values only — the shape was settled once, above.
                                        $rowOpen = $rowSkeleton->fill([
                                            'rowClass' => e($table->getRowClasses($record, $rowIndex)),
                                            'keyJs' => $recordKeyJs,
                                            'tabindex' => $rowIndex === 0 ? '0' : '-1',
                                            'rowIndex' => (string) $rowIndex,
                                            'ariaRowIndex' => (string) ($headerRowCount + $rangeFrom + $rowIndex),
                                            'key' => e((string) $recordKey),
                                        ]);
                                    @endphp
                                    {!! $rowOpen !!}
                                        @if($rowContextMenuPanel !== ''){!! $rowContextMenuPanel !!}@endif
                                        {{-- Selection cell. The shape was settled once, in the
                                             preamble; this row supplies the key. --}}
                                        @if($isSelectable){!! $selectionCellSkeleton->fill(['keyJs' => $recordKeyJs]) !!}@endif

                                        {{-- Sub-row expander cell. Three shapes, each compiled once
                                             for the table; this row picks one and supplies its key.
                                             The `@if` the partial keeps inside itself is what puts
                                             this row's morph markers back — see the partial. --}}
                                        @if($hasSubRows){!! $table->getSubRowCell(
                                            $recordKeyJs,
                                            $isSubRowsExpandable && $recordHasSubRows,
                                            $component->isRowExpanded($recordKey),
                                        ) !!}@endif

                                        {{-- Actions Cell (Start Position) --}}
                                        @if($hasActions && $actionsPosition === 'start')
                                            {{-- Tags touch, and the @foreach stays. The whitespace between
                                                 them was four DOM text nodes per row for markup that never
                                                 varies; the loop's morph markers are NOT surplus, because
                                                 the button list genuinely changes per record (an action can
                                                 be non-executable for one row and not the next) — the case
                                                 §8f showed those markers exist for. --}}
                                            <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}"><div class="flex flex-wrap items-center gap-1 {{ $actionsJustifyClass }}">@foreach($actions as $action){!! $action->render($record, $actionClick) !!}@endforeach</div></td>
                                        @endif

                                        {{-- Column Cells. Assembled in PHP rather than by a @foreach, so
                                             the row emits no whitespace between the cells and Blade runs
                                             no per-cell conditional — the two things that made a <td>
                                             cost ~900 bytes and a fistful of DOM nodes to say `v`. The
                                             cell is $cm['cell'], compiled once per column above. --}}
                                        @php
                                            $cellsHtml = '';
                                            $linkOpen = $recordUrl
                                                ? '<a href="'.e($recordUrl).'" class="hover:text-primary-600 dark:hover:text-primary-400">'
                                                : '';

                                            foreach ($visibleColumns as $column) {
                                                $cm = $columnMeta[$column->getName()];
                                                $cell = $cm['responsiveDisplay']
                                                    ? $column->renderResponsiveCell($record)
                                                    : $column->renderCellFast($record);

                                                // A record url turns every non-editable cell into a link to
                                                // the record; an editable one keeps its own interaction.
                                                if ($linkOpen !== '' && ! $cm['editable']) {
                                                    $cell = $linkOpen.$cell.'</a>';
                                                }

                                                $cellsHtml .= $cm['cell']->fill(['content' => $cell]);
                                            }
                                        @endphp
                                        {!! $cellsHtml !!}

                                        {{-- Actions Cell (End Position - Default) --}}
                                        @if($hasActions && $actionsPosition === 'end')
                                            {{-- Tags touch, and the @foreach stays. The whitespace between
                                                 them was four DOM text nodes per row for markup that never
                                                 varies; the loop's morph markers are NOT surplus, because
                                                 the button list genuinely changes per record (an action can
                                                 be non-executable for one row and not the next) — the case
                                                 §8f showed those markers exist for. --}}
                                            <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}"><div class="flex flex-wrap items-center gap-1 {{ $actionsJustifyClass }}">@foreach($actions as $action){!! $action->render($record, $actionClick) !!}@endforeach</div></td>
                                        @endif
                                    </tr>

                                    {{-- Sub-rows --}}
                                    @if($recordHasSubRows && $component->isRowExpanded($recordKey))
                                        @php
                                            $subRows = $component->getSubRows($record);
                                        @endphp
                                        @include('wire-table::tables.partials.sub-rows', [
                                            'table' => $table,
                                            'component' => $component,
                                            'record' => $record,
                                            'recordKey' => $recordKey,
                                            'subRows' => $subRows,
                                            'visibleSubRowColumns' => $visibleSubRowColumns,
                                            'colSpan' => $colSpan,
                                            'cellPadding' => $cellPadding,
                                            'isBordered' => $isBordered,
                                        ])
                                    @endif

                                    {{-- Group subtotal --}}
                                    @if($isGroupEnd && $hasGroupSummaries)
                                        @include('wire-table::tables.partials.group-subtotal', [
                                            'table' => $table,
                                            'component' => $component,
                                            'groupSummaries' => $component->computeGroupSummaries($groupValue),
                                            'visibleColumns' => $visibleColumns,
                                            'colSpan' => $colSpan,
                                            'cellPadding' => $cellPadding,
                                            'isBordered' => $isBordered,
                                            'isSelectable' => $isSelectable,
                                            'hasActions' => $hasActions,
                                            'actionsPosition' => $actionsPosition,
                                        ])
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ $colSpan }}" class="px-6 py-16 text-center">
                                            {{-- Canonical empty-state surface; filter-empty adds a reset action. --}}
                                            @include('wire-core::partials.empty-state', [
                                                'icon' => $isEmptyDueToFilter
                                                    ? 'outline:magnifying-glass'
                                                    : ($table->getEmptyStateIcon() ?? 'outline:inbox'),
                                                'heading' => $isEmptyDueToFilter
                                                    ? __('wire-table::messages.empty_filter_heading')
                                                    : $table->getEmptyStateHeading(),
                                                'description' => $isEmptyDueToFilter
                                                    ? __('wire-table::messages.empty_no_records_match')
                                                    : $table->getEmptyStateDescription(),
                                                'actions' => $isEmptyDueToFilter
                                                    ? [view('wire-table::tables.partials.reset-filters-button')->render()]
                                                    : $table->getEmptyStateActionsHtml(),
                                            ])
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>

                                {{-- Summary footer --}}
                                @if($hasSummaries)
                                    @php $summaryScope = $component->getSummaryScope(); @endphp
                                    @include('wire-table::tables.partials.summary-footer', [
                                        'table' => $table,
                                        'component' => $component,
                                        'summaries' => $component->computeTableSummaries($summaryScope),
                                        'subRowGrandTotals' => $component->computeSubRowGrandTotals($summaryScope),
                                        'summaryScope' => $summaryScope,
                                        'summaryScopeOptions' => $component->getSummaryScopeOptions(),
                                        'isSelectable' => $isSelectable,
                                        'hasActions' => $hasActions,
                                        'actionsPosition' => $actionsPosition,
                                        'cellPadding' => $cellPadding,
                                        'isBordered' => $isBordered,
                                        'visibleColumns' => $visibleColumns,
                                        'colSpan' => $colSpan,
                                    ])
                                @endif
                            </table>

                            @if($isFillEnabled)
                                @include('wire-table::tables.partials.fill-handle', [
                                    'fillColumns' => $fillColumns,
                                    'fillMax' => $table->getFillMaxRecords(),
                                ])
                            @endif
                        @else
                            {{-- No columns visible state --}}
                            <div class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="rounded-full bg-amber-100 dark:bg-amber-900/30 p-3">
                                        {!! icon('outline:eye-slash', 'h-8 w-8', 'text-amber-500 dark:text-amber-400') !!}
                                    </div>
                                    <div>
                                        <h3 class="text-base font-medium text-gray-900 dark:text-white">
                                            {{ __('wire-table::messages.empty_no_columns') }}
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('wire-table::messages.empty_no_columns_hint') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Mobile Cards (Stacked Layout) --}}
                    @if($isStackedOnMobile && $hasVisibleColumns)
                        <div class="{{ $cardsVisibleClass }}">
                            {{-- The card view's select-all. It has to live here because the
                                 header row that carries it on desktop is hidden at this
                                 width — without it, selecting a page on a phone means
                                 tapping every card. Always rendered, never behind a
                                 gesture: what is not visible is not found. --}}
                            @if($isSelectable)
                                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-4 py-2.5">
                                    <button
                                            type="button"
                                            x-on:click="toggleAll()"
                                            role="checkbox"
                                            :aria-checked="allSelected ? 'true' : (someSelected ? 'mixed' : 'false')"
                                            aria-label="{{ __('wire-table::messages.select_all_on_page') }}"
                                            data-testid="table-card-select-all"
                                            class="relative h-5 w-5 shrink-0 rounded border transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                                            :class="(allSelected || someSelected) ? 'bg-primary-600 border-primary-600' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600'"
                                    >
                                        <span x-show="allSelected" x-cloak>{!! $selectCheckIcon !!}</span>
                                        <span x-show="someSelected" x-cloak>
                                            {!! icon('minus', 'h-4 w-4', 'absolute inset-0 text-white') !!}
                                        </span>
                                    </button>
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        {{ __('wire-table::messages.select_all') }}
                                    </span>
                                    <span class="ml-auto text-xs tabular-nums text-gray-500 dark:text-gray-400"
                                          data-testid="table-card-select-count">
                                        <span x-show="selectedCount === 0">{{ __('wire-table::messages.selection_page_of_total', ['page' => count($pageRecordKeys), 'total' => $recordCount]) }}</span>
                                        <span x-show="selectedCount > 0" x-cloak
                                              x-text="@js(__('wire-table::messages.selection_selected_of_total', ['count' => ':count', 'total' => $recordCount])).replace(':count', selectedCount)"></span>
                                    </span>
                                </div>
                            @endif

                            @php
                                // A card is a record, not the column order in disguise: the
                                // slots below carry the hierarchy (what it is, whose it is,
                                // how much), and MobileCard derives them when nothing says.
                                $card = $table->getMobileCard(array_values($visibleColumns));
                                $cardTitle = $card->title();
                                $cardSubtitle = $card->subtitle();
                                $cardMetric = $card->metric();
                                $cardMeta = $card->meta();
                                $cardDetails = $card->details();
                                $mobileCell = fn($column, $record) => $column->hasResponsiveDisplay()
                                    ? $column->renderMobileCell($record)
                                    : $column->renderCellFast($record);
                            @endphp
                            {{-- Record actions are a desktop pointer affordance: the delegated
                                 controller lives on the desktop <tbody> only, so click/dblclick/
                                 right-click record actions do not apply to these touch cards. The
                                 same actions reach a finger as ordinary buttons instead — see
                                 $mobileActions, which folds the behaviour-only bindings in. --}}
                            {{-- Mind the whitespace below: the tags touch on purpose.

                                 This is a SECOND full rendering of every record — the desktop
                                 rows are in the same document, hidden by CSS at this width — so
                                 the card's layout is emitted per row and every run of whitespace
                                 between two tags is one more DOM text node the morph walks on
                                 every commit. Measured before this was closed up: 4391 B and 36
                                 whitespace nodes per row, 225% on top of the row itself.

                                 Whitespace BETWEEN ATTRIBUTES is free (no node), so the
                                 attributes stay laid out, and every conditional stays exactly
                                 where it was — the morph markers an @if emits are load-bearing
                                 (see §8f in architecture/plans/render-engine-htmlable-first.md).
                                 Mind also that a directive must never be glued straight onto a
                                 Blade comment: Livewire then fails to inject its opening marker.
                            --}}
                            @forelse($records as $record)
                                @php
                                    $recordKey = $record->{$table->getPrimaryKey()};
                                    $recordUrl = $table->getRecordUrl($record);
                                @endphp
                                <div
                                        class="{{ $table->getRowCardClasses($record) }}"
                                        data-testid="table-card"
                                        data-row-key="{{ $recordKey }}"
                                        @if($isSelectable) :class="isSelected(@js((string) $recordKey)) ? 'ring-2 ring-primary-500 ring-inset bg-primary-50/50 dark:bg-primary-900/30' : ''" @endif
                                >{{-- Header: identifier on the left, the figure the list is read
                                    for on the right, actions after it. --}}<div
                                        class="flex items-start gap-3 px-4 pt-4 {{ count($cardDetails) > 0 ? 'pb-3' : 'pb-4' }}"
                                >@if($isSelectable)<label class="flex items-center pt-0.5 flex-shrink-0" data-select-cell><input
                                        type="checkbox"
                                        x-on:change="toggle(@js((string) $recordKey))"
                                        :checked="isSelected(@js((string) $recordKey))"
                                        data-testid="table-card-select"
                                        aria-label="{{ __('wire-table::messages.select_row') }}"
                                        class="h-5 w-5 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-offset-gray-800 touch-manipulation"
                                ><span class="sr-only">{{ __('wire-table::messages.select_row') }}</span></label>@endif<div
                                        class="flex-1 min-w-0"
                                ><div class="flex items-baseline gap-3">@if($cardTitle)<div
                                        class="min-w-0 font-medium text-gray-900 dark:text-white truncate text-base"
                                >@if($recordUrl)<a
                                        href="{{ $recordUrl }}"
                                        class="hover:text-primary-600 dark:hover:text-primary-400"
                                >{!! $mobileCell($cardTitle, $record) !!}</a>@else{!! $mobileCell($cardTitle, $record) !!}@endif</div>@endif{{--
                                    Amounts line up on one right edge and use tabular figures, so
                                    a column of them can be compared. --}}
                                    @if($cardMetric)<div
                                        class="ml-auto shrink-0 font-semibold text-gray-900 dark:text-white text-base tabular-nums whitespace-nowrap"
                                        data-testid="table-card-metric"
                                >{!! $mobileCell($cardMetric, $record) !!}</div>@endif</div>@if($cardSubtitle)<div
                                        class="mt-0.5 text-sm text-gray-600 dark:text-gray-300 truncate"
                                >{!! $mobileCell($cardSubtitle, $record) !!}</div>@endif
                                    @if(count($cardMeta) > 0)<div
                                        class="mt-1.5 flex flex-wrap items-center gap-2"
                                >@foreach($cardMeta as $metaColumn)<span>{!! $mobileCell($metaColumn, $record) !!}</span>@endforeach</div>@endif</div>{{--
                                    Only a collapsed group belongs beside the title: it is one icon
                                    wide. Labelled buttons go to their own row below — sharing this
                                    line, they take the width the identity needs and the title
                                    collapses to nothing (min-w-0 does the rest). --}}
                                    @if($hasMobileActions && $collapseMobileActions)<div
                                        class="flex items-center justify-end flex-shrink-0 -mr-1"
                                >{!! $mobileActionGroup->render($record, $actionClick) !!}</div>@endif</div>{{--
                                Whatever no slot claimed, as the label/value grid --}}
                                @if(count($cardDetails) > 0)<dl
                                        class="px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-2 {{ $isSelectable ? 'pl-12' : '' }}"
                                >@php $detailCount = count($cardDetails); @endphp
                                    @foreach($cardDetails as $index => $column)
                                        @php $isLastOdd = ($index === $detailCount - 1) && ($detailCount % 2 === 1); @endphp
                                        <div class="{{ $isLastOdd ? 'col-span-2' : 'col-span-1' }}"><dt
                                        class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5"
                                >{{ $column->getLabel() }}</dt><dd
                                        class="text-sm text-gray-900 dark:text-white"
                                >{!! $mobileCell($column, $record) !!}</dd></div>@endforeach</dl>@endif
                                @if($hasMobileActions && ! $collapseMobileActions)<div
                                        class="flex flex-wrap items-center gap-2 px-4 pb-3 {{ $isSelectable ? 'pl-12' : '' }}"
                                        data-testid="table-card-actions"
                                >@foreach($mobileActions as $action){!! $action->render($record, $actionClick) !!}@endforeach</div>@endif{{--
                                Sub-rows: the same children, subtotal, "show more" and per-child
                                actions the desktop panel renders. Guarded here rather than inside
                                the partial — the card's own toggle button lives there, in two
                                branches. --}}
                                @if($hasSubRows && $table->hasSubRowsFor($record))
                                    @include('wire-table::tables.partials.sub-rows-mobile', [
                                        'table' => $table,
                                        'component' => $component,
                                        'record' => $record,
                                        'recordKey' => $recordKey,
                                        'visibleSubRowColumns' => $visibleSubRowColumns,
                                        'isExpanded' => $component->isRowExpanded($recordKey),
                                        'isSubRowsExpandable' => $isSubRowsExpandable,
                                        'isSelectable' => $isSelectable,
                                    ])
                                @endif</div>
                            @empty
                                <div class="px-4 py-12 text-center bg-white dark:bg-gray-800">
                                    {{-- The same canonical surface the desktop table's empty state
                                         uses, so a custom icon/description, the filter-empty reset
                                         and the empty-state actions reach a phone too. The action
                                         copies drop their keyboard shortcut: both layouts are in
                                         the document at every width, and a rendered button binds
                                         its shortcut as a window listener. --}}
                                    @include('wire-core::partials.empty-state', [
                                        'icon' => $isEmptyDueToFilter
                                            ? 'outline:magnifying-glass'
                                            : ($table->getEmptyStateIcon() ?? 'outline:inbox'),
                                        'iconSize' => 'h-6 w-6',
                                        'heading' => $isEmptyDueToFilter
                                            ? __('wire-table::messages.empty_filter_heading')
                                            : $table->getEmptyStateHeading(),
                                        'description' => $isEmptyDueToFilter
                                            ? __('wire-table::messages.empty_no_records_match')
                                            : $table->getEmptyStateDescription(),
                                        'actions' => $isEmptyDueToFilter
                                            ? [view('wire-table::tables.partials.reset-filters-button')->render()]
                                            : $table->getMobileEmptyStateActionsHtml(),
                                    ])
                                </div>
                            @endforelse

                            {{-- Totals for the card view. The desktop <tfoot> lives inside
                                 the table this layout hides, so without this a phone shows
                                 no totals at all. --}}
                            @if($hasSummaries)
                                @php $cardSummaryScope = $component->getSummaryScope(); @endphp
                                @include('wire-table::tables.partials.summary-footer-mobile', [
                                    'table' => $table,
                                    'component' => $component,
                                    'summaries' => $component->computeTableSummaries($cardSummaryScope),
                                    'subRowGrandTotals' => $component->computeSubRowGrandTotals($cardSummaryScope),
                                    'summaryScope' => $cardSummaryScope,
                                    'summaryScopeOptions' => $component->getSummaryScopeOptions(),
                                    'visibleColumns' => $visibleColumns,
                                ])
                            @endif
                        </div>
                    @endif

                    {{-- Footer / Pagination --}}
                    @if($isPaginated && $hasVisibleColumns)
                        @php
                            // The range comes from the preamble — aria-rowindex needs it before
                            // the body renders. Which of the three questions may be asked of the
                            // records is PagingRenderPlan's business, not this view's.
                            $hasMultiplePages = $plan->paging()->hasLinks;
                            $knowsTotal = $hasPaginator;
                            $knowsRange = $plan->paging()->knowsRange;
                            $total = $recordCount;
                            $from = $rangeFrom;
                            $to = $rangeTo;
                        @endphp

                        <div
                                class="px-4 lg:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/30">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                {{-- Per Page Selector - Always visible when paginated --}}
                                <div class="flex items-center gap-2">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.show') }}</span>
                                    <select
                                            wire:model.live="tableState.pagination.perPage"
                                            data-testid="table-per-page"
                                            aria-label="{{ __('wire-table::messages.show') }}"
                                            class="rounded-lg border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1.5"
                                    >
                                        @foreach($table->getPerPageOptions() as $option)
                                            {{-- Mark the live value server-side: without it the first
                                                 paint shows the first option regardless of state, and a
                                                 morph can snap the control back to it. --}}
                                            <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option === \NyonCode\WireTable\Table::PER_PAGE_ALL ? __('wire-table::messages.per_page_all') : $option }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.records') }}</span>
                                </div>

                                {{-- Results info. A simple paginator knows its offsets but not
                                     the total, so it says "showing 1 - 10" and stops; a cursor
                                     paginator knows neither and says nothing rather than a
                                     confident wrong number. --}}
                                @if($knowsRange)
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('wire-table::messages.showing') }} <span
                                                class="font-medium text-gray-700 dark:text-gray-300">{{ $from }}</span> -
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $to }}</span>@if($knowsTotal) {{ __('wire-table::messages.of') }} <span
                                                class="font-medium text-gray-700 dark:text-gray-300">{{ $total }}</span>@endif
                                        {{ __('wire-table::messages.records') }}
                                    </div>
                                @endif

                                {{-- Pagination Links - Only when multiple pages --}}
                                @if($hasMultiplePages)
                                    <div>
                                        {{ $records->links($plan->paging()->linksView) }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Action Modal.

                     An island, so opening one costs the modal instead of the whole
                     table. The saving is not in skipping this island — a closed
                     modal renders nothing anyway — it is that a call TARGETING an
                     island makes Livewire skipRender() the component and return
                     only the island, which is why the buttons carry
                     wire:island="action-modals".

                     `always` is load-bearing: without it an island is SKIPPED on
                     every request after the mount unless that request targeted it,
                     and a modal opened by anything other than a wire:island button
                     — a keyboard shortcut, the row context menu, openOn(), a test
                     calling the method — would simply never render. With it the
                     behaviour is exactly today's, and a targeted call is the only
                     thing that changes: it skips the rest of the table.

                     $component is a view variable, and an island body sees only
                     the component plus its public properties, so it is re-bound
                     from $__livewire here. --}}
                @island('action-modals', always: true)
                    @php($component = $__livewire)
                    @include('wire-table::tables.partials.action-modal')
                @endisland

                {{-- Halt Modal --}}
                @include('wire-table::tables.partials.halt-modal')

                {{-- Keyboard shortcut help (`?`) --}}
                @include('wire-table::tables.partials.shortcut-help-modal')

                {{-- One clipboard controller and one feedback pill for the page, not
                     one per cell — see record-copy.js. Inside the wrapper because the
                     pill is a real element and a Livewire component may have only one
                     root; `@once` because a page may hold several tables and the
                     controller binds to the document, not to this table. --}}
                @if($hasCopyableColumn)
                    @once
                        @include('wire-core::partials.copy-assets')
                    @endonce
                @endif

                </div> {{-- Close table wrapper --}}

                {{-- Close polling wrapper --}}
                        @if($pollingAttribute)
            </div>
    @endif
@endif {{-- End lazy loading wrapper --}}
