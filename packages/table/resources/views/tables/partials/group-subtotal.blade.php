{{-- Group subtotal row(s) — mirrors the summary footer layout inside the body --}}
{{-- Variables: $table, $component, $groupSummaries, $visibleColumns, $colSpan,
     $cellPadding, $isBordered, $isSelectable, $hasActions, $actionsPosition --}}
@php
    $groupMaxRows = 0;
    foreach ($groupSummaries as $colName => $summaryList) {
        $groupMaxRows = max($groupMaxRows, count($summaryList));
    }
@endphp

@for($i = 0; $i < $groupMaxRows; $i++)
    <tr class="bg-gray-50 dark:bg-gray-800/40">
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
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ __('wire-table::messages.summary_subtotal') }}</span>
                @endif
            </td>
        @endif

        {{-- Column cells --}}
        @foreach($visibleColumns as $column)
            @php
                $colName = $column->getName();
                $colSummaries = $groupSummaries[$colName] ?? [];
                $entry = $colSummaries[$i] ?? null;
            @endphp
            <td class="{{ $cellPadding }} {{ $isBordered ? 'border border-gray-200 dark:border-gray-700' : '' }} text-{{ $column->getAlignment() }}">
                @if($entry)
                    <div class="text-xs">
                        <span class="text-gray-500 dark:text-gray-400">{{ $entry['label'] }}:</span>
                        <span class="text-gray-900 dark:text-white font-medium">{{ $entry['value'] }}</span>
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
