{{-- Column number range filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $minValue = $column->getFilterMinValue();
    $maxValue = $column->getFilterMaxValue();
    $step = $column->getFilterStep();
    $debounce = $column->getFilterDebounce();
    $fromValue = is_array($value) ? ($value['min'] ?? '') : '';
    $toValue = is_array($value) ? ($value['max'] ?? '') : '';
@endphp

<div class="flex gap-1 items-center">
    <input
        type="number"
        wire:model.live.debounce.{{ $debounce }}ms="tableState.columnFilters.{{ $name }}.min"
        @if($minValue !== null) min="{{ $minValue }}" @endif
        @if($maxValue !== null) max="{{ $maxValue }}" @endif
        @if($step) step="{{ $step }}" @endif
        placeholder="{{ __('wire-table::messages.filter_min') }}"
        title="{{ __('wire-table::messages.filter_min') }}"
        class="{{ $controlClasses }}"
    >
    <span class="text-gray-400 text-xs flex-shrink-0">–</span>
    <input
        type="number"
        wire:model.live.debounce.{{ $debounce }}ms="tableState.columnFilters.{{ $name }}.max"
        @if($minValue !== null) min="{{ $minValue }}" @endif
        @if($maxValue !== null) max="{{ $maxValue }}" @endif
        @if($step) step="{{ $step }}" @endif
        placeholder="{{ __('wire-table::messages.filter_max') }}"
        title="{{ __('wire-table::messages.filter_max') }}"
        class="{{ $controlClasses }}"
    >
</div>
