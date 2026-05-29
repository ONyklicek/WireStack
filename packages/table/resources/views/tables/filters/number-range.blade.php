{{-- Number range filter --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    $minValue = $filter->getMin();
    $maxValue = $filter->getMax();
    $step = $filter->getStep();
    $fromValue = is_array($value) ? ($value['min'] ?? '') : '';
    $toValue = is_array($value) ? ($value['max'] ?? '') : '';
@endphp

<div class="flex flex-col gap-1">
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>
    <div class="flex gap-2">
        <div class="flex-1">
            <label for="filter-{{ $name }}-min" class="sr-only">{{ $filter->getMinLabel() }}</label>
            <input
                type="number"
                id="filter-{{ $name }}-min"
                wire:model.live.debounce.500ms="tableState.filters.{{ $name }}.min"
                value="{{ $fromValue }}"
                @if($minValue !== null) min="{{ $minValue }}" @endif
                @if($maxValue !== null) max="{{ $maxValue }}" @endif
                @if($step) step="{{ $step }}" @endif
                placeholder="{{ $filter->getMinLabel() }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >
        </div>
        <span class="flex items-center text-gray-400">–</span>
        <div class="flex-1">
            <label for="filter-{{ $name }}-max" class="sr-only">{{ $filter->getMaxLabel() }}</label>
            <input
                type="number"
                id="filter-{{ $name }}-max"
                wire:model.live.debounce.500ms="tableState.filters.{{ $name }}.max"
                value="{{ $toValue }}"
                @if($minValue !== null) min="{{ $minValue }}" @endif
                @if($maxValue !== null) max="{{ $maxValue }}" @endif
                @if($step) step="{{ $step }}" @endif
                placeholder="{{ $filter->getMaxLabel() }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            >
        </div>
    </div>
</div>
