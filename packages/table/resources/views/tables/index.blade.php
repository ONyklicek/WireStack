@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    use Illuminate\Support\Collection;
    use NyonCode\WireTable\Table;

    assert($table instanceof Table);

    /** @var LengthAwarePaginator|Collection $records */
    /** @var mixed $component */

    $isLazy = $table->isLazy();
    $isTableReady = $component->isTableReady();
    $lazyPlaceholder = $table->getLazyPlaceholder();

    // Polling
    $pollingConfig = $component->getTablePollingConfig();
    $pollingAttribute = $component->getTablePollingAttribute();

    // Table state — read once via the state container; the legacy magic
    // properties ($component->tableFilters, …) build the deprecation map on
    // every access and must not be used in per-row/per-column loops.
    // Floating filter/column-toggle panels present as a bottom sheet on mobile
    // unless disabled via Table::sheetOnMobile(false) or the global config.
    $sheetOnMobile = $table->usesSheetOnMobile();
    $sheetBp = $table->getMobileBreakpoint();
    $sheetBpPx = \NyonCode\WireCore\Foundation\Support\MobileSheet::px($sheetBp);
    $sheetPanel = \NyonCode\WireCore\Foundation\Support\MobileSheet::panel($sheetBp);
    $sheetMotion = \NyonCode\WireCore\Foundation\Support\MobileSheet::motion($sheetBp);
    $sheetBackdrop = \NyonCode\WireCore\Foundation\Support\MobileSheet::backdropHide($sheetBp);
    $tableSearch = $component->tableState->get('search');
    $tableFilters = $component->tableState->get('filters', []) ?? [];
    $columnFilterValues = $component->tableState->get('columnFilters', []) ?? [];
    $sortColumn = $component->tableState->get('sort.column');
    $sortDirection = $component->tableState->get('sort.direction', 'asc');
    $perPage = (int) $component->tableState->get('pagination.perPage', $table->getPerPage());
    // Treat a filter as active only when it holds a real value. A range filter
    // that was typed then cleared leaves ['min' => '', 'max' => ''] — a truthy
    // array that plain array_filter would wrongly count as active.
    $filterHasValue = function ($value) use (&$filterHasValue) {
        if (is_array($value)) {
            foreach ($value as $inner) {
                if ($filterHasValue($inner)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null && $value !== '';
    };
    $activeTableFilters = array_filter($tableFilters, $filterHasValue);
    $activeColumnFilters = array_filter($columnFilterValues, $filterHasValue);

    $actions = $table->getRowActionsForDisplay(); // applies the configured row-action style (solid/quiet)
    $bulkActions = $table->getBulkActions();
    $headerActions = $table->getHeaderActions();
    $filters = $table->getFilters();

    $hasActions = $table->hasActions();
    // Mobile stacked cards can collapse the row actions into one dropdown group.
    $collapseMobileActions = $table->shouldCollapseActionsOnMobile();
    $mobileActionGroup = $collapseMobileActions ? $table->getMobileActionGroup() : null;
    // Host click resolver: the single place that maps a row action to the table's
    // executeTableAction/openActionModal (core action views stay host-agnostic).
    $actionClick = new \NyonCode\WireTable\Actions\TableActionClickResolver();
    $rowContextMenuEnabled = $table->hasRowContextMenu(); // dedicated actions, independent of the actions column
    // Record actions: whole-row click/dblclick bindings (name map) + whether the
    // delegated controller must be mounted at all (bindings, a context menu, or
    // keyboard navigation).
    $recordActionBindings = $table->getRecordActionBindings();
    $hasRecordPointer = $recordActionBindings !== [];
    $keyboardNav = $table->keyboardNavEnabled();
    $tableRole = $table->getTableRole();
    $recordKeyboardConfig = $keyboardNav ? $table->getRecordActionKeyboardConfig() : null;
    $recordActionsRootEnabled = $hasRecordPointer || $rowContextMenuEnabled || $keyboardNav;
    $hasBulkActions = !empty($bulkActions);
    $hasHeaderActions = !empty($headerActions);
    $hasFilters = !empty($filters);
    $isSelectable = $table->isSelectable();
    // Record-invariant chrome icon resolved once per render (IconManager owns the
    // SVG cache); the row loop echoes the string instead of re-entering @icon per row.
    $selectCheckIcon = $isSelectable
        ? app(\NyonCode\WireCore\Foundation\Icons\IconManager::class)->render('check', 'h-4 w-4', 'absolute inset-0 text-white')
        : '';
    $hasSummaries = $component->tableHasSummaries();

    // Selection is managed client-side (Alpine) and entangled deferred — a
    // checkbox click costs no server roundtrip. When the footer renders
    // summaries, changes are committed (debounced) so selection-scope totals
    // and the scope toggle stay correct.
    $pageRecordKeys = [];
    if ($isSelectable) {
        foreach ($records as $pageRecord) {
            $pageRecordKeys[] = (string) $pageRecord->{$table->getPrimaryKey()};
        }
    }
    $selectionSyncLive = $isSelectable && $hasSummaries;
    $isPaginated = $table->isPaginated();
    $visibleColumns = array_filter($table->getColumns(), fn($c) => $c->canView() && $component->isColumnVisible($c->getName()));
    $hasVisibleColumns = count($visibleColumns) > 0;
    // Column-static render metadata: resolved once per column here instead of
    // re-calling these getters for every cell (N rows × M columns → M). Reused by
    // the header and body. Keyed by column name.
    $columnMeta = [];
    foreach ($visibleColumns as $col) {
        $columnMeta[$col->getName()] = [
            'wrapClass' => $col->shouldWrap() ? '' : 'whitespace-nowrap',
            'alignment' => $col->getAlignmentClass(),
            'responsive' => $col->getResponsiveClasses(),
            'editable' => $col->isEditable(),
            'responsiveDisplay' => $col->hasResponsiveDisplay(),
        ];
    }
    // Columns a fill drag may write. The client additionally requires the cell to
    // have actually rendered an editable root, so a per-record disabled cell is
    // skipped without this list having to know about records.
    $fillColumns = array_values(array_map(
        fn($c) => $c->getName(),
        array_filter($visibleColumns, fn($c) => $c->isFillable()),
    ));
    $isFillEnabled = $table->isFillHandleEnabled() && $fillColumns !== [];
    $filterableColumns = array_filter($table->getColumns(), fn($c) => $c->canView() && $c->isFilterable() && $component->isColumnVisible($c->getName()));
    $hasColumnFilters = count($filterableColumns) > 0;
    $hasSubRows = $table->hasSubRows();
    $isSubRowsExpandable = $hasSubRows && $table->isSubRowsExpandable();
    $allRowsExpanded = $hasSubRows && $component->expandsSubRowsByDefault();
    $hasGrouping = $table->hasGrouping();
    $hasGroupSummaries = $hasGrouping && $component->tableHasGroupSummaries();
    $subRowColumns = $hasSubRows ? $table->getSubRowColumns() : [];
    $visibleSubRowColumns = $hasSubRows ? array_filter($subRowColumns, fn($c) => $c->canView()) : [];
    $colSpan = ($isSelectable ? 1 : 0) + count($visibleColumns) + ($hasActions ? 1 : 0) + ($hasSubRows ? 1 : 0);
    $toggleableColumns = array_filter($table->getColumns(), fn($c) => $c->isToggleable() && $c->canView());
    $visibleToggleableCount = count(array_filter($toggleableColumns, fn($c) => $component->isColumnVisible($c->getName())));
    // Sorting on a phone: the stacked card view hides the header row that holds
    // the sort buttons, so the control has to exist somewhere else.
    $mobileSortableColumns = ($table->isStackedOnMobile() && $table->isSortable())
        ? array_values(array_filter($visibleColumns, fn($c) => $c->isSortable()))
        : [];
    $hasMobileSort = count($mobileSortableColumns) > 0;

    // The view menu earns its place from either section it can hold.
    $hasColumnToggles = count($toggleableColumns) > 0;
    $hasViewMenu = $hasColumnToggles || $isSubRowsExpandable;
    $viewMenuLabel = $hasColumnToggles && ! $isSubRowsExpandable
        ? __('wire-table::messages.toggle_columns')
        : __('wire-table::messages.view_options');

    // Action configuration
    $actionsPosition = $table->getActionsPosition(); // 'start' or 'end'
    $actionsAlignment = $table->getActionsAlignment(); // 'left', 'center', 'right'
    $actionsAlignmentClass = $table->getActionsAlignmentClass(); // literal text-* utility
    $actionsJustifyClass = $table->getActionsJustifyClass(); // literal justify-* utility
    $actionsColumnLabel = $table->getActionsColumnLabel() ?? __('wire-table::messages.actions_label');
    $actionsColumnWidth = $table->getActionsColumnWidth();

    // Table styling
    $isCompact = $table->isCompact();
    $isBordered = $table->isBordered();
    // Row hover/striping/tint now composed in Table::getRowClasses($record, $rowIndex).
    $cellPadding = $isCompact ? 'px-4 py-2' : 'px-6 py-4';
    $headerPadding = $isCompact ? 'px-4 py-2' : 'px-6 py-3';

    // Responsive layout — class maps owned by the Table (literal Tailwind names).
    $isStackedOnMobile = $table->isStackedOnMobile();
    $tableHiddenClass = $table->getStackedTableHiddenClass();
    $cardsVisibleClass = $table->getStackedCardsVisibleClass();

    // Check if search/filter is active but no results
    $hasActiveFilters = !empty($tableSearch) || $activeTableFilters !== [] || $activeColumnFilters !== [];
    $recordCount = $records instanceof LengthAwarePaginator ? $records->total() : $records->count();
    $isEmptyDueToFilter = $hasActiveFilters && $recordCount === 0;
@endphp

{{-- Lazy loading: trigger load when visible --}}
@if($isLazy && !$isTableReady)
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
    {{-- Polling wrapper --}}
    @if($pollingAttribute)
        <div {!! $pollingAttribute !!}>
            @endif

            <div
                    class="w-full"
                    wire:key="table-wrapper"
                    @if($isSelectable)
                        data-page-keys="{{ json_encode($pageRecordKeys) }}"
                        data-selection-root
                        x-data="{
                            selected: $wire.entangle('tableState.selection.records'),
                            /* 'keys' → `selected` is the selection.
                               'all'  → everything the filter matches is selected and
                                        `selected` holds the exclusions, so the same
                                        toggle works for both and no key list of the
                                        whole result set ever reaches the browser. */
                            mode: $wire.entangle('tableState.selection.mode'),
                            matching: {{ $recordCount }},
                            commitTimer: null,
                            get pageKeys() { return JSON.parse(this.$root.dataset.pageKeys || '[]'); },
                            get selectsAll() { return this.mode === 'all'; },
                            get selectedCount() { return this.selectsAll ? Math.max(0, this.matching - this.selected.length) : this.selected.length; },
                            get allSelected() { return this.pageKeys.length > 0 && this.pageKeys.every(k => this.isSelected(k)); },
                            get someSelected() { return this.selectedCount > 0 && !this.allSelected; },
                            isSelected(key) { return this.selectsAll ? !this.selected.includes(key) : this.selected.includes(key); },
                            toggle(key) {
                                this.selected = this.selected.includes(key)
                                    ? this.selected.filter(k => k !== key)
                                    : [...this.selected, key];
                                this.queueCommit();
                            },
                            toggleAll() {
                                /* Selecting a page unions with other pages instead of
                                   replacing them, which is what silently discarded a
                                   selection when the user paged. */
                                const clear = this.selectsAll || this.allSelected;
                                this.mode = 'keys';
                                this.selected = clear
                                    ? this.selected.filter(k => !this.pageKeys.includes(k))
                                    : [...new Set([...this.selected, ...this.pageKeys])];
                                this.queueCommit();
                            },
                            selectAllMatching() {
                                this.mode = 'all';
                                this.selected = [];
                                this.$wire.$commit();
                            },
                            selectOnlyPage() {
                                this.mode = 'keys';
                                this.selected = [...this.pageKeys];
                                this.$wire.$commit();
                            },
                            deselectAll() {
                                this.mode = 'keys';
                                this.selected = [];
                                this.queueCommit();
                            },
                            queueCommit() {
                                if (! {{ $selectionSyncLive ? 'true' : 'false' }}) return;
                                clearTimeout(this.commitTimer);
                                this.commitTimer = setTimeout(() => this.$wire.$commit(), 350);
                            },
                        }"
                    @endif
            >
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

                                {{-- Header Actions --}}
                                @if($hasHeaderActions)
                                    @foreach($headerActions as $headerAction)
                                        @if($headerAction->canExecute())
                                            {!! $headerAction->render() !!}
                                        @endif
                                    @endforeach
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
                                    @if($tableRole) role="{{ $tableRole }}" @endif
                                    class="w-full {{ $isBordered ? 'border-collapse' : '' }} {{ $table->getTableClass() }}">
                                <thead
                                        class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400 uppercase {{ $table->getHeaderClass() }}">
                                <tr>
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
                                    <tr class="bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-100 dark:border-gray-700/50">
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
                                            x-data="wireRecordActions({ bindings: @js($recordActionBindings), contextMenu: {{ $rowContextMenuEnabled ? 'true' : 'false' }}, keyboard: @js($recordKeyboardConfig) })"
                                            @if($hasRecordPointer)
                                                @click="onPointer('click', $event)"
                                                @dblclick="onPointer('dblclick', $event)"
                                            @endif
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

                                        // Right-click context menu: only render one for rows that
                                        // actually have a visible action.
                                        $rowContextMenuHtml = $rowContextMenuEnabled
                                            ? trim($table->getRowContextMenuHtml($record)->toHtml())
                                            : '';
                                        $hasRowContextMenu = $rowContextMenuHtml !== '';
                                    @endphp

                                    {{-- Group header --}}
                                    @if($isGroupStart)
                                        @include('wire-table::tables.partials.group-header', [
                                            'label' => $table->resolveGroupLabel($record),
                                            'colSpan' => $colSpan,
                                            'cellPadding' => $cellPadding,
                                        ])
                                    @endif
                                    <tr
                                            class="{{ $table->getRowClasses($record, $rowIndex) }} {{ $keyboardNav ? 'focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500' : '' }}"
                                            @if($isSelectable) :class="isSelected(@js((string) $recordKey)) ? 'bg-primary-50 dark:bg-primary-900/20' : ''" @endif
                                            @if($keyboardNav) role="row" tabindex="-1" @endif
                                            wire:key="row-{{ $recordKey }}"
                                            data-testid="table-row"
                                            data-row-key="{{ $recordKey }}"
                                    >
                                        @if($hasRowContextMenu)
                                            {{-- Teleported context-menu panel for this row. It carries no
                                                 per-row Alpine state: the single wireRecordActions controller
                                                 on the <tbody> opens, positions and closes it by record key
                                                 (data-record-menu). <template> is a script-supporting element,
                                                 valid as a direct child of <tr>. --}}
                                            <template x-teleport="body">
                                                <div
                                                        data-record-menu="{{ $recordKey }}"
                                                        class="fixed z-50 min-w-[12rem] origin-top-left rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 focus:outline-none"
                                                        style="display: none; left: 0; top: 0;"
                                                        role="menu"
                                                >
                                                    <div class="py-1">
                                                        {!! $rowContextMenuHtml !!}
                                                    </div>
                                                </div>
                                            </template>
                                        @endif
                                        {{-- Selection Checkbox --}}
                                        @if($isSelectable)
                                            <td class="w-12 {{ $cellPadding }}">
                                                <div class="flex items-center justify-center">
                                                    <button
                                                            type="button"
                                                            x-on:click="toggle(@js((string) $recordKey))"
                                                            role="checkbox"
                                                            :aria-checked="isSelected(@js((string) $recordKey))"
                                                            aria-label="{{ __('wire-table::messages.select_row') }}"
                                                            data-testid="table-row-select"
                                                            class="relative h-4 w-4 rounded border focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
                                                            :class="isSelected(@js((string) $recordKey)) ? 'bg-primary-600 border-primary-600' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 hover:border-gray-400'"
                                                    >
                                                        <span x-show="isSelected(@js((string) $recordKey))" x-cloak>
                                                            {!! $selectCheckIcon !!}
                                                        </span>
                                                    </button>
                                                </div>
                                            </td>
                                        @endif

                                        {{-- Sub-row Toggle Cell --}}
                                        @if($hasSubRows)
                                            <td class="w-10 {{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}">
                                                @if($isSubRowsExpandable)
                                                    @include('wire-table::tables.partials.sub-row-toggle', [
                                                        'recordKey' => $recordKey,
                                                        'isExpanded' => $component->isRowExpanded($recordKey),
                                                    ])
                                                @endif
                                            </td>
                                        @endif

                                        {{-- Actions Cell (Start Position) --}}
                                        @if($hasActions && $actionsPosition === 'start')
                                            <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}">
                                                <div
                                                        class="flex flex-wrap items-center gap-1 {{ $actionsJustifyClass }}">
                                                    @foreach($actions as $action)
                                                        {!! $action->render($record, $actionClick) !!}
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endif

                                        {{-- Column Cells --}}
                                        @foreach($visibleColumns as $column)
                                            @php $cm = $columnMeta[$column->getName()]; @endphp
                                            <td
                                                class="{{ $cellPadding }} {{ $cm['wrapClass'] }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} {{ $cm['alignment'] }} dark:text-white {{ $cm['responsive'] }}"
                                                data-testid="table-cell-{{ $column->getName() }}"
                                                data-column="{{ $column->getName() }}"
                                            >
                                                @if($recordUrl && !$cm['editable'])
                                                    <a href="{{ $recordUrl }}"
                                                       class="hover:text-primary-600 dark:hover:text-primary-400">
                                                        {!! $cm['responsiveDisplay'] ? $column->renderResponsiveCell($record) : $column->renderCellFast($record) !!}
                                                    </a>
                                                @else
                                                    {!! $cm['responsiveDisplay'] ? $column->renderResponsiveCell($record) : $column->renderCellFast($record) !!}
                                                @endif
                                            </td>
                                        @endforeach

                                        {{-- Actions Cell (End Position - Default) --}}
                                        @if($hasActions && $actionsPosition === 'end')
                                            <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}">
                                                <div
                                                        class="flex flex-wrap items-center gap-1 {{ $actionsJustifyClass }}">
                                                    @foreach($actions as $action)
                                                        {!! $action->render($record, $actionClick) !!}
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endif
                                    </tr>

                                    {{-- Sub-rows --}}
                                    @if($hasSubRows && $component->isRowExpanded($recordKey))
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
                                                    : [],
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
                                 right-click record actions do not apply to these touch cards. Touch
                                 users reach the same actions through the visible row-action buttons
                                 and their modals. --}}
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
                                >
                                    {{-- Header: identifier on the left, the figure the list is
                                         read for on the right, actions after it. --}}
                                    <div class="flex items-start gap-3 px-4 pt-4 {{ count($cardDetails) > 0 ? 'pb-3' : 'pb-4' }}">
                                        @if($isSelectable)
                                            <label class="flex items-center pt-0.5 flex-shrink-0">
                                                <input
                                                        type="checkbox"
                                                        x-on:change="toggle(@js((string) $recordKey))"
                                                        :checked="isSelected(@js((string) $recordKey))"
                                                        data-testid="table-card-select"
                                                        aria-label="{{ __('wire-table::messages.select_row') }}"
                                                        class="h-5 w-5 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-offset-gray-800 touch-manipulation"
                                                >
                                                <span class="sr-only">{{ __('wire-table::messages.select_row') }}</span>
                                            </label>
                                        @endif

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-baseline gap-3">
                                                @if($cardTitle)
                                                    <div class="min-w-0 font-medium text-gray-900 dark:text-white truncate text-base">
                                                        @if($recordUrl)
                                                            <a href="{{ $recordUrl }}" class="hover:text-primary-600 dark:hover:text-primary-400">
                                                                {!! $mobileCell($cardTitle, $record) !!}
                                                            </a>
                                                        @else
                                                            {!! $mobileCell($cardTitle, $record) !!}
                                                        @endif
                                                    </div>
                                                @endif

                                                {{-- Amounts line up on one right edge and use tabular
                                                     figures, so a column of them can be compared. --}}
                                                @if($cardMetric)
                                                    <div class="ml-auto shrink-0 font-semibold text-gray-900 dark:text-white text-base tabular-nums whitespace-nowrap"
                                                         data-testid="table-card-metric">
                                                        {!! $mobileCell($cardMetric, $record) !!}
                                                    </div>
                                                @endif
                                            </div>

                                            @if($cardSubtitle)
                                                <div class="mt-0.5 text-sm text-gray-600 dark:text-gray-300 truncate">
                                                    {!! $mobileCell($cardSubtitle, $record) !!}
                                                </div>
                                            @endif

                                            @if(count($cardMeta) > 0)
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                    @foreach($cardMeta as $metaColumn)
                                                        <span>{!! $mobileCell($metaColumn, $record) !!}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Only a collapsed group belongs beside the title: it is one
                                             icon wide. Labelled buttons go to their own row below —
                                             sharing this line, they take the width the identity needs
                                             and the title collapses to nothing (min-w-0 does the rest). --}}
                                        @if($hasActions && $collapseMobileActions)
                                            <div class="flex items-center justify-end flex-shrink-0 -mr-1">
                                                {!! $mobileActionGroup->render($record, $actionClick) !!}
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Whatever no slot claimed, as the label/value grid --}}
                                    @if(count($cardDetails) > 0)
                                        <dl class="px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-2 {{ $isSelectable ? 'pl-12' : '' }}">
                                            @php $detailCount = count($cardDetails); @endphp
                                            @foreach($cardDetails as $index => $column)
                                                @php $isLastOdd = ($index === $detailCount - 1) && ($detailCount % 2 === 1); @endphp
                                                <div class="{{ $isLastOdd ? 'col-span-2' : 'col-span-1' }}">
                                                    <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">
                                                        {{ $column->getLabel() }}
                                                    </dt>
                                                    <dd class="text-sm text-gray-900 dark:text-white">
                                                        {!! $mobileCell($column, $record) !!}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @endif

                                    @if($hasActions && ! $collapseMobileActions)
                                        <div class="flex flex-wrap items-center gap-2 px-4 pb-3 {{ $isSelectable ? 'pl-12' : '' }}"
                                             data-testid="table-card-actions">
                                            @foreach($actions as $action)
                                                {!! $action->render($record, $actionClick) !!}
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Sub-rows: the same children, subtotal, "show more" and
                                         per-child actions the desktop panel renders. --}}
                                    @if($hasSubRows)
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
                                    @endif
                                </div>
                            @empty
                                <div class="px-4 py-12 text-center bg-white dark:bg-gray-800">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="rounded-full bg-gray-100 dark:bg-gray-700 p-3">
                                            {!! icon('outline:inbox', 'h-6 w-6', 'text-gray-400') !!}
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $table->getEmptyStateHeading() ?? __('wire-table::messages.empty_heading') }}
                                        </p>
                                    </div>
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
                            $hasPaginator = $records instanceof LengthAwarePaginator;
                            $hasMultiplePages = $hasPaginator && $records->hasPages();
                            $total = $hasPaginator ? $records->total() : $records->count();
                            $from = $hasPaginator ? ($records->firstItem() ?? 0) : ($records->count() > 0 ? 1 : 0);
                            $to = $hasPaginator ? ($records->lastItem() ?? 0) : $records->count();
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
                                            <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.records') }}</span>
                                </div>

                                {{-- Results Info - Always visible when paginated --}}
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('wire-table::messages.showing') }} <span
                                            class="font-medium text-gray-700 dark:text-gray-300">{{ $from }}</span> -
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $to }}</span> {{ __('wire-table::messages.of') }} <span
                                            class="font-medium text-gray-700 dark:text-gray-300">{{ $total }}</span>
                                    {{ __('wire-table::messages.records') }}
                                </div>

                                {{-- Pagination Links - Only when multiple pages --}}
                                @if($hasMultiplePages)
                                    <div>
                                        {{ $records->links('wire-table::tables.partials.pagination') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Action Modal --}}
                @include('wire-table::tables.partials.action-modal')

                {{-- Halt Modal --}}
                @include('wire-table::tables.partials.halt-modal')

                </div> {{-- Close table wrapper --}}

                {{-- Close polling wrapper --}}
                        @if($pollingAttribute)
            </div>
    @endif
@endif {{-- End lazy loading wrapper --}}
