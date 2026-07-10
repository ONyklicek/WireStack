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
    @if(! $filter->isNative())
        {{-- Combobox: delegate to the canonical shared owner. Non-searchable
             filters use the same UI without the search input, so both match. --}}
        @include('wire-core::partials.searchable-select', [
            'selectId' => 'filter-' . $name,
            'statePath' => 'tableState.filters.' . $name . '.value',
            'options' => $options,
            'placeholder' => $placeholder,
            'multiple' => $isMultiple,
            'searchable' => $filter->isSearchable(),
            'searchPrompt' => __('Search...'),
            'noResultsMessage' => __('No results found'),
            'sheetOnMobile' => $filter->usesSheetOnMobile(),
            'mobileBreakpoint' => $filter->getMobileBreakpoint(),
            // Filters must apply immediately — match the native path's wire:model.live.
            'live' => true,
        ])
    @else
        <select
            id="filter-{{ $name }}"
            wire:model.live="tableState.filters.{{ $name }}.value" data-testid="filter-{{ $name }}"
            @if($isMultiple) multiple @endif
            {{-- Match the searchable combobox trigger so both filter variants share one design. --}}
            class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-white"
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
