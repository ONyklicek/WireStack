<div class="wire-chart-widget rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
     x-data="wireChart(@js($type), @js($labels), @js($datasets), @js($filterOptions), @js($activeFilter), @js($options))">

    @if($widget->getHeading() || $widget->hasFilter())
        <div class="mb-4 flex items-center justify-between">
            <div>
                @if($widget->getHeading())
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $widget->getHeading() }}</h3>
                @endif
                @if($widget->getDescription())
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $widget->getDescription() }}</p>
                @endif
            </div>

            @if($filterOptions)
                <select x-model="activeFilter" x-on:change="updateChart()"
                        data-testid="chart-filter"
                        class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    @foreach($filterOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    @endif

    {{-- wire:ignore: Chart.js owns this canvas DOM. Without it a Livewire morph
         can touch the canvas mid-render and fight Chart.js for it. --}}
    <div class="wire-chart-canvas" wire:ignore>
        <canvas x-ref="canvas" data-testid="chart-canvas" style="width: 100%; height: 250px;"></canvas>
    </div>
</div>

@include('wire-core::widgets.partials.chart-assets')
