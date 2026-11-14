{{-- Date range filter: the forms DateRangePicker (DateFilter::rangeControl()) —
     one box and two months side by side from the breakpoint up, the two
     pickers with the presets as chips on a phone. With ->presets() the picked
     preset's name is written beside the dates, so the range stays relative.
     Drawn in the header row, in a column popover, and in the filter panel. --}}
{{-- Variables: $filter, $value, $statePath --}}
<div class="min-w-0" data-testid="date-range-filter-{{ str_replace('.', '-', $statePath) }}">
    {!! $filter->controlHtml($filter->rangeControl($statePath)) !!}
</div>
