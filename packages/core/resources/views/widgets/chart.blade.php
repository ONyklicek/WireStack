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
        <canvas x-ref="canvas" style="width: 100%; height: 250px;"></canvas>
    </div>
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('wireChart', (type, labels, datasets, filterOptions, activeFilter, options) => ({
                type,
                labels,
                datasets,
                filterOptions,
                activeFilter,
                options,
                chart: null,

                init() {
                    if (typeof Chart === 'undefined') {
                        console.warn('Chart.js is not loaded. Include Chart.js to enable chart widgets.');
                        return;
                    }
                    this.chart = new Chart(this.$refs.canvas, {
                        type: this.type,
                        data: { labels: this.labels, datasets: this.datasets },
                        options: this.options,
                    });
                },

                destroy() {
                    // Without this, an Alpine re-init (Livewire morph that changes
                    // the datasets baked into x-data) leaks the previous Chart.js
                    // instance and its RAF/listeners, and the next new Chart() throws
                    // "Canvas is already in use".
                    this.chart?.destroy();
                    this.chart = null;
                },

                updateChart() {
                    if (this.chart) {
                        this.chart.data.labels = this.labels;
                        this.chart.data.datasets = this.datasets;
                        this.chart.update();
                    }
                },
            }));
        });
    </script>
    @endpush
@endonce
