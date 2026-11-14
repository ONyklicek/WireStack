{{-- Single-date filter: the forms DateTimePicker (DateFilter::dateControl()),
     the calendar a form draws — a bottom sheet on a phone — bound straight to
     the filter's state path. --}}
{{-- Variables: $filter, $value, $statePath --}}
<div class="min-w-0">
    {!! $filter->controlHtml($filter->dateControl($statePath)) !!}
</div>
