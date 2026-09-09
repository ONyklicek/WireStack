/**
 * wireChart — the Alpine controller behind `ChartWidget`.
 *
 * Shipped as its own bundle rather than folded into `wire-core-dropdown.js`:
 * charts are an optional, heavy surface (see
 * architecture/plans/js-asset-registration.md §3.C), so the *body* is fetched
 * only by pages that actually render a chart widget. The *registrator* below is
 * never lazy — the moment this file executes, `wireChart` exists.
 *
 * Chart.js itself is NOT shipped by this package. The consuming app includes it
 * (CDN or its own bundle); without it the widget degrades to an empty canvas and
 * one console warning, and never throws.
 */
const wireChart = (type, labels, datasets, filterOptions, activeFilter, options) => ({
    type,
    labels,
    datasets,
    filterOptions,
    activeFilter,
    options,
    chart: null,

    init() {
        // Read Chart off `window` explicitly: this file is bundled, so a bare
        // `Chart` would be resolved against the module scope, not the global.
        if (! window.Chart) {
            console.warn('Chart.js is not loaded. Include Chart.js to enable chart widgets.')

            return
        }

        this.chart = new window.Chart(this.$refs.canvas, {
            type: this.type,
            data: { labels: this.labels, datasets: this.datasets },
            options: this.options,
        })
    },

    destroy() {
        // Without this, an Alpine re-init (a Livewire morph that changes the
        // datasets baked into x-data) leaks the previous Chart.js instance and
        // its RAF/listeners, and the next new Chart() throws "Canvas is already
        // in use".
        this.chart?.destroy()
        this.chart = null
    },

    updateChart() {
        if (! this.chart) return

        this.chart.data.labels = this.labels
        this.chart.data.datasets = this.datasets
        this.chart.update()
    },
})

// ─── Self-registration ──────────────────────────────────────────
// `alpine:init` fires exactly once per document, so a bundle that arrives after
// a wire:navigate — or with a Livewire-loaded modal — would subscribe to an
// event that already fired and register nothing, leaving every
// x-data="wireChart(...)" evaluating against an empty registry. The listener is
// only the cold-load fallback for this idempotent registrar.
let registered = false
const registerWireCoreChart = () => {
    if (registered || ! window.Alpine) return
    registered = true

    window.Alpine.data('wireChart', wireChart)
}

if (window.Alpine) {
    // Alpine already started (e.g. the script loaded after a Livewire navigation).
    registerWireCoreChart()
} else {
    document.addEventListener('alpine:init', registerWireCoreChart)
}
