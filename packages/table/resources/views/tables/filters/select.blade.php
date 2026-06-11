{{-- Select filter --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    $placeholder = $filter->getPlaceholder();
    $options = $filter->getOptions();
    $rawValue = is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    $currentValue = $rawValue ?? $filter->getDefault();
    $isMultiple = $filter->isMultiple();
    $selectedValues = $isMultiple ? array_map('strval', (array) ($currentValue ?? [])) : [];
@endphp

<div class="flex flex-col gap-1">
    <label for="filter-{{ $name }}" class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    <select
        id="filter-{{ $name }}"
        wire:model.live="tableState.filters.{{ $name }}.value"
        @if($isMultiple) multiple @endif
        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
    >
        <option value="">{{ $placeholder }}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @if($isMultiple ? in_array((string) $optionValue, $selectedValues, true) : (string) $currentValue === (string) $optionValue) selected @endif>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>
</div>
