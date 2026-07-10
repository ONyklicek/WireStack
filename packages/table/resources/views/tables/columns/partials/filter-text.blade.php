{{-- Column text filter (inline, in table header row) --}}
{{-- Variables: $column --}}
@php
    $name = $column->getName();
    $placeholder = $column->getFilterPlaceholder() ?? __('wire-table::messages.filter_placeholder');
@endphp

<input
    type="text"
    wire:model.live.debounce.300ms="tableState.columnFilters.{{ $name }}"
    placeholder="{{ $placeholder }}"
    class="{{ $controlClasses }}"
>
