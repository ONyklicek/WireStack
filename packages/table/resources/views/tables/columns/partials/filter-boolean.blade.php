{{-- Column boolean filter (inline, in table header row) --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $trueLabel = $column->getFilterTrueLabel();
    $falseLabel = $column->getFilterFalseLabel();
@endphp

<div class="relative">
    <select
        wire:model.live="tableState.columnFilters.{{ $name }}"
        class="{{ $controlClasses }}"
    >
        <option value="">{{ __('wire-table::messages.filter_all') }}</option>
        <option value="true" @if($value === 'true' || $value === '1') selected @endif>{{ $trueLabel }}</option>
        <option value="false" @if($value === 'false' || $value === '0') selected @endif>{{ $falseLabel }}</option>
    </select>
    @include('wire-table::tables.columns.partials.filter-chevron')
</div>
