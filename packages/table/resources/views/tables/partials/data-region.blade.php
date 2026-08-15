{{--
    The data region: the desktop table, the stacked mobile cards, and the
    pagination footer under both.

    An ISLAND, which is why it has a scope of its own instead of inheriting the
    wrapper's. Livewire extracts an `@island` body into its own view file at
    compile time and renders it with the component plus its public properties —
    `HandlesIslands::renderIslandView()` — and nothing else. Not one of the
    wrapper's locals reaches here, so the region asks the same plan the wrapper
    asks and reads its own half.

    What that buys: an action fired from anything inside these markers targets the
    island automatically (`closestIsland()` in Livewire's JS — no attribute
    needed), so a sort, a page, a cell save, a sub-row expansion or a row action
    makes the server render THIS ALONE and skip the toolbar, the filter panels and
    the modals. The boundary is drawn where it is for that reason: everything a
    row change can alter is inside it, including the "showing 1 - 10 of 240" line
    and the page links, which move when an edit drops a row out of the filter.

    Rendered through `@island(always: true)`, so a request that does NOT target it
    still renders it — a search, a filter, a column toggle, a poll tick. Without
    `always` those would leave the rows behind stale.
--}}
@php
    use NyonCode\WireTable\Table;

    /** @var mixed $__livewire */
    $component = $__livewire;
    $table = $component->getTable();
    $records = $component->getTableRecords();
    $plan = $component->tableRenderPlan();

    assert($table instanceof Table);

    // Sub-rows and grouping — the regions inside the body that are their own
    // shape of row.
    $hasSubRows = $plan->shell()->hasSubRows;
    $isSubRowsExpandable = $plan->shell()->isSubRowsExpandable;
    $allRowsExpanded = $plan->shell()->allRowsExpanded;
    $hasGrouping = $plan->shell()->hasGrouping;
    $hasGroupSummaries = $plan->shell()->hasGroupSummaries;

    // What the header row's own filter inputs and sort indicators show.
    $columnFilterValues = $plan->state()->columnFilters;
    $activeColumnFilters = $plan->state()->activeColumnFilters;
    $sortColumn = $plan->state()->sortColumn;
    $sortDirection = $plan->state()->sortDirection;
    $perPage = $plan->state()->perPage;

    // Row and mobile-card actions, and the resolver that keeps wire-core's action
    // views host-agnostic.
    $actions = $plan->actions()->row;
    $hasActions = $plan->actions()->hasAny;
    $mobileActions = $plan->actions()->mobile;
    $hasMobileActions = $plan->actions()->hasMobile;
    $collapseMobileActions = $plan->actions()->collapseMobile;
    $mobileActionGroup = $plan->actions()->mobileGroup;
    $actionClick = $plan->actions()->click;
    $actionsPosition = $plan->actions()->position; // 'start' or 'end'
    $actionsAlignmentClass = $plan->actions()->alignmentClass; // literal text-* utility
    $actionsJustifyClass = $plan->actions()->justifyClass; // literal justify-* utility
    $actionsColumnLabel = $plan->actions()->columnLabel;
    $actionsColumnWidth = $plan->actions()->columnWidth;

    // Row interaction — the pointer bindings, the two independently switchable
    // halves of the gesture layer, and the active-row marker.
    $rowContextMenuEnabled = $plan->interaction()->rowContextMenuEnabled;
    $recordActionBindings = $plan->interaction()->recordActionBindings;
    $keyboardNav = $plan->interaction()->keyboardNav;
    $tableRole = $plan->interaction()->tableRole;
    $recordKeyboardConfig = $plan->interaction()->recordKeyboardConfig;
    $recordActionsRootEnabled = $plan->interaction()->recordActionsRootEnabled;
    $gestureConfig = $plan->interaction()->gestureConfig;
    $activeRowConfig = $plan->interaction()->activeRowConfig;

    // The row itself: the compiled <tr>, the checkbox cell and the per-row class
    // binding. Compiled once per TABLE — see RowRenderPlan.
    $rowSkeleton = $plan->row()->rowSkeleton;
    $selectionCellSkeleton = $plan->row()->selectionCellSkeleton;
    $rowClassBinding = $plan->row()->rowClassBinding;
    $isSelectable = $plan->row()->isSelectable;
    $selectCheckIcon = $plan->row()->selectCheckIcon;
    $hasSummaries = $plan->row()->hasSummaries;
    $pageRecordKeys = $plan->row()->pageRecordKeys;

    // Columns, and everything derived from them — the visible set, the per-column
    // render metadata (including each column's compiled <td> skeleton), and the
    // lists the fill handle and the column filters read.
    $visibleColumns = $plan->columns()->visible;
    $hasVisibleColumns = $plan->columns()->hasVisible;
    $columnMeta = $plan->columns()->meta;
    $fillColumns = $plan->columns()->fillable;
    $isFillEnabled = $plan->columns()->isFillEnabled;
    $filterableColumns = $plan->columns()->filterable;
    $hasColumnFilters = $plan->columns()->hasFilters;
    $subRowColumns = $plan->columns()->subRow;
    $visibleSubRowColumns = $plan->columns()->visibleSubRow;
    $colSpan = $plan->columns()->colSpan;

    // Table styling. Row hover/striping/tint is composed in
    // Table::getRowClasses($record, $rowIndex), not here.
    $isBordered = $plan->layout()->isBordered;
    $cellPadding = $plan->layout()->cellPadding;
    $headerPadding = $plan->layout()->headerPadding;
    $isStackedOnMobile = $plan->layout()->isStackedOnMobile;
    $tableHiddenClass = $plan->layout()->tableHiddenClass;
    $cardsVisibleClass = $plan->layout()->cardsVisibleClass;

    // Where this page sits in the whole result set — read by the footer's
    // "from - to of total" line and, before it, by aria-rowindex, since an ARIA
    // row index counts through the entire grid rather than the page.
    $isPaginated = $plan->paging()->isPaginated;
    $hasPaginator = $plan->paging()->hasPaginator;
    $recordCount = $plan->paging()->recordCount;
    $isEmptyDueToFilter = $plan->paging()->isEmptyDueToFilter;
    $rangeFrom = $plan->paging()->rangeFrom;
    $rangeTo = $plan->paging()->rangeTo;
    $headerRowCount = $plan->paging()->headerRowCount;
@endphp
                    <div class="relative overflow-x-auto {{ $tableHiddenClass }}"
                         @if($isFillEnabled)
                             {{-- Deliberately NOT island-targeted, unlike the editable
                                  cells inside it: the fill suppresses morphs while a drag
                                  is in flight through Livewire's `morph.updating` hook, and
                                  an island fragment morph does not go through it. Targeted,
                                  a fill's own response wipes the cells it just painted —
                                  verify-spa-navigate.mjs is what says so. It needs no render
                                  anyway; it reconciles each cell from the response payload. --}}
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