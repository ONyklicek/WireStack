{{-- Dashboard page: optional heading over the dashboard's widget grid.

     The grid itself is wire-core's — the same view a hand-written WithWidgets
     component renders — so a dashboard page adds a heading and nothing else to
     what was already there. --}}
<div class="wire-dashboard-page space-y-4 sm:space-y-6">
    @include('wire-panels::pages.partials.header', ['breadcrumbs' => []])

    @include('wire-core::widgets.widget-grid', ['widgets' => $widgets, 'columns' => $columns])
</div>
