{{-- The interactive widget grid, with Chart.js on the page.

     The polling variant renders `wire-core::widgets.widget-grid` directly
     because nothing on it needs a script. This one has a chart, and a chart
     widget without Chart.js degrades to an empty canvas and a console warning —
     which would leave the driver measuring nothing.

     Variables: $widgets, $columns --}}
<div data-preview-root class="mx-auto w-full max-w-[1100px] p-5">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        if (window.Chart) {
            // No animation: a driver reading the chart's data straight after a
            // morph must not race a 400 ms tween.
            Chart.defaults.animation = false;
        }
    </script>

    <div data-preview-focus>
        @include('wire-core::widgets.widget-grid', ['widgets' => $widgets, 'columns' => $columns])
    </div>
</div>
