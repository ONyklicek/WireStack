{{-- Column date range filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $minDate = $column->getFilterMinDate();
    $maxDate = $column->getFilterMaxDate();
    $fromValue = is_array($value) ? ($value['from'] ?? '') : '';
    $toValue = is_array($value) ? ($value['to'] ?? '') : '';
@endphp

<div class="flex gap-1">
    <input
        type="date"
        wire:model.live="columnFilters.{{ $name }}.from"
        @if($minDate) min="{{ $minDate }}" @endif
        @if($maxDate) max="{{ $maxDate }}" @endif
        placeholder="Od"
        title="Od"
        class="block w-full rounded-md border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1 px-1"
    >
    <input
        type="date"
        wire:model.live="columnFilters.{{ $name }}.to"
        @if($minDate) min="{{ $minDate }}" @endif
        @if($maxDate) max="{{ $maxDate }}" @endif
        placeholder="Do"
        title="Do"
        class="block w-full rounded-md border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300 focus:border-primary-500 focus:ring-primary-500 py-1 px-1"
    >
</div>
