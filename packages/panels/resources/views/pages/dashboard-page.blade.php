{{-- Dashboard page: optional heading over the dashboard's widget grid.

     The grid itself is wire-core's — the same view a hand-written WithWidgets
     component renders — so a dashboard page adds a heading and nothing else to
     what was already there. --}}
<div class="wire-dashboard-page space-y-4">
    @if($title)
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h1>
    @endif

    @include('wire-core::widgets.widget-grid', ['widgets' => $widgets, 'columns' => $columns])
</div>
