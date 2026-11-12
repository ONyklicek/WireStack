{{-- A header filter that offers a choice of comparison: the operator picker,
     then the filter's own inline control bound to `.value` — or nothing, when
     the operator takes no value ("is empty"). --}}
{{-- Variables: $column, $filter, $value (the {value, operator} state),
     $statePath (the filter's root), $valueView, $controlClasses --}}
@php
    $operator = $filter->operatorFromState($value);
@endphp
<div class="flex flex-col gap-2" data-filter-operator="{{ $operator->value }}">
    @include('wire-table::tables.filters.partials.operator-select', [
        'raw' => $value,
        'statePath' => $statePath,
        'controlId' => 'colfilter-'.$column->getName().'-operator',
        'label' => $column->getLabel(),
    ])

    @if($operator->requiresValue())
        @include($valueView, [
            'value' => is_array($value) ? ($value['value'] ?? null) : null,
            'statePath' => $statePath.'.value',
        ])
    @endif
</div>
