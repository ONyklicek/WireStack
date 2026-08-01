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

    // live(broadcast: true): re-read as soon as somebody else commits, instead
    // of on the next tick. Null without the opt-in, so a table that did not ask
    // for it ships no listener and needs no channel authorization.
    $liveChannel = $component->getTableLiveChannel();

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
    // The stacked cards have their own action list: a finger has no double-click,
    // no right-click and no Delete key, so a behaviour-only record action also
    // renders here as an ordinary button (recordActionButtonsOnMobile()).
    $mobileActions = $table->getMobileRowActionsForDisplay();
    $hasMobileActions = $mobileActions !== [];
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
    // The controller mount has its own owner: grid semantics (role/tabindex)
    // now cover selectable tables too, but mounting wireRecordActions there is
    // a visible change that ships separately — see mountsRecordActionController().
    $recordActionsRootEnabled = $table->mountsRecordActionController();
    // The mouse half of the gesture layer (sweep, Shift/mod ranges) — switchable
    // independently of the keyboard one, so the controller gets its own config.
    $gestureConfig = $table->getGestureConfig();
    $usesRangeSelection = $table->usesRangeSelection();
    // The marker only exists where something continues from the marked row: the
    // keyboard, a range or a sweep. A table left with a bare click binding runs
    // the action and highlights nothing.
    $activeRowConfig = $table->usesActiveRowMarker() ? $table->getActiveRowConfig() : null;
    // `?` opens the shortcut help. The event name is derived from the component
    // id, so a page with several tables opens only the one whose row has focus —
    // a bare window event would open every help modal at once. It goes through
    // a lowercase hash, because the listener lives in an ATTRIBUTE NAME
    // (x-on:{event}.window) and the DOM lowercases those: a mixed-case Livewire
    // id would never match what the controller dispatches. The controller learns
    // the name through its keyboard config; a table whose legend is empty gets
    // no event and no modal at all.
    $shortcutLegend = $table->usesShortcutHelp() ? $table->shortcutLegend() : null;
    $shortcutHelpEvent = $shortcutLegend !== null && ! $shortcutLegend->isEmpty()
        ? 'wire-table-shortcut-help-'.substr(md5($component->getId()), 0, 12)
        : null;

    if ($recordKeyboardConfig !== null) {
        $recordKeyboardConfig['help'] = $shortcutHelpEvent;
    }
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

    // One `:class` expression per row, merging every dynamic row state that has
    // to survive a Livewire morph: the selection tint and the record-action
    // active marker. Both are Alpine bindings rather than classes toggled from
    // JS, so the roundtrip a click triggers cannot wash them off. `rowClass()`
    // returns an object (it also switches the row's hover tint off while it is
    // the active row); `%key%` is substituted with the record key per row.
    $rowClassBindingParts = [];
    if ($isSelectable) {
        $rowClassBindingParts[] = "'bg-primary-50 dark:bg-primary-900/20': isSelected(%key%)";
    }
    if ($activeRowConfig !== null) {
        $rowClassBindingParts[] = '...rowClass(%key%)';
    }
    $rowClassBinding = $rowClassBindingParts === []
        ? null
        : '{ '.implode(', ', $rowClassBindingParts).' }';

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
    $hasPaginator = $records instanceof LengthAwarePaginator;
    $recordCount = $hasPaginator ? $records->total() : $records->count();
    $isEmptyDueToFilter = $hasActiveFilters && $recordCount === 0;

    // Where this page sits in the whole result set. Read by the footer's
    // "from - to of total" line and, before it, by aria-rowindex: an ARIA row
    // index counts through the entire grid, not the page, so row 1 of page 2
    // is not index 1. Hence the lift out of the footer.
    $rangeFrom = $hasPaginator ? ($records->firstItem() ?? 0) : ($records->count() > 0 ? 1 : 0);
    $rangeTo = $hasPaginator ? ($records->lastItem() ?? 0) : $records->count();

    // Header rows come first in the ARIA row numbering, and the column-filter
    // row is one of them when present — miss it and every body index is off by
    // one.
    $headerRowCount = 1 + ($hasColumnFilters ? 1 : 0);

    // Whole sentences for the selection live region: only the server can
    // translate them, and the counts are substituted client-side because the
    // selection itself lives in Alpine.
    $selectionAnnouncements = [
        'some' => __('wire-table::messages.selection_announce_some', ['count' => ':count', 'total' => ':total']),
        'all' => __('wire-table::messages.selection_announce_all', ['total' => ':total']),
        'none' => __('wire-table::messages.selection_announce_none'),
    ];
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

                                        // Right-click context menu: only render one for rows that
                                        // actually have a visible action.
                                        $rowContextMenuHtml = $rowContextMenuEnabled
                                            ? trim($table->getRowContextMenuHtml($record)->toHtml())
                                            : '';
                                        $hasRowContextMenu = $rowContextMenuHtml !== '';

                                        // Per-record sub-rows (subRowsVisible): decides this row's
                                        // chevron and panel only — the expander cell itself still
                                        // renders, empty, or the columns stop lining up.
                                        $recordHasSubRows = $hasSubRows && $table->hasSubRowsFor($record);
                                    @endphp

                                    {{-- Group header --}}
                                    @if($isGroupStart)
                                        @include('wire-table::tables.partials.group-header', [
                                            'label' => $table->resolveGroupLabel($record),
                                            'colSpan' => $colSpan,
                                            'cellPadding' => $cellPadding,
                                        ])
                                    @endif
                                    @php $recordKeyJs = \Illuminate\Support\Js::from((string) $recordKey)->toHtml(); @endphp
                                    <tr
                                            class="{{ $table->getRowClasses($record, $rowIndex) }} {{ $keyboardNav ? 'focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500' : '' }}"
                                            @if($rowClassBinding) :class="{!! str_replace('%key%', $recordKeyJs, $rowClassBinding) !!}" @endif
                                            {{-- The roving tabindex is bound, not printed: Livewire morphs the
                                                 rows back to this markup on every update, which would wipe an
                                                 assigned tabstop and drop the grid out of the tab order. --}}
                                            @if($keyboardNav) role="row" tabindex="{{ $rowIndex === 0 ? '0' : '-1' }}" :tabindex="rowTabindex({!! $recordKeyJs !!}, {{ $rowIndex }})" @endif
                                            {{-- Position in the whole grid, so it survives paging:
                                                 the header rows come first, then this page's offset. --}}
                                            @if($tableRole) aria-rowindex="{{ $headerRowCount + $rangeFrom + $rowIndex }}" @endif
                                            {{-- Bound, never printed: the selection lives in Alpine and
                                                 a static value would snap back to the server's truth on
                                                 the next morph, leaving the row lying about itself. --}}
                                            @if($isSelectable) :aria-selected="isSelected({!! $recordKeyJs !!}) ? 'true' : 'false'" @endif
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
                                        {{-- Selection Checkbox. data-select-cell marks the selection
                                             column for the sweep gesture (and the widened click
                                             target): the cell is found by this hook, never by column
                                             position — sortable prepends a drag-handle <td> and would
                                             shift every index. --}}
                                        @if($isSelectable)
                                            {{-- The whole cell toggles, not just the 16px box, which is
                                                 under every touch-target guideline while the rest of the
                                                 cell sits dead. While ranges are on, a modified click is
                                                 left alone: Shift and mod mean range and add-to-selection,
                                                 and the row controller answers those for the whole row,
                                                 cell included. With ranges off nobody else would answer
                                                 them, so the cell takes every click and toggles. --}}
                                            <td class="w-12 {{ $cellPadding }} cursor-pointer"
                                                data-select-cell
                                                x-on:click="{{ $usesRangeSelection ? '$event.shiftKey || $event.ctrlKey || $event.metaKey || ' : '' }}toggle(@js((string) $recordKey))">
                                                <div class="flex items-center justify-center">
                                                    {{-- No handler of its own: a click (or Enter/Space on
                                                         the focused box) bubbles to the cell, which owns
                                                         the toggle. Two handlers would toggle twice. --}}
                                                    <button
                                                            type="button"
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
                                                @if($isSubRowsExpandable && $recordHasSubRows)
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
                                            <label class="flex items-center pt-0.5 flex-shrink-0" data-select-cell>
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
                                        @if($hasMobileActions && $collapseMobileActions)
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

                                    @if($hasMobileActions && ! $collapseMobileActions)
                                        <div class="flex flex-wrap items-center gap-2 px-4 pb-3 {{ $isSelectable ? 'pl-12' : '' }}"
                                             data-testid="table-card-actions">
                                            @foreach($mobileActions as $action)
                                                {!! $action->render($record, $actionClick) !!}
                                            @endforeach
                                        </div>
                                    @endif

                                    {{-- Sub-rows: the same children, subtotal, "show more" and
                                         per-child actions the desktop panel renders. Guarded here
                                         rather than inside the partial — the card's own toggle
                                         button lives there, in two branches. --}}
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
                                    @endif
                                </div>
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
                            // $hasPaginator and the range come from the preamble — aria-rowindex
                            // needs them before the body renders.
                            $hasMultiplePages = $hasPaginator && $records->hasPages();
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

                {{-- Keyboard shortcut help (`?`) --}}
                @include('wire-table::tables.partials.shortcut-help-modal')

                </div> {{-- Close table wrapper --}}

                {{-- Close polling wrapper --}}
                        @if($pollingAttribute)
            </div>
    @endif
@endif {{-- End lazy loading wrapper --}}
