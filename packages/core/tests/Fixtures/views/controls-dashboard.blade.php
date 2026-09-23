{{-- A host page for WidgetDashboardControlsTest and DashboardFiltersTest: what an
     application writes around the grid — the filter bar, the controls, the grid. --}}
<div>
    @include('wire-core::widgets.partials.widget-filters')
    @include('wire-core::widgets.partials.widget-layout-controls')
    @include('wire-core::widgets.widget-grid')
</div>
