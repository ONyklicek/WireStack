{{-- Column multi-select filter (inline, in the table header row).
     Delegates to the canonical searchable combobox (multiple mode) shared with
     forms Select and the table SelectFilter — search + the exact forms design,
     matching any of the picked values (whereIn). --}}
{{-- Variables: $column, $filter, $value, $controlClasses --}}
@php
    $name = $column->getName();
    $placeholder = $filter->placeholder ?? __('wire-table::messages.filter_all');
@endphp

@include('wire-core::partials.searchable-select', [
    'selectId' => 'colfilter-'.$name,
    'statePath' => 'tableState.columnFilters.'.$name,
    'options' => $filter->getOptions(),
    'placeholder' => $placeholder,
    'multiple' => true,
    'searchable' => $filter->isSearchable(),
    'searchPrompt' => __('wire-table::messages.filter_search'),
    'noResultsMessage' => __('wire-table::messages.filter_no_results'),
    'sheetOnMobile' => $filter->isSearchable() ? false : (bool) config('wire-core.mobile.sheet', true),
    'live' => true,
])
