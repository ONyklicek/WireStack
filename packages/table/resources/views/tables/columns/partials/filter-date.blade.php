{{-- Column date filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $minDate = $column->getFilterMinDate();
    $maxDate = $column->getFilterMaxDate();
@endphp

<input
    type="date"
    wire:model.live="tableState.columnFilters.{{ $name }}"
    @if($minDate) min="{{ $minDate }}" @endif
    @if($maxDate) max="{{ $maxDate }}" @endif
    class="{{ $controlClasses }}"
>
