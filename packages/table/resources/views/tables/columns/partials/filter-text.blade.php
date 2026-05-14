{{-- Column text filter (inline, in table header row) --}}
{{-- Variables: $column --}}
@php
    $name = $column->getName();
    $placeholder = $column->getFilterPlaceholder() ?? __('wire-table::messages.filter_placeholder');
@endphp

<input
    type="text"
    wire:model.live.debounce.300ms="columnFilters.{{ $name }}"
    placeholder="{{ $placeholder }}"
    class="block w-full rounded-md border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-xs text-gray-600 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-primary-500 py-1 px-2"
>
