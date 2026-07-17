{{-- Ternary (yes/no/all) filter --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $label = $filter->getLabel();
    $options = $filter->getOptions();
    $allLabel = $filter->getAllLabel();
    $rawValue = is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    $currentValue = $rawValue ?? $filter->getDefault();
    // Normalize to the option keys so a value that arrived as a real bool or as
    // 1/0 from the URL still marks the right option selected.
    $selectedValue = match (true) {
        $currentValue === 'true' || $currentValue === '1' || $currentValue === true => 'true',
        $currentValue === 'false' || $currentValue === '0' || $currentValue === false => 'false',
        default => '',
    };
@endphp

<div class="flex flex-col gap-1">
    <label for="filter-{{ $name }}" class="text-sm font-medium text-gray-700 dark:text-gray-300">
        {{ $label }}
    </label>
    @if(! $filter->isNative())
        {{-- Combobox: delegate to the canonical shared owner so a ternary filter
             matches the select filter and the forms Select. "All" is the
             placeholder — picking it clears the filter. --}}
        @include('wire-core::partials.searchable-select', [
            'selectId' => 'filter-' . $name,
            'statePath' => 'tableState.filters.' . $name . '.value',
            'options' => $options,
            'placeholder' => $allLabel,
            'multiple' => false,
            'searchable' => false,
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
            {{-- Match the combobox trigger so both variants share one design. --}}
            class="block w-full rounded-md border border-gray-300 bg-white shadow-sm text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-white"
        >
            <option value="" @if($selectedValue === '') selected @endif>{{ $allLabel }}</option>
            @foreach($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @if($selectedValue === (string) $optionValue) selected @endif>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>
    @endif
</div>
