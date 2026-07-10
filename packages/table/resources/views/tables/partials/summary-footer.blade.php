{{-- Table summary footer --}}
{{-- Variables: $table, $component, $summaries, $summaryScope, $summaryScopeOptions,
     $isSelectable, $hasActions, $actionsPosition, $cellPadding, $isBordered, $visibleColumns, $colSpan,
     $subRowGrandTotals (optional) --}}
@php
    // Determine how many summary rows we need
    $maxRows = 0;
    foreach ($summaries as $colName => $summaryList) {
        $maxRows = max($maxRows, count($summaryList));
    }

    $summaryScope = $summaryScope ?? 'query';
    $summaryScopeOptions = $summaryScopeOptions ?? ['query', 'page'];
    $showScopeToggle = count($summaryScopeOptions) > 1;
    $subRowGrandTotals = $subRowGrandTotals ?? [];
@endphp

@if($maxRows > 0 || $subRowGrandTotals !== [])
    <tfoot class="bg-gray-50 dark:bg-gray-800/50 border-t-2 border-gray-300 dark:border-gray-600">
        {{-- Scope toggle row: this page / all / selection --}}
        @if($showScopeToggle)
            <tr>
                <td colspan="{{ $colSpan }}" class="{{ $cellPadding }} py-2">
                    <div class="flex items-center justify-end gap-2">
                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ __('wire-table::messages.summary_scope_label') }}</span>
                        <div class="inline-flex rounded-md border border-gray-200 dark:border-gray-600 overflow-hidden text-xs">
                            @foreach($summaryScopeOptions as $option)
                                <button type="button"
                                        wire:click="setSummaryScope('{{ $option }}')" data-testid="summary-scope-{{ $option }}"
                                        @class([
                                            'px-2.5 py-1 font-medium transition-colors',
                                            'bg-primary-600 text-white' => $summaryScope === $option,
                                            'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' => $summaryScope !== $option,
                                            'border-l border-gray-200 dark:border-gray-600' => !$loop->first,
                                        ])>
                                    {{ __('wire-table::messages.summary_scope_'.$option) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </td>
            </tr>
        @endif

        @for($i = 0; $i < $maxRows; $i++)
            <tr>
                {{-- Selection spacer --}}
                @if($isSelectable)
                    <td class="w-12 {{ $cellPadding }}"></td>
                @endif

                {{-- Sub-row toggle spacer --}}
                @if($table->hasSubRows() && $table->isSubRowsExpandable())
                    <td class="w-10 {{ $cellPadding }}"></td>
                @endif

                {{-- Actions (start position) --}}
                @if($hasActions && $actionsPosition === 'start')
                    <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}">
                        @if($i === 0)
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.summary_total') }}</span>
                        @endif
                    </td>
                @endif

                {{-- Column cells --}}
                @foreach($visibleColumns as $column)
                    @php
                        $colName = $column->getName();
                        $colSummaries = $summaries[$colName] ?? [];
                        $entry = $colSummaries[$i] ?? null;
                    @endphp
                    <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} {{ $column->getAlignmentClass() }}">
                        @if($entry)
                            <div class="text-xs">
                                <span class="text-gray-500 dark:text-gray-400">{{ $entry['label'] }}:</span>
                                <span class="text-gray-900 dark:text-white font-semibold">{{ $entry['value'] }}</span>
                            </div>
                        @endif
                    </td>
                @endforeach

                {{-- Actions (end position) --}}
                @if($hasActions && $actionsPosition === 'end')
                    <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }}"></td>
                @endif
            </tr>
        @endfor

        {{-- Sub-row grand totals: children across all parents. Sub-row columns
             don't align with the parent grid, so these render full-width. --}}
        @foreach($subRowGrandTotals as $colName => $entries)
            @foreach($entries as $entry)
                <tr>
                    <td colspan="{{ $colSpan }}" class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} text-right">
                        <div class="text-xs">
                            <span class="text-gray-500 dark:text-gray-400">{{ $entry['label'] }}:</span>
                            <span class="text-gray-900 dark:text-white font-semibold">{{ $entry['value'] }}</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        @endforeach
    </tfoot>
@endif
