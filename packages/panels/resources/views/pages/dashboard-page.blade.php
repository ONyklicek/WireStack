{{-- Dashboard page: an optional heading over the dashboard's widget grid, and
     — when the dashboard says `customisable()` — the controls for rearranging
     it.

     The grid itself is wire-core's, the same view a hand-written WithWidgets
     component renders, and so are the controls: this page decides where they
     sit, not what they do. A page that wants them somewhere else drops this
     include and puts `wire-core::widgets.partials.widget-layout-controls`
     wherever its own layout puts buttons.

     Both partials render nothing on a dashboard nobody may rearrange, so there
     is no condition here to keep in step with one over there — and the filter
     bar renders nothing on a dashboard that declares no filters.

     Variables: everything `WithWidgets::widgetGridData()` returns, plus $title --}}
<div class="wire-dashboard-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header', ['breadcrumbs' => []])

    {{-- Filters and controls share a row: the filters narrow what the grid
         shows, the controls change how it is arranged, and both sit over it. --}}
    <div class="flex flex-wrap items-center gap-3">
        @include('wire-core::widgets.partials.widget-filters')

        <div class="ml-auto">
            @include('wire-core::widgets.partials.widget-layout-controls')
        </div>
    </div>

    @include('wire-core::widgets.widget-grid')
</div>
