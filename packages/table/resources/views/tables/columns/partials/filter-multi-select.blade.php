{{-- Column multi-select filter (inline, in the table header row).
     Delegates to the canonical searchable combobox (multiple mode) shared with
     forms Select and the table SelectFilter — search + the exact forms design,
     matching any of the picked values (whereIn). --}}
{{-- Variables: $column, $value --}}
@php
    $name = $column->getName();
    $placeholder = $column->getFilterPlaceholder() ?? __('wire-table::messages.filter_all');
@endphp

@include('wire-core::partials.searchable-select', [
    'selectId' => 'colfilter-'.$name,
    'statePath' => 'tableState.columnFilters.'.$name,
    'options' => $column->getFilterOptions(),
    'placeholder' => $placeholder,
    'multiple' => true,
    'searchable' => $column->isFilterSearchable(),
    'searchPrompt' => __('wire-table::messages.filter_search'),
    'noResultsMessage' => __('wire-table::messages.filter_no_results'),
    'sheetOnMobile' => $column->isFilterSearchable() ? false : (bool) config('wire-core.mobile.sheet', true),
    'live' => true,
])
