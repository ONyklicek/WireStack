{{-- Page A of the SPA-navigation fixture.

     Deliberately empty of every surface that ships a client-side controller:
     no table, no dropdown, no sortable, no action modal. Only Livewire and
     Alpine boot here — which is the whole point, because `alpine:init` fires
     on THIS document, once, before any of the package bundles exist.

     The link below is the only wire:navigate in the repository; the driver
     workbench/scripts/verify-spa-navigate.mjs clicks it. --}}
<div data-preview-root data-spa-page="a" class="mx-auto w-full max-w-[900px] p-6">
    <div data-preview-focus class="rounded-[28px] border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-lg font-semibold text-slate-900">SPA navigation — page A</h1>

        <p class="mt-2 max-w-2xl text-sm text-slate-600">
            A page with no table, no dropdown and no sortable surface, so none of the
            packages' pre-bundled Alpine controllers are in this document. Alpine has
            already started here by the time you follow the link.
        </p>

        {{-- Proof that Livewire is live on this page before we navigate away. --}}
        <div class="mt-6 flex items-center gap-3">
            <button
                type="button"
                wire:click="ping"
                data-testid="spa-ping"
                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Ping the server
            </button>
            <span data-testid="spa-ping-count" class="text-sm text-slate-500">{{ $pings }}</span>
        </div>

        <div class="mt-8">
            <a
                href="/previews/spa-navigate-table"
                wire:navigate
                data-testid="spa-navigate-link"
                class="inline-flex items-center rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-500"
            >
                Go to the table (wire:navigate)
            </a>

            <a
                href="/previews/spa-navigate-table"
                data-testid="spa-hardlink"
                class="ml-3 inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Go to the table (full page load)
            </a>
        </div>
    </div>
</div>
