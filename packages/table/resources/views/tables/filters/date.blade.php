{{-- Date filter --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    $isRange = $filter->isRange();
    $minDate = $filter->getMinDate();
    $maxDate = $filter->getMaxDate();
@endphp

<div class="flex flex-col gap-1">
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</label>

    @if($isRange)
        @php
            $fromValue = is_array($value) ? ($value['from'] ?? '') : '';
            $toValue = is_array($value) ? ($value['to'] ?? '') : '';
        @endphp
        <div class="flex gap-2">
            <div class="flex-1">
                <label for="filter-{{ $name }}-from" class="sr-only">{{ $filter->getFromLabel() }}</label>
                <input
                    type="date"
                    id="filter-{{ $name }}-from"
                    wire:model.live="tableFilters.{{ $name }}.from"
                    value="{{ $fromValue }}"
                    @if($minDate) min="{{ $minDate }}" @endif
                    @if($maxDate) max="{{ $maxDate }}" @endif
                    placeholder="{{ $filter->getFromLabel() }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
            </div>
            <div class="flex-1">
                <label for="filter-{{ $name }}-to" class="sr-only">{{ $filter->getToLabel() }}</label>
                <input
                    type="date"
                    id="filter-{{ $name }}-to"
                    wire:model.live="tableFilters.{{ $name }}.to"
                    value="{{ $toValue }}"
                    @if($minDate) min="{{ $minDate }}" @endif
                    @if($maxDate) max="{{ $maxDate }}" @endif
                    placeholder="{{ $filter->getToLabel() }}"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
            </div>
        </div>
    @else
        @php $currentValue = $value ?? ($filter->getDefault() ?? ''); @endphp
        <input
            type="date"
            id="filter-{{ $name }}"
            wire:model.live="tableFilters.{{ $name }}"
            value="{{ $currentValue }}"
            @if($minDate) min="{{ $minDate }}" @endif
            @if($maxDate) max="{{ $maxDate }}" @endif
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        >
    @endif
</div>
