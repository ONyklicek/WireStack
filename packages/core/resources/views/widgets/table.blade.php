<div class="wire-table-widget rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @if($widget->getHeading())
        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $widget->getHeading() }}</h3>
            @if($widget->getDescription())
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $widget->getDescription() }}</p>
            @endif
        </div>
    @endif

    <div class="wire-table-widget-content">
        {{-- Table will be rendered by the Livewire component using the tableCallback --}}
    </div>
</div>
