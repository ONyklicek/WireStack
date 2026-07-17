{{-- Column boolean filter (inline, in table header row).
     Delegates to the canonical searchable combobox shared with the table
     SelectFilter and the forms Select — so a boolean header filter matches the
     select ones sitting next to it. "All" is the placeholder (clears the filter). --}}
{{-- Variables: $column, $filter, $value, $controlClasses --}}
@php
    $name = $column->getName();
@endphp

@include('wire-core::partials.searchable-select', [
    'selectId' => 'colfilter-'.$name,
    'statePath' => 'tableState.columnFilters.'.$name,
    'options' => $filter->getOptions(),
    'placeholder' => $filter->getAllLabel(),
    'multiple' => false,
    'searchable' => false,
    'searchPrompt' => __('wire-table::messages.filter_search'),
    'noResultsMessage' => __('wire-table::messages.filter_no_results'),
    'sheetOnMobile' => (bool) config('wire-core.mobile.sheet', true),
    // Column filters apply immediately.
    'live' => true,
])
