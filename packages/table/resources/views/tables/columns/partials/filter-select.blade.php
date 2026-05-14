{{-- Column select filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $placeholder = $column->getFilterPlaceholder() ?? __('wire-table::messages.filter_all');
    $options = $column->getFilterOptions();
@endphp

<select
    wire:model.live="columnFilters.{{ $name }}"
    class="block w-full rounded-md border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1"
>
    <option value="">{{ $placeholder }}</option>
    @foreach($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @if((string) $value === (string) $optionValue) selected @endif>
            {{ $optionLabel }}
        </option>
    @endforeach
</select>
