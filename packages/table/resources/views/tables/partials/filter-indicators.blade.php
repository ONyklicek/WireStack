{{-- Active filter indicator chips with per-filter remove buttons. --}}
{{-- Variables: $component --}}
@php
    $indicators = $component->getActiveFilterIndicators();
@endphp

@if($indicators !== [])
    <div class="flex flex-wrap items-center gap-2 px-4 lg:px-6 py-2.5 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
        @foreach($indicators as $filterName => $indicatorLabel)
            <span
                    wire:key="filter-indicator-{{ $filterName }}"
                    data-testid="filter-chip-{{ $filterName }}"
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 dark:bg-primary-500/10 py-1 pl-3 pr-1.5 text-xs font-medium text-primary-700 dark:text-primary-300 ring-1 ring-inset ring-primary-600/20 dark:ring-primary-400/20"
            >
                {{ $indicatorLabel }}
                <button
                        type="button"
                        wire:click="removeTableFilter('{{ $filterName }}')"
                        data-testid="filter-chip-remove-{{ $filterName }}"
                        title="{{ __('wire-table::messages.filter_remove') }}"
                        class="inline-flex items-center justify-center rounded-full p-0.5 text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-500/20 hover:text-primary-800 dark:hover:text-primary-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    <span class="sr-only">{{ __('wire-table::messages.filter_remove') }}: {{ $indicatorLabel }}</span>
                    <x-wire::icon name="outline:x-mark" size="h-3.5 w-3.5" />
                </button>
            </span>
        @endforeach

        @if(count($indicators) > 1)
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
