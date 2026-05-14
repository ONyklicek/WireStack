{{-- Column boolean filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $trueLabel = $column->getFilterTrueLabel();
    $falseLabel = $column->getFilterFalseLabel();
@endphp

<select
    wire:model.live="columnFilters.{{ $name }}"
    class="block w-full rounded-md border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1"
>
    <option value="">{{ __('wire-table::messages.filter_all') }}</option>
    <option value="true" @if($value === 'true' || $value === '1') selected @endif>{{ $trueLabel }}</option>
    <option value="false" @if($value === 'false' || $value === '0') selected @endif>{{ $falseLabel }}</option>
</select>
