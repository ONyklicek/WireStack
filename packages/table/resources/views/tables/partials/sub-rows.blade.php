{{-- Sub-rows for a parent record --}}
{{-- Variables: $table, $component, $record, $recordKey, $subRows, $colSpan, $cellPadding, $isBordered --}}
@php
    $subColumns = $table->getSubRowColumns();
    $hasSubRowActions = false; // Future: sub-row actions
    $isFilterable = $table->isSubRowsFilterable();
    $subRowSummaries = $component->computeTableSummaries('subRows', $record);
    $hasSubSummaries = !empty($subRowSummaries);
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
                                {!! $subCol->renderFilter($component->subRowFilters[$subCol->getName()] ?? null) !!}
                            </div>
                        @endif
                    @endforeach
                    @if(!empty($component->subRowFilters))
                        <button type="button" wire:click="resetSubRowFilters" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
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
                        @foreach($subColumns as $subCol)
                            @if($subCol->canView())
                                <th class="px-3 py-2 font-medium">{{ $subCol->getLabel() }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @forelse($subRows as $subRow)
                        <tr class="hover:bg-gray-100/50 dark:hover:bg-gray-700/20" wire:key="sub-row-{{ $recordKey }}-{{ $subRow->getKey() }}">
                            <td class="w-8"></td>
                            @foreach($subColumns as $subCol)
                                @if($subCol->canView())
                                    <td class="px-3 py-2 text-gray-700 dark:text-gray-300 {{ $subCol->shouldWrap() ? '' : 'whitespace-nowrap' }}">
                                        {!! $subCol->renderCell($subRow) !!}
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($subColumns) + 1 }}" class="px-3 py-4 text-center text-xs text-gray-400 dark:text-gray-500 italic">
                                {{ __('wire-table::messages.no_sub_rows') }}
                            </td>
                        </tr>
                    @endforelse
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
                                @foreach($subColumns as $subCol)
                                    @if($subCol->canView())
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
                                    @endif
                                @endforeach
                            </tr>
                        @endfor
                    </tfoot>
                @endif
            </table>
        </div>
    </td>
</tr>
