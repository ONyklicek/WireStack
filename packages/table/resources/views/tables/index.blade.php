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

    $hasActions = $table->hasActions();
    $hasBulkActions = !empty($table->getBulkActions());
    $hasHeaderActions = !empty($table->getHeaderActions());
    $hasFilters = !empty($table->getFilters());
    $isSelectable = $table->isSelectable();
    $isPaginated = $table->isPaginated();
    $visibleColumns = array_filter($table->getColumns(), fn($c) => $c->canView() && $component->isColumnVisible($c->getName()));
    $hasVisibleColumns = count($visibleColumns) > 0;
    $filterableColumns = array_filter($table->getColumns(), fn($c) => $c->canView() && $c->isFilterable() && $component->isColumnVisible($c->getName()));
    $hasColumnFilters = count($filterableColumns) > 0;
    $hasSubRows = $table->hasSubRows();
    $isSubRowsExpandable = $hasSubRows && $table->isSubRowsExpandable();
    $subRowColumns = $hasSubRows ? $table->getSubRowColumns() : [];
    $visibleSubRowColumns = $hasSubRows ? array_filter($subRowColumns, fn($c) => $c->canView()) : [];
    $colSpan = ($isSelectable ? 1 : 0) + count($visibleColumns) + ($hasActions ? 1 : 0) + ($hasSubRows ? 1 : 0);
    $toggleableColumns = array_filter($table->getColumns(), fn($c) => $c->isToggleable() && $c->canView());
    $visibleToggleableCount = count(array_filter($toggleableColumns, fn($c) => $component->isColumnVisible($c->getName())));

    // Action configuration
    $actionsPosition = $table->getActionsPosition(); // 'start' or 'end'
    $actionsAlignment = $table->getActionsAlignment(); // 'left', 'center', 'right'
    $actionsColumnLabel = $table->getActionsColumnLabel() ?? 'Akce';
    $actionsColumnWidth = $table->getActionsColumnWidth();

    // Table styling
    $isCompact = $table->isCompact();
    $isBordered = $table->isBordered();
    $isStriped = $table->isStriped();
    $cellPadding = $isCompact ? 'px-4 py-2' : 'px-6 py-4';
    $headerPadding = $isCompact ? 'px-4 py-2' : 'px-6 py-3';

    // Responsive layout
    $isStackedOnMobile = $table->isStackedOnMobile();
    $stackedBreakpoint = $table->getStackedBreakpoint();

    // Tailwind requires full class names - cannot use dynamic interpolation
    $tableHiddenClass = '';
    $cardsVisibleClass = 'hidden';

    if ($isStackedOnMobile) {
        $tableHiddenClass = match($stackedBreakpoint) {
            'sm' => 'hidden sm:block',
            'md' => 'hidden md:block',
            'lg' => 'hidden lg:block',
            'xl' => 'hidden xl:block',
            default => 'hidden md:block',
        };
        $cardsVisibleClass = match($stackedBreakpoint) {
            'sm' => 'sm:hidden',
            'md' => 'md:hidden',
            'lg' => 'lg:hidden',
            'xl' => 'xl:hidden',
            default => 'md:hidden',
        };
    }

    // Check if search/filter is active but no results
    $hasActiveFilters = !empty($component->tableSearch) || !empty(array_filter($component->tableFilters ?? [])) || !empty(array_filter($component->columnFilters ?? []));
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

            <div class="w-full" wire:key="table-wrapper">
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
                                            <x-wire::icon name="outline:magnifying-glass" size="h-4 w-4" class="text-gray-400" />
                                        </div>
                                        <input
                                                type="search"
                                                wire:model.live.debounce.300ms="tableState.search"
                                                placeholder="{{ __('wire-table::messages.search') }}..."
                                                class="block w-full rounded-lg border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 pl-9 pr-3 py-2 text-sm placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:text-white dark:placeholder-gray-500"
                                        >
                                    </div>
                                @endif

                                {{-- Filters Toggle --}}
                                @if($hasFilters)
                                    <div x-data="{ open: false }" class="relative">
                                        <button
                                                @click="open = !open"
                                                type="button"
                                                x-ref="trigger"
                                                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                        >
                                            <x-wire::icon name="outline:funnel" size="h-4 w-4" />
                                            <span>{{ __('wire-table::messages.filters') }}</span>
                                            @if(!empty(array_filter($component->tableFilters ?? [])))
                                                <span
                                                        class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-primary-600 rounded-full">
                                        {{ count(array_filter($component->tableFilters)) }}
                                    </span>
                                            @endif
                                        </button>

                                        {{-- Filters Dropdown --}}
                                        <div
                                                x-show="open"
                                                @click.outside="open = false"
                                                x-transition
                                                x-anchor.bottom-start.offset.8="$refs.trigger"
                                                class="absolute left-0 z-50 mt-2 w-72 origin-top-left rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700"
                                                x-cloak
                                        >
                                            <div class="p-4 space-y-4">
                                                @foreach($table->getFilters() as $filter)
                                                    @if($filter->canView())
                                                        {!! $filter->render($component->tableFilters[$filter->getName()] ?? null) !!}
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
                                @endif
                            </div>

                            {{-- Right side: Polling, Header Actions, Column Toggle --}}
                            <div class="flex items-center gap-2">
                                {{-- Polling Indicator --}}
                                @include('wire-table::tables.partials.polling-indicator')

                                {{-- Sub-rows Toolbar --}}
                                @if($hasSubRows)
                                    @include('wire-table::tables.partials.sub-rows-toolbar', ['table' => $table, 'component' => $component])
                                @endif

                                {{-- Plugin Toolbar Widgets --}}
                                @if(method_exists($component, 'getTableToolbarWidgets'))
                                    @foreach($component->getTableToolbarWidgets() as $widget)
                                        {!! $widget !!}
                                    @endforeach
                                @endif

                                {{-- Header Actions --}}
                                @if($hasHeaderActions)
                                    @foreach($table->getHeaderActions() as $headerAction)
                                        @if($headerAction->canExecute())
                                            {!! $headerAction->render() !!}
                                        @endif
                                    @endforeach
                                @endif

                                {{-- Column Toggle --}}
                                @if(count($toggleableColumns) > 0)
                                    <div
                                            x-data="{
                                open: false,
                                position: 'right',
                                checkPosition() {
                                    const btn = this.$refs.toggleBtn;
                                    const rect = btn.getBoundingClientRect();
                                    const dropdownWidth = 224; // w-56 = 14rem = 224px

                                    // Check if dropdown would overflow right edge
                                    if (rect.right - dropdownWidth < 0) {
                                        this.position = 'left';
                                    } else if (rect.left + dropdownWidth > window.innerWidth) {
                                        this.position = 'right';
                                    } else {
                                        // Default based on position in viewport
                                        this.position = rect.left > window.innerWidth / 2 ? 'right' : 'left';
                                    }
                                }
                            }"
                                            @keydown.escape.window="open = false"
                                            class="relative"
                                    >
                                        <button
                                                x-ref="toggleBtn"
                                                @click="checkPosition(); open = !open"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                title="{{ __('wire-table::messages.toggle_columns') }}"
                                        >
                                            <x-wire::icon name="outline:view-columns" size="h-5 w-5" />
                                        </button>

                                        <div
                                                x-show="open"
                                                @click.outside="open = false"
                                                x-transition:enter="transition ease-out duration-100"
                                                x-transition:enter-start="transform opacity-0 scale-95"
                                                x-transition:enter-end="transform opacity-100 scale-100"
                                                x-transition:leave="transition ease-in duration-75"
                                                x-transition:leave-start="transform opacity-100 scale-100"
                                                x-transition:leave-end="transform opacity-0 scale-95"
                                                :class="position === 'right' ? 'right-0 origin-top-right' : 'left-0 origin-top-left'"
                                                class="absolute mt-2 w-56 rounded-xl bg-white dark:bg-gray-800 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700 z-50 max-h-80 overflow-y-auto"
                                                x-cloak
                                        >
                                            <div class="p-2">
                                                <div
                                                        class="px-3 py-2 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700 mb-1">
                                                    Sloupce
                                                </div>
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
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Active Filter Indicators --}}
                    @if($hasFilters)
                        @include('wire-table::tables.partials.filter-indicators', ['component' => $component])
                    @endif

                    {{-- Selection Bar --}}
                    @if($isSelectable && count($component->selectedRecords) > 0)
                        <div
                                class="px-4 lg:px-6 py-3 bg-primary-50 dark:bg-primary-900/20 border-b border-primary-100 dark:border-primary-800/30">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div
                                            class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-800/50">
                                <span
                                        class="text-sm font-semibold text-primary-700 dark:text-primary-300">{{ count($component->selectedRecords) }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
                            {{ trans_choice('{1} record selected|[2,*] records selected', count($component->selectedRecords)) }}
                        </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    {{-- Bulk Actions in selection bar --}}
                                    @if($hasBulkActions)
                                        @foreach($table->getBulkActions() as $bulkAction)
                                            @if($bulkAction->canExecute())
                                                {!! $bulkAction->render() !!}
                                            @endif
                                        @endforeach
                                    @endif

                                    {{-- Deselect button --}}
                                    <button
                                            type="button"
                                            wire:click="deselectAllRecords"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary-700 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200 hover:bg-primary-100 dark:hover:bg-primary-800/50 rounded-lg transition-colors"
                                    >
                                        <x-wire::icon name="outline:x-mark" size="w-4 h-4" />
                                        {{ __('wire-table::messages.deselect') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Table --}}
                    <div class="overflow-x-auto {{ $tableHiddenClass }}">
                        @if($hasVisibleColumns)
                            <table
                                    class="w-full {{ $isBordered ? 'border-collapse' : '' }} {{ $table->getTableClass() }}">
                                <thead
                                        class="bg-gray-50 dark:bg-gray-800/50 text-xs text-gray-500 dark:text-gray-400 uppercase {{ $table->getHeaderClass() }}">
                                <tr>
                                    {{-- Select All Checkbox --}}
                                    @if($isSelectable)
                                        <th scope="col" class="w-12 {{ $headerPadding }}">
                                            <div class="flex items-center justify-center">
                                                @php
                                                    $allSelected = $component->areAllVisibleSelected();
                                                    $someSelected = $component->areSomeVisibleSelected();
                                                @endphp
                                                <button
                                                        type="button"
                                                        wire:click="{{ $allSelected || $someSelected ? 'deselectAllRecords' : 'selectAllRecords' }}"
                                                        class="relative h-4 w-4 rounded border focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors
                                                {{ $allSelected ? 'bg-primary-600 border-primary-600' : ($someSelected ? 'bg-primary-600 border-primary-600' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600') }}"
                                                >
                                                    @if($allSelected)
                                                        <x-wire::icon name="check" size="h-4 w-4" class="absolute inset-0 text-white" />
                                                    @elseif($someSelected)
                                                        <x-wire::icon name="minus" size="h-4 w-4" class="absolute inset-0 text-white" />
                                                    @endif
                                                </button>
                                            </div>
                                        </th>
                                    @endif

                                    {{-- Sub-row Toggle Header --}}
                                    @if($hasSubRows)
                                        <th scope="col" class="w-10 {{ $headerPadding }}">
                                            {{ $table->getSubRowsToggleLabel() ?? '' }}
                                        </th>
                                    @endif

                                    {{-- Actions Header (Start Position) --}}
                                    @if($hasActions && $actionsPosition === 'start')
                                        <th
                                                scope="col"
                                                class="{{ $headerPadding }} font-semibold {{ $actionsAlignment === 'center' ? 'text-center' : ($actionsAlignment === 'right' ? 'text-right' : 'text-left') }}"
                                                @if($actionsColumnWidth) style="width: {{ $actionsColumnWidth }}" @endif
                                        >
                                            {{ $actionsColumnLabel }}
                                        </th>
                                    @endif

                                    {{-- Column Headers --}}
                                    @foreach($table->getColumns() as $column)
                                        @if($column->canView() && $component->isColumnVisible($column->getName()))
                                            <th
                                                    scope="col"
                                                    class="{{ $headerPadding }} text-{{ $column->getAlignment() }} font-semibold {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} {{ $column->getResponsiveClasses() }}"
                                                    @if($column->getWidth()) style="width: {{ $column->getWidth() }}" @endif
                                            >
                                                @if($column->isSortable() && $table->isSortable())
                                                    <button
                                                            type="button"
                                                            wire:click="sortTable('{{ $column->getName() }}')"
                                                            class="group inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200"
                                                    >
                                                        <span>{{ $column->getLabel() }}</span>
                                                        <span class="flex-none">
                                                    @if($component->tableSortColumn === $column->getName())
                                                                @if($component->tableSortDirection === 'asc')
                                                                    <x-wire::icon name="outline:chevron-up" size="h-4 w-4" class="text-gray-500 dark:text-gray-400" />
                                                                @else
                                                                    <x-wire::icon name="outline:chevron-down" size="h-4 w-4" class="text-gray-500 dark:text-gray-400" />
                                                                @endif
                                                            @else
                                                                <x-wire::icon name="outline:chevron-up-down" size="h-4 w-4" class="text-gray-500 dark:text-gray-400 opacity-0 group-hover:opacity-100" />
                                                            @endif
                                                </span>
                                                    </button>
                                                @else
                                                    {{ $column->getLabel() }}
                                                @endif
                                            </th>
                                        @endif
                                    @endforeach

                                    {{-- Actions Header (End Position - Default) --}}
                                    @if($hasActions && $actionsPosition === 'end')
                                        <th
                                                scope="col"
                                                class="{{ $headerPadding }} font-semibold {{ $actionsAlignment === 'center' ? 'text-center' : ($actionsAlignment === 'right' ? 'text-right' : 'text-left') }}"
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

                                        @foreach($table->getColumns() as $column)
                                            @if($column->canView() && $component->isColumnVisible($column->getName()))
                                                <th class="{{ $headerPadding }}">
                                                    @if($column->isFilterable())
                                                        {!! $column->renderFilter($component->columnFilters[$column->getName()] ?? null) !!}
                                                    @endif
                                                </th>
                                            @endif
                                        @endforeach

                                        {{-- Actions Filter Cell (End Position) --}}
                                        @if($hasActions && $actionsPosition === 'end')
                                            <th class="{{ $headerPadding }} text-right">
                                                @if(!empty(array_filter($component->columnFilters ?? [])))
                                                    <button
                                                            type="button"
                                                            wire:click="resetColumnFilters"
                                                            class="inline-flex items-center justify-center p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                                                            title="{{ __('wire-table::messages.filter_reset_column') }}"
                                                    >
                                                        <x-wire::icon name="outline:x-mark" size="w-4 h-4" />
                                                    </button>
                                                @endif
                                            </th>
                                        @endif
                                    </tr>
                                @endif
                                </thead>

                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse($records as $record)
                                    @php
                                        $recordKey = $record->{$table->getPrimaryKey()};
                                        $recordUrl = $table->getRecordUrl($record);
                                        $isSelected = $component->isRecordSelected($recordKey);
                                        $rowIndex = $loop->index;
                                    @endphp
                                    <tr
                                            class="{{ $table->isHoverable() ? 'hover:bg-gray-50 dark:hover:bg-gray-700/30' : '' }} {{ $isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : '' }} {{ $isStriped && $rowIndex % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/30' : '' }} {{ $table->getRowClass() }}"
                                            wire:key="row-{{ $recordKey }}"
                                    >
                                        {{-- Selection Checkbox --}}
                                        @if($isSelectable)
                                            <td class="w-12 {{ $cellPadding }}">
                                                <div class="flex items-center justify-center">
                                                    <button
                                                            type="button"
                                                            wire:click="toggleRecordSelection('{{ $recordKey }}')"
                                                            class="relative h-4 w-4 rounded border focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors
                                                    {{ $isSelected ? 'bg-primary-600 border-primary-600' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 hover:border-gray-400' }}"
                                                    >
                                                        @if($isSelected)
                                                            <x-wire::icon name="check" size="h-4 w-4" class="absolute inset-0 text-white" />
                                                        @endif
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
                                                        class="flex items-center gap-1 {{ $actionsAlignment === 'center' ? 'justify-center' : ($actionsAlignment === 'right' ? 'justify-end' : 'justify-start') }}">
                                                    @foreach($table->getActions() as $action)
                                                        {!! $action->render($record) !!}
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endif

                                        {{-- Column Cells --}}
                                        @foreach($table->getColumns() as $column)
                                            @if($column->canView() && $component->isColumnVisible($column->getName()))
                                                <td class="{{ $cellPadding }} {{ $column->shouldWrap() ? '' : 'whitespace-nowrap' }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} text-{{ $column->getAlignment() }} dark:text-white {{ $column->getResponsiveClasses() }}">
                                                    @if($recordUrl && !$column->isEditable())
                                                        <a href="{{ $recordUrl }}"
                                                           class="hover:text-primary-600 dark:hover:text-primary-400">
                                                            {!! $column->hasResponsiveDisplay() ? $column->renderResponsiveCell($record) : $column->renderCell($record) !!}
                                                        </a>
                                                    @else
                                                        {!! $column->hasResponsiveDisplay() ? $column->renderResponsiveCell($record) : $column->renderCell($record) !!}
                                                    @endif
                                                </td>
                                            @endif
                                        @endforeach

                                        {{-- Actions Cell (End Position - Default) --}}
                                        @if($hasActions && $actionsPosition === 'end')
                                            <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}">
                                                <div
                                                        class="flex items-center gap-1 {{ $actionsAlignment === 'center' ? 'justify-center' : ($actionsAlignment === 'right' ? 'justify-end' : 'justify-start') }}">
                                                    @foreach($table->getActions() as $action)
                                                        {!! $action->render($record) !!}
                                                    @endforeach
                                                </div>
                                            </td>
                                        @endif
                                    </tr>

                                    {{-- Sub-rows --}}
                                    @if($hasSubRows && ($component->isRowExpanded($recordKey) || $component->flattenMode))
                                        @php
                                            $subRows = $component->getSubRows($record);
                                        @endphp
                                        @include('wire-table::tables.partials.sub-rows', [
                                            'table' => $table,
                                            'component' => $component,
                                            'record' => $record,
                                            'recordKey' => $recordKey,
                                            'subRows' => $subRows,
                                            'colSpan' => $colSpan,
                                            'cellPadding' => $cellPadding,
                                            'isBordered' => $isBordered,
                                        ])
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ $colSpan }}" class="px-6 py-16 text-center">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="rounded-full bg-gray-100 dark:bg-gray-700 p-3">
                                                    @if($isEmptyDueToFilter)
                                                        {{-- Search/Filter empty icon --}}
                                                        <x-wire::icon name="outline:magnifying-glass" size="h-8 w-8" class="text-gray-400" />
                                                    @else
                                                        {{-- Regular empty icon --}}
                                                        <x-wire::icon name="outline:inbox" size="h-8 w-8" class="text-gray-400" />
                                                    @endif
                                                </div>
                                                <div>
                                                    <h3 class="text-base font-medium text-gray-900 dark:text-white">
                                                        @if($isEmptyDueToFilter)
                                                            {{ __('wire-table::messages.empty_filter_heading') }}
                                                        @else
                                                            {{ $table->getEmptyStateHeading() }}
                                                        @endif
                                                    </h3>
                                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                        @if($isEmptyDueToFilter)
                                                            {{ __('wire-table::messages.empty_no_records_match') }}
                                                        @else
                                                            {{ $table->getEmptyStateDescription() }}
                                                        @endif
                                                    </p>
                                                </div>
                                                @if($isEmptyDueToFilter)
                                                    <button
                                                            type="button"
                                                            wire:click="resetTableFilters"
                                                            class="mt-2 inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                                                    >
                                                        <x-wire::icon name="outline:arrow-path" size="w-4 h-4" />
                                                        {{ __('wire-table::messages.filter_reset') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>

                                {{-- Summary footer --}}
                                @if($component->tableHasSummaries())
                                    @php $summaryScope = $component->getSummaryScope(); @endphp
                                    @include('wire-table::tables.partials.summary-footer', [
                                        'table' => $table,
                                        'component' => $component,
                                        'summaries' => $component->computeTableSummaries($summaryScope),
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
                        @else
                            {{-- No columns visible state --}}
                            <div class="px-6 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="rounded-full bg-amber-100 dark:bg-amber-900/30 p-3">
                                        <x-wire::icon name="outline:eye-slash" size="h-8 w-8" class="text-amber-500 dark:text-amber-400" />
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
                            @php
                                $mobileColumns = array_values($visibleColumns);
                                $firstColumn = $mobileColumns[0] ?? null;
                                $restColumns = array_slice($mobileColumns, 1);
                            @endphp
                            @forelse($records as $record)
                                @php
                                    $recordKey = $record->{$table->getPrimaryKey()};
                                    $isSelected = in_array((string) $recordKey, $component->selectedRecords);
                                    $recordUrl = $table->getRecordUrl($record);
                                @endphp
                                <div
                                        class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 {{ $isSelected ? 'ring-2 ring-primary-500 ring-inset bg-primary-50/50 dark:bg-primary-900/30' : '' }}">
                                    {{-- Card Header: First column as title + Actions --}}
                                    <div class="flex items-start gap-3 p-4 {{ count($restColumns) > 0 ? 'pb-4' : '' }}">
                                        @if($isSelectable)
                                            <label class="flex items-center pt-0.5 flex-shrink-0">
                                                <input
                                                        type="checkbox"
                                                        wire:model.live="tableState.selection.records"
                                                        value="{{ $recordKey }}"
                                                        class="h-5 w-5 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 dark:focus:ring-offset-gray-800 touch-manipulation"
                                                >
                                                <span class="sr-only">Vybrat</span>
                                            </label>
                                        @endif

                                        <div class="flex-1 min-w-0">
                                            @if($firstColumn)
                                                @php
                                                    $firstContent = $firstColumn->hasResponsiveDisplay()
                                                        ? $firstColumn->renderMobileCell($record)
                                                        : $firstColumn->renderCell($record);
                                                @endphp
                                                @if($recordUrl)
                                                    <a href="{{ $recordUrl }}"
                                                       class="block hover:text-primary-600 dark:hover:text-primary-400">
                                                        <div
                                                                class="font-medium text-gray-900 dark:text-white truncate text-base">
                                                            {!! $firstContent !!}
                                                        </div>
                                                    </a>
                                                @else
                                                    <div
                                                            class="font-medium text-gray-900 dark:text-white truncate text-base">
                                                        {!! $firstContent !!}
                                                    </div>
                                                @endif
                                            @endif
                                        </div>

                                        @if($hasActions)
                                            <div class="flex items-center gap-1 flex-shrink-0 -mr-1">
                                                @foreach($table->getActions() as $action)
                                                    {!! $action->render($record) !!}
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Card Body: Rest of the columns in 2-column grid --}}
                                    @if(count($restColumns) > 0)
                                        <div class="px-4 pb-4 {{ $isSelectable ? 'pl-12' : '' }}">
                                            <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                                                @php $restCount = count($restColumns); @endphp
                                                @foreach($restColumns as $index => $column)
                                                    @php
                                                        $colContent = $column->hasResponsiveDisplay()
                                                            ? $column->renderMobileCell($record)
                                                            : $column->renderCell($record);
                                                        $isLastOdd = ($index === $restCount - 1) && ($restCount % 2 === 1);
                                                    @endphp
                                                    <div class="{{ $isLastOdd ? 'col-span-2' : 'col-span-1' }}">
                                                        <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">
                                                            {{ $column->getLabel() }}
                                                        </dt>
                                                        <dd class="text-sm text-gray-900 dark:text-white">
                                                            {!! $colContent !!}
                                                        </dd>
                                                    </div>
                                                @endforeach
                                            </dl>
                                        </div>
                                    @endif

                                    {{-- Sub-rows (Mobile) --}}
                                    @if($hasSubRows && ($component->isRowExpanded($recordKey) || $component->flattenMode))
                                        @php $subRows = $component->getSubRows($record); @endphp
                                        @if($subRows->isNotEmpty())
                                            <div class="border-t border-gray-100 dark:border-gray-700/50 bg-gray-50/80 dark:bg-gray-800/50">
                                                {{-- Toggle header --}}
                                                @if($isSubRowsExpandable)
                                                    <button
                                                        type="button"
                                                        wire:click="toggleRowExpansion('{{ $recordKey }}')"
                                                        class="w-full flex items-center gap-2 px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400"
                                                    >
                                                        <x-wire::icon name="outline:chevron-right" size="w-3 h-3" class="rotate-90" />
                                                        {{ $table->getSubRowsToggleLabel() ?? __('wire-table::messages.details') }}
                                                    </button>
                                                @endif

                                                <dl class="px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-2 {{ $isSelectable ? 'pl-12' : '' }}">
                                                    @foreach($subRows as $subRow)
                                                        @foreach($visibleSubRowColumns as $subCol)
                                                            <div class="col-span-1">
                                                                <dt class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-0.5">
                                                                    {{ $subCol->getLabel() }}
                                                                </dt>
                                                                <dd class="text-sm text-gray-700 dark:text-gray-300">
                                                                    {!! $subCol->renderCell($subRow) !!}
                                                                </dd>
                                                            </div>
                                                        @endforeach
                                                    @endforeach
                                                </dl>
                                            </div>
                                        @endif
                                    @elseif($hasSubRows && $isSubRowsExpandable)
                                        <div class="border-t border-gray-100 dark:border-gray-700/50">
                                            <button
                                                type="button"
                                                wire:click="toggleRowExpansion('{{ $recordKey }}')"
                                                class="w-full flex items-center gap-2 px-4 py-2 text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
                                            >
                                                <x-wire::icon name="outline:chevron-right" size="w-3 h-3" />
                                                {{ $table->getSubRowsToggleLabel() ?? __('wire-table::messages.details') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="px-4 py-12 text-center bg-white dark:bg-gray-800">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="rounded-full bg-gray-100 dark:bg-gray-700 p-3">
                                            <x-wire::icon name="outline:inbox" size="h-6 w-6" class="text-gray-400" />
                                        </div>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $table->getEmptyStateHeading() ?? __('wire-table::messages.empty_heading') }}
                                        </p>
                                    </div>
                                </div>
                            @endforelse
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
                                            class="rounded-lg border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm text-gray-700 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1.5"
                                    >
                                        @foreach($table->getPerPageOptions() as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
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
