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
    // Normalize the current value(s) to a list of comparable strings. Works for
    // both single and multiple selects and guards against array values reaching
    // a scalar/echo context (array default, multi/single mismatch, stale state).
    $selectedValues = array_map('strval', array_filter(
        is_array($currentValue) ? $currentValue : [$currentValue],
        static fn ($v) => $v !== null && $v !== '',
    ));
@endphp

<div class="flex flex-col gap-1">
    <label for="filter-{{ $name }}" class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    @if($filter->isSearchable() && ! $filter->isNative())
        {{-- Searchable combobox: delegate to the canonical shared owner. --}}
        @include('wire-core::partials.searchable-select', [
            'selectId' => 'filter-' . $name,
            'statePath' => 'tableState.filters.' . $name . '.value',
            'options' => $options,
            'placeholder' => $placeholder,
            'multiple' => $isMultiple,
            'searchPrompt' => __('Search...'),
            'noResultsMessage' => __('No results found'),
        ])
    @else
        <select
            id="filter-{{ $name }}"
            wire:model.live="tableState.filters.{{ $name }}.value"
            @if($isMultiple) multiple @endif
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        >
            <option value="">{{ $placeholder }}</option>
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @if(in_array((string) $optionValue, $selectedValues, true)) selected @endif>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    @endif
</div>
