<div class="wire-custom-widget rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    @if($widget->getHeading())
        <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">{{ $widget->getHeading() }}</h3>
    @endif
    @if($widget->getDescription())
        <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">{{ $widget->getDescription() }}</p>
    @endif
</div>
