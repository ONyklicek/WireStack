{{-- A single-date DateFilter in the filter panel: the label, the operator
     picker when the filter offers one, and the forms DateTimePicker
     (DateFilter::dateControl()) — the same calendar a form draws. --}}
{{-- Variables: $filter, $value --}}
@php
    $name = $filter->getName();
    $base = 'tableState.filters.'.$name;
    $hasPicker = $filter->hasOperatorPicker();
@endphp
<div class="flex flex-col gap-1">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $filter->getLabel() }}</span>
    @if($hasPicker)
        @include('wire-table::tables.filters.partials.operator-select', [
            'raw' => $value,
            'statePath' => $base,
            'controlId' => 'filter-'.$name.'-operator',
            'label' => $filter->getLabel(),
        ])
    @endif
    @if(! $hasPicker || $filter->operatorFromState($value)->requiresValue())
        {!! $filter->controlHtml($filter->dateControl($base.'.value')) !!}
    @endif
</div>
