<div
    data-preview-root
    class="mx-auto min-h-[760px] w-full max-w-[1280px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="min-h-full rounded-[28px] border border-slate-200 bg-white p-6 sm:p-8">
        <div class="mx-auto max-w-4xl">
            <div class="flex flex-col gap-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-600">wireStack · action modals</p>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-950">Nested modals as a live frame stack</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">
                    Every open modal is a reactive form — parents stay live behind the active one, and a nested modal
                    can write straight back into the form that opened it. Open a configuration, then stack from inside it.
                    <span class="whitespace-nowrap text-slate-400">Esc / backdrop pops the top only.</span>
                </p>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->configs() as $cfg)
                    <div class="group flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-[11px] font-bold text-indigo-500">{{ $cfg['idx'] }}</span>
                                <span class="text-sm font-semibold text-slate-900">{{ $cfg['name'] }}</span>
                            </div>
                            <p class="text-[13px] leading-snug text-slate-500">{{ $cfg['desc'] }}</p>
                        </div>
                        <button
                            type="button"
                            wire:click="mountAction('{{ $cfg['entry'] }}')"
                            data-testid="open-{{ $cfg['entry'] }}"
                            class="inline-flex items-center justify-center gap-1.5 self-start rounded-lg bg-indigo-600 px-3.5 py-2 text-[13px] font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                        >
                            Open action
                            <span aria-hidden="true">&rarr;</span>
                        </button>
                    </div>
                @endforeach
            </div>

            <p class="mt-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] leading-relaxed text-slate-600">
                <span class="font-semibold text-slate-800">Try it:</span>
                open <span class="font-semibold">Create &amp; select</span>, click <span class="font-semibold">+ New customer</span>,
                type a name and hit <span class="font-semibold">Create &amp; select</span> — the nested form closes and the
                parent’s Customer field already shows the new value. Or open <span class="font-semibold">Deep + $setFrame</span>
                and stack three levels; the deepest modal writes the root order.
            </p>
        </div>

        {{-- The whole live stack renders here (active modal + live, click-inert parents). --}}
        <x-wire-actions::modal-host :component="$this" />
    </div>
</div>
