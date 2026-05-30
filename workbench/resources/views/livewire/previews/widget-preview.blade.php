<div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        if (window.Chart) {
            Chart.defaults.animation = false;
            Chart.defaults.font.family = 'Inter, ui-sans-serif, system-ui, sans-serif';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.boxWidth = 8;
        }
    </script>

    @if ($variant === 'chart')
        <div
            data-preview-root
            class="mx-auto w-[1100px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
        >
            <div data-preview-focus class="rounded-[28px] border border-slate-200 bg-white p-7">
                <div class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">Chart widget</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-900">Interactive Chart.js surface</h2>
                    <p class="mt-1 text-sm text-slate-500">Heading, description, and a live filter dropdown wired to the dataset.</p>
                </div>

                <div class="[&_select]:!w-36 [&_select]:!pr-9 [&_select]:!text-left [&_canvas]:!h-[440px]">
                    {!! $barChart !!}
                </div>
            </div>
        </div>
    @else
        <div
            data-preview-root
            class="mx-auto w-[1280px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
        >
            <div data-preview-focus class="rounded-[28px] border border-slate-200 bg-white p-7">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">Dashboard</p>
                        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Operations overview</h2>
                        <p class="mt-1 text-sm text-slate-500">Stats overview and a chart widget composed in a single grid.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Live · polling 30s
                    </span>
                </div>

                <div class="mt-6">
                    {!! $stats !!}
                </div>

                <div class="mt-6 [&_canvas]:!h-[380px]">
                    {!! $lineChart !!}
                </div>
            </div>
        </div>
    @endif

    @stack('scripts')
</div>
