<div
    data-preview-root
    class="mx-auto min-h-[800px] w-full max-w-[1280px] overflow-hidden rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="h-full rounded-[28px] border border-slate-200 bg-white p-8">
        <div
            x-data="{
                fire(event, detail) {
                    window.dispatchEvent(new CustomEvent(event, { detail }));
                },
                burst(event, n, prefix) {
                    for (let i = 1; i <= n; i++) {
                        setTimeout(() => this.fire(event, {
                            type: ['success', 'error', 'warning', 'info'][i % 4],
                            message: prefix + ' ' + i,
                        }), i * 120);
                    }
                },
            }"
            class="space-y-8"
        >
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Toast notifications</h2>
                <p class="mt-1 text-sm text-slate-600">
                    Countdown bar with hover-to-pause, action buttons, a collapsible stack, a max-visible cap,
                    and reduced-motion / screen-reader support. The server buttons route through
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">NotificationManager</code> without passing
                    <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">$this</code>.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                {{-- Basic types (client-side, top-right default container) --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Types &amp; countdown bar</p>
                    <p class="mt-1 text-xs text-slate-500">Top-right · hover any toast to pause it.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" @click="fire('table-notification', { type: 'success', message: 'Saved successfully' })"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500">Success</button>
                        <button type="button" @click="fire('table-notification', { type: 'error', message: 'Something failed' })"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-500">Error</button>
                        <button type="button" @click="fire('table-notification', { type: 'warning', message: 'Double-check this' })"
                            class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-400">Warning</button>
                        <button type="button" @click="fire('table-notification', { type: 'info', message: 'For your information' })"
                            class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500">Info</button>
                    </div>
                </div>

                {{-- Actions + persistent (server-side, through the driver) --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Actions &amp; persistent</p>
                    <p class="mt-1 text-xs text-slate-500">Sent from PHP — the Undo button dispatches back to Livewire.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" wire:click="sendServerToast"
                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Toast with Undo</button>
                        <button type="button" wire:click="sendPersistentToast"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Persistent</button>
                    </div>
                </div>

                {{-- Client-side action toast --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Client-side action</p>
                    <p class="mt-1 text-xs text-slate-500">wireToast with an <code class="text-[11px]">actions</code> option.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button"
                            @click="fire('table-notification', { type: 'success', title: 'Deleted', message: 'Item deleted', actions: [{ label: 'Undo', event: 'demo-undo', color: 'primary', close: true }] })"
                            class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700">Deleted → Undo</button>
                    </div>
                </div>

                {{-- Stack --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Collapsible stack</p>
                    <p class="mt-1 text-xs text-slate-500">Bottom-right · piles up, hover to fan out.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" @click="burst('demo-stack', 5, 'Stacked')"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500">Fire 5 stacked</button>
                    </div>
                </div>

                {{-- Max visible --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900">Max-visible cap</p>
                    <p class="mt-1 text-xs text-slate-500">Bottom-left · caps at 3, overflow into “+N more”.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" @click="burst('demo-max', 8, 'Queued')"
                            class="rounded-lg bg-fuchsia-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-fuchsia-500">Fire 8 queued</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Demo containers (the layout already mounts the default top-right one) --}}
    <x-wire-notifications::toast-container position="bottom-right" event-name="demo-stack" stack :duration="6000" />
    <x-wire-notifications::toast-container position="bottom-left" event-name="demo-max" :max="3" :duration="6000" />
</div>
