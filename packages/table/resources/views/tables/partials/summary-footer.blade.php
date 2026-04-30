{{-- Table summary footer --}}
{{-- Variables: $table, $component, $summaries, $isSelectable, $hasActions, $actionsPosition, $cellPadding, $isBordered, $visibleColumns --}}
@php
    // Determine how many summary rows we need
    $maxRows = 0;
    foreach ($summaries as $colName => $summaryList) {
        $maxRows = max($maxRows, count($summaryList));
    }
@endphp

@if($maxRows > 0)
    <tfoot class="bg-gray-50 dark:bg-gray-800/50 border-t-2 border-gray-300 dark:border-gray-600">
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
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Celkem</span>
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
                    <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} text-{{ $column->getAlignment() }}">
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
    </tfoot>
@endif
