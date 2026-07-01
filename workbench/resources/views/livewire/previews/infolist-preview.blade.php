<div>
    @if ($variant === 'entries')
        <div
            data-preview-root
            class="mx-auto w-[1100px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
        >
            <div data-preview-focus class="rounded-[28px] border border-slate-200 bg-white p-7">
                <div class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">Infolist</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-900">Entry types</h2>
                    <p class="mt-1 text-sm text-slate-500">Text, badge, list, boolean icon, color, key-value, and repeatable entries — all bound to one record.</p>
                </div>

                {{ $entries }}
            </div>
        </div>
    @else
        <div
            data-preview-root
            class="mx-auto w-[1100px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
        >
            <div data-preview-focus class="rounded-[28px] border border-slate-200 bg-white p-7">
                <div class="mb-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">Infolist</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-900">Record overview</h2>
                    <p class="mt-1 text-sm text-slate-500">Read-only, schema-driven display of a single record with sections, a column grid, and formatted entries.</p>
                </div>

                <div class="space-y-6">
                    {{ $overview }}
                </div>
            </div>
        </div>
    @endif
</div>
