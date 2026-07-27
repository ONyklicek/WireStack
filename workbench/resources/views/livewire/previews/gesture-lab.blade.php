{{-- Gesture lab. The table carries every gesture capability at once; the panel
     beside it reads the state those gestures drive, straight out of the shared
     selection component, so a person can drive the table by hand and watch what
     each gesture does — and the CDP driver can assert on rendered text instead
     of reaching into Alpine internals. --}}
<div data-preview-root class="mx-auto w-full max-w-[1600px] p-6">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Gesture lab</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Selection gestures, record actions, column reordering and the shortcut help, all on one table.
                Focus a row and press <kbd class="rounded border border-gray-300 px-1 text-xs">?</kbd> for the built-in list.
            </p>
        </div>
        <div class="flex gap-2 text-sm">
            <a href="/previews/gesture-lab"
               class="rounded-lg px-3 py-1.5 {{ $variant === 'lab' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}"
               data-testid="lab-variant-lab">40 rows, one page</a>
            <a href="/previews/gesture-lab-paged"
               class="rounded-lg px-3 py-1.5 {{ $variant === 'paged' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}"
               data-testid="lab-variant-paged">20 a page</a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div data-preview-focus class="min-w-0">
            {{ $this->table }}
        </div>

        {{-- Live state read-out.

             It reads the selection component through $root rather than holding
             state of its own: the point is to show what the gestures did, not
             to keep a second copy that could disagree. The active row and the
             announcement come from the same DOM the user is looking at. --}}
        <aside
            class="h-fit rounded-2xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-800"
            x-data="{
                announcement: '—',
                columnOrder: '—',
                get sel() {
                    const root = document.querySelector('[data-selection-root]');
                    return root ? Alpine.$data(root) : null;
                },
                get ctrl() {
                    const tbody = document.querySelector('tbody[x-data^=&quot;wireRecordActions&quot;]');
                    return tbody ? Alpine.$data(tbody) : null;
                },
                init() {
                    // The live region and the header are plain DOM, not reactive
                    // state, so a getter reading them would only be recomputed
                    // when something else on this panel happened to change —
                    // and would sit stale in between. Watch them instead.
                    const read = () => {
                        this.announcement = document.querySelector('[data-testid=selection-live]')?.textContent || '—';
                        this.columnOrder = [...document.querySelectorAll('thead th[data-column]')]
                            .map((th) => th.getAttribute('data-column')).join(', ') || '—';
                    };

                    read();

                    const observer = new MutationObserver(read);
                    observer.observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['data-column'] });
                },
            }"
            data-testid="lab-inspector"
        >
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Live state</h2>

            <dl class="space-y-2">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Mode</dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100" data-testid="lab-mode" x-text="sel?.mode ?? '—'"></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400" x-text="sel?.selectsAll ? 'Exclusions' : 'Selected keys'"></dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100" data-testid="lab-selected-raw" x-text="sel ? sel.selected.length : '—'"></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Selected count</dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100" data-testid="lab-selected-count" x-text="sel?.selectedCount ?? '—'"></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Range anchor</dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100" data-testid="lab-anchor" x-text="sel?.anchorKey ?? '—'"></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Range base</dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100" data-testid="lab-base" x-text="sel?.baseSelection === null ? 'none' : sel.baseSelection.length"></dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Active row</dt>
                    <dd class="font-mono text-gray-900 dark:text-gray-100" data-testid="lab-active" x-text="ctrl?.activeKey ?? '—'"></dd>
                </div>
                <div class="border-t border-gray-100 pt-2 dark:border-gray-700">
                    <dt class="text-gray-500 dark:text-gray-400">Announced</dt>
                    <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-100" data-testid="lab-announced" x-text="announcement"></dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Column order</dt>
                    <dd class="mt-1 font-mono text-xs text-gray-900 dark:text-gray-100" data-testid="lab-columns" x-text="columnOrder"></dd>
                </div>
            </dl>

            <h2 class="mt-5 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Try it</h2>
            <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                <li><strong>Click a checkbox cell</strong> anywhere in it — the whole cell is the target</li>
                <li><strong>Shift+click</strong> a row, then another — a range</li>
                <li><strong>mod+click</strong> — toggle one row anywhere on it</li>
                <li><strong>Drag down the checkbox column</strong> — sweep (adds only)</li>
                <li><strong>Space / Shift+arrows / mod+A</strong> — the same from the keyboard</li>
                <li><strong>Home / End / PageUp / PageDown</strong> — navigate</li>
                <li><strong>Enter, Shift+Enter, Delete, Backspace</strong> — record actions</li>
                <li><strong>Right-click or Shift+F10</strong> — the row menu</li>
                <li><strong>Drag a column header</strong> — the checkbox column must stay put</li>
                <li><strong>Select a page, then "Select all"</strong> — ranges deselect in that mode</li>
            </ul>
        </aside>
    </div>
</div>
