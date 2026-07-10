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
        wire:model.live="tableState.columnFilters.{{ $name }}.from"
        @if($minDate) min="{{ $minDate }}" @endif
        @if($maxDate) max="{{ $maxDate }}" @endif
        placeholder="{{ __('wire-table::messages.from') }}"
        title="{{ __('wire-table::messages.from') }}"
        class="{{ $controlClasses }}"
    >
    <input
        type="date"
        wire:model.live="tableState.columnFilters.{{ $name }}.to"
        @if($minDate) min="{{ $minDate }}" @endif
        @if($maxDate) max="{{ $maxDate }}" @endif
        placeholder="{{ __('wire-table::messages.to') }}"
        title="{{ __('wire-table::messages.to') }}"
        class="{{ $controlClasses }}"
    >
</div>
