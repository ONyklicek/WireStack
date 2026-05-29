{{-- Text filter --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    $placeholder = $filter->getPlaceholder();
    $currentValue = $value ?? $filter->getDefault();
@endphp

<div class="flex flex-col gap-1">
    <label for="filter-{{ $name }}" class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <input
        type="text"
        id="filter-{{ $name }}"
        wire:model.live.debounce.300ms="tableState.filters.{{ $name }}"
        value="{{ $currentValue }}"
        placeholder="{{ $placeholder }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
    >
</div>
