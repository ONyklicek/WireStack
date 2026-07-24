{{-- Sub-rows for a parent record --}}
{{-- Variables: $table, $component, $record, $recordKey, $subRows, $colSpan, $cellPadding, $isBordered --}}
@php
    $customSubRowView = $table->getSubRowView();
@endphp

{{-- Custom child renderer: hand full control to a user-supplied view --}}
@if($customSubRowView)
    <tr wire:key="sub-rows-{{ $recordKey }}">
        <td colspan="{{ $colSpan }}" class="p-0">
            <div class="bg-gray-50/80 dark:bg-gray-800/50 border-t border-b border-gray-100 dark:border-gray-700/50">
                @include($customSubRowView, [
                    'table' => $table,
                    'component' => $component,
                    'record' => $record,
                    'recordKey' => $recordKey,
                    'subRows' => $subRows,
                ])
            </div>
        </td>
    </tr>
@else
@php
    $subColumns = $table->getSubRowColumns();
    // canView() may hit the Gate — resolve visibility once per parent, not per cell.
    $visibleSubRowColumns ??= array_filter($subColumns, fn ($c) => $c->canView());
    $subRowActions = $table->getSubRowActions();
    $hasSubRowActions = $table->hasSubRowActions();
    $isFilterable = $table->isSubRowsFilterable();
    $isSortable = $table->isSubRowsSortable();
    $activeSort = $component->getSubRowSort();
    $subRowFilterValues = $component->tableState->get('rows.subRowFilters', []) ?? [];
    // The slots are seeded (null / []) so select-type controls can entangle their
    // path, so "not empty" no longer means "a filter is active" — a real value is
    // a non-empty scalar or a non-empty array.
    $hasActiveSubRowFilter = collect($subRowFilterValues)->contains(
        fn ($v) => is_array($v) ? $v !== [] : ($v !== null && $v !== ''),
    );
    $subRowSummaries = $component->computeTableSummaries('subRows', $record, $subRows);
    $hasSubSummaries = !empty($subRowSummaries);

    // "Show more" affordance: only when a limit is configured and more exist.
    $subRowsLimit = $table->getSubRowsLimit();
    $showAll = $component->isSubRowsShowAll($recordKey);
    $totalSubRows = ($subRowsLimit && !$showAll) ? $component->getSubRowsTotalCount($record) : $subRows->count();
    $remaining = max(0, $totalSubRows - $subRows->count());

    // Visible column count for colspans (+1 indent spacer, +1 optional actions cell).
    $totalColCount = count($visibleSubRowColumns) + 1 + ($hasSubRowActions ? 1 : 0);
@endphp

<tr wire:key="sub-rows-{{ $recordKey }}">
    <td colspan="{{ $colSpan }}" class="p-0">
        <div class="bg-gray-50/80 dark:bg-gray-800/50 border-t border-b border-gray-100 dark:border-gray-700/50">
            {{-- Sub-row filters --}}
            @if($isFilterable && count($subColumns) > 0)
                <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-100 dark:border-gray-700/50">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-1">{{ __('wire-table::messages.filter_label') }}</span>
                    @foreach($subColumns as $subCol)
                        @if($subCol->isFilterable())
                            <div class="w-40">
                                {{-- Bind to the sub-row filter slot, not the parent
                                     table's column filters — otherwise the input
                                     silently filters the parent (or, on a name
                                     collision, filters nothing at all). --}}
                                {!! $subCol->renderFilter(
                                    $subRowFilterValues[$subCol->getName()] ?? null,
                                    'tableState.rows.subRowFilters.'.$subCol->getName(),
                                ) !!}
                            </div>
                        @endif
                    @endforeach
                    @if($hasActiveSubRowFilter)
                        <button type="button" wire:click="resetSubRowFilters" data-testid="subrows-reset-filters" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            ✕ {{ __('wire-table::messages.reset') }}
                        </button>
                    @endif
                </div>
            @endif

            {{-- Sub-rows table --}}
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        {{-- Indent spacer --}}
                        <th class="w-8"></th>
                        @foreach($visibleSubRowColumns as $subCol)
                            @php $colSortable = $isSortable && $table->isSubRowColumnSortable($subCol->getName()); @endphp
                            <th class="px-3 py-2 font-medium">
                                    @if($colSortable)
                                        @php $isActive = $activeSort && $activeSort['column'] === $subCol->getName(); @endphp
                                        <button type="button"
                                                wire:click="sortSubRows('{{ $subCol->getName() }}')" data-testid="subrows-sort-{{ $subCol->getName() }}"
                                                class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 {{ $isActive ? 'text-gray-700 dark:text-gray-200' : '' }}">
                                            <span>{{ $subCol->getLabel() }}</span>
                                            @if($isActive)
                                                @if($activeSort['direction'] === 'asc')
                                                    {!! icon('outline:chevron-up', 'w-4 h-4', 'text-gray-500 dark:text-gray-400') !!}
                                                @else
                                                    {!! icon('outline:chevron-down', 'w-4 h-4', 'text-gray-500 dark:text-gray-400') !!}
                                                @endif
                                            @else
                                                <span class="text-[10px] opacity-30">
                                                    {!! icon('outline:chevron-up-down', 'w-4 h-4', 'text-gray-500 dark:text-gray-400 opacity-0 hover:opacity-100') !!}
                                                </span>
                                            @endif
                                        </button>
                                    @else
                                        {{ $subCol->getLabel() }}
                                    @endif
                                </th>
                        @endforeach
                        @if($hasSubRowActions)
                            <th class="px-3 py-2 font-medium text-right">{{ __('wire-table::messages.actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($subRows as $subRow)
                        <tr class="hover:bg-gray-100/50 dark:hover:bg-gray-700/20" wire:key="sub-row-{{ $recordKey }}-{{ $subRow->getKey() }}">
                            <td class="w-8"></td>
                            @foreach($visibleSubRowColumns as $subCol)
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300 {{ $subCol->shouldWrap() ? '' : 'whitespace-nowrap' }}">
                                    {!! $subCol->renderCellFast($subRow) !!}
                                </td>
                            @endforeach
                            @if($hasSubRowActions)
                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @foreach($subRowActions as $action)
                                            {!! $action->render($subRow) !!}
                                        @endforeach
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $totalColCount }}" class="px-3 py-4 text-center text-xs text-gray-400 dark:text-gray-500 italic">
                                {{ __('wire-table::messages.no_sub_rows') }}
                            </td>
                        </tr>
                    @endforelse

                    {{-- Show more --}}
                    @if($remaining > 0)
                        <tr wire:key="sub-rows-more-{{ $recordKey }}">
                            <td colspan="{{ $totalColCount }}" class="px-3 py-2 text-center">
                                <button type="button"
                                        wire:click="showAllSubRows('{{ $recordKey }}')" data-testid="subrows-show-more"
                                        class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                                    {{ __('wire-table::messages.show_more_count', ['count' => $remaining]) }}
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>

                {{-- Sub-row summaries --}}
                @if($hasSubSummaries && $subRows->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 dark:border-gray-600">
                        @php
                            // Determine max summary rows needed
                            $maxRows = 0;
                            foreach ($subRowSummaries as $summaryList) {
                                $maxRows = max($maxRows, count($summaryList));
                            }
                        @endphp
                        @for($i = 0; $i < $maxRows; $i++)
                            <tr class="text-xs font-medium text-gray-600 dark:text-gray-400">
                                <td class="w-8"></td>
                                @foreach($visibleSubRowColumns as $subCol)
                                    @php
                                        $colSummaries = $subRowSummaries[$subCol->getName()] ?? [];
                                        $entry = $colSummaries[$i] ?? null;
                                    @endphp
                                    <td class="px-3 py-1.5">
                                        @if($entry)
                                            <span class="text-gray-400">{{ $entry['label'] }}:</span>
                                            <span class="text-gray-700 dark:text-gray-200 font-semibold">{{ $entry['value'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                @if($hasSubRowActions)
                                    <td class="px-3 py-1.5"></td>
                                @endif
                            </tr>
                        @endfor
                    </tfoot>
                @endif
            </table>
        </div>
    </td>
</tr>
@endif
