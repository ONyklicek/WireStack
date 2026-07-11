{{-- Active filter indicator chips (panel + column header filters) with
     per-filter remove buttons. --}}
{{-- Variables: $component --}}
@php
    $indicators = $component->getActiveFilterIndicators();
    $columnIndicators = $component->getActiveColumnFilterIndicators();
    $total = count($indicators) + count($columnIndicators);
@endphp

@if($total > 0)
    <div class="flex flex-wrap items-center gap-2 px-4 lg:px-6 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
        @foreach($indicators as $filterName => $indicatorLabel)
            @include('wire-table::tables.partials.filter-chip', [
                'name' => $filterName,
                'label' => $indicatorLabel,
                'removeMethod' => 'removeTableFilter',
                'keyPrefix' => 'filter-indicator',
                'testidPrefix' => 'filter-chip',
            ])
        @endforeach

        @foreach($columnIndicators as $filterName => $indicatorLabel)
            @include('wire-table::tables.partials.filter-chip', [
                'name' => $filterName,
                'label' => $indicatorLabel,
                'removeMethod' => 'removeColumnFilter',
                'keyPrefix' => 'column-filter-indicator',
                'testidPrefix' => 'column-filter-chip',
            ])
        @endforeach

        @if($total > 1)
            <button
                    type="button"
                    wire:click="resetTableFilters"
                    data-testid="table-filter-reset"
                    class="text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                {{ __('wire-table::messages.filter_reset') }}
            </button>
        @endif
    </div>
@endif
