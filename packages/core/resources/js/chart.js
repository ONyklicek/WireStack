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
 *
 * ## There is no update path here, deliberately
 *
 * This controller builds a chart and tears it down. It used to carry the filter
 * as well — `filterOptions`, `activeFilter` and an `updateChart()` bound to a
 * `<select>` — and that was the bug rather than the feature: `updateChart()`
 * assigned `this.labels` and `this.datasets` back onto the chart it had just
 * been constructed with, the same two arrays, so changing the selection redrew
 * an identical chart and the server-side dataset closure never ran with
 * anything but its default.
 *
 * A filter is resolved on the server now, where the closure is, and the answer
 * arrives as this widget's `wire:partial` region. New data therefore means a new
 * element: the wrapper's `wire:key` carries the active filter, so the morph
 * replaces rather than patches, `destroy()` below tears the old chart down and
 * `init()` builds one over the new data. Alpine never re-evaluates `x-data` on
 * an element it has already initialised, which is exactly why patching could
 * never have worked.
 */
const wireChart = (type, labels, datasets, options) => ({
    type,
    labels,
    datasets,
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
