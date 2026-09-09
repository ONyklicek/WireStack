{{-- The two pure-CSS widget types, laid out the way a dashboard would.

     Variables: $quota, $capacity, $orders, $escalations --}}
<div
    data-preview-root
    class="mx-auto w-full max-w-[1280px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="rounded-[28px] border border-slate-200 bg-white p-7">
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">Progress &amp; list widgets</p>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">Pure CSS · no JavaScript</h2>
            <p class="mt-1 text-sm text-slate-500">A fill against a target, a short feed of records, header actions, a filter, and an empty state.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {!! $quota !!}
            {!! $orders !!}
            {!! $capacity !!}
            {!! $escalations !!}
        </div>
    </div>
</div>
