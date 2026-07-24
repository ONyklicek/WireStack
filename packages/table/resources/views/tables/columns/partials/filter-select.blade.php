{{-- Column select filter (inline, in table header row).
     Delegates to the canonical searchable combobox shared with forms Select and
     the table SelectFilter — so it gains search + the exact forms design. --}}
{{-- Variables: $column, $filter, $value, $controlClasses --}}
@php
    $name = $column->getName();
    $placeholder = $filter->placeholder ?? __('wire-table::messages.filter_all');
@endphp

@include('wire-core::partials.searchable-select', [
    'selectId' => 'colfilter-'.$name,
    'statePath' => $statePath,
    'options' => $filter->getOptions(),
    'placeholder' => $placeholder,
    'multiple' => false,
    'searchable' => $filter->isSearchable(),
    'searchPrompt' => __('wire-table::messages.filter_search'),
    'noResultsMessage' => __('wire-table::messages.filter_no_results'),
    // Searchable filters keep the floating panel on mobile so the search input
    // stays usable; non-searchable ones use the global sheet default.
    'sheetOnMobile' => $filter->isSearchable() ? false : (bool) config('wire-core.mobile.sheet', true),
    // Column filters apply immediately.
    'live' => true,
])
