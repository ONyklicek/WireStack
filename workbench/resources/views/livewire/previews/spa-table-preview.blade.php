{{-- Page B of the SPA-navigation fixture: the table every client-side
     controller in the repo has to be present for — selection, record actions,
     the fill handle, the context menu and column reordering. --}}
<div data-preview-root data-spa-page="b" class="mx-auto w-full max-w-[1280px] p-6">
    <div class="mb-4 flex items-baseline justify-between gap-4">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">SPA navigation — page B</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                Selection, record actions, the fill handle, the row context menu and
                column reordering, all at once. Reached cold this is an ordinary table;
                reached through <code>wire:navigate</code> from page A it is the first
                document these bundles have ever been asked to register into.
            </p>
        </div>

        <a
            href="/previews/spa-navigate"
            wire:navigate
            data-testid="spa-back-link"
            class="shrink-0 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            Back to page A
        </a>
    </div>

    <div data-preview-focus class="w-full overflow-hidden rounded-[28px] border border-slate-200 bg-white p-4">
        {{ $this->table }}
    </div>
</div>
