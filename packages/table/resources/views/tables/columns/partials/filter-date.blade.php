{{-- Column date filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $minDate = $column->getFilterMinDate();
    $maxDate = $column->getFilterMaxDate();
@endphp

<input
    type="date"
    wire:model.live="columnFilters.{{ $name }}"
    @if($minDate) min="{{ $minDate }}" @endif
    @if($maxDate) max="{{ $maxDate }}" @endif
    class="block w-full rounded-md border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1 px-1"
>
