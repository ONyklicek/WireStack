{{-- A range DateFilter with presets in the filter panel: the label, then the
     same control the header draws (tables.columns.partials.filter-date-range),
     bound under tableState.filters. --}}
{{-- Variables: $filter, $value --}}
<div class="flex flex-col gap-1">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $filter->getLabel() }}</span>
    @include('wire-table::tables.columns.partials.filter-date-range', [
        'statePath' => 'tableState.filters.'.$filter->getName(),
        'controlClasses' => \NyonCode\WireTable\Support\FilterControl::classes(),
    ])
</div>
