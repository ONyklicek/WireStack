{{-- Ternary (yes/no/all) filter --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    $currentValue = $value ?? $filter->getDefault();
@endphp

<div class="flex flex-col gap-1">
    <label for="filter-{{ $name }}" class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <select
        id="filter-{{ $name }}"
        wire:model.live="tableFilters.{{ $name }}"
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
    >
        <option value="" @if($currentValue === null || $currentValue === '') selected @endif>
            {{ $filter->getAllLabel() }}
        </option>
        <option value="true" @if($currentValue === 'true' || $currentValue === '1') selected @endif>
            {{ $filter->getTrueLabel() }}
        </option>
        <option value="false" @if($currentValue === 'false' || $currentValue === '0') selected @endif>
            {{ $filter->getFalseLabel() }}
        </option>
    </select>
</div>
