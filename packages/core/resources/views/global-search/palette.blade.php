{{-- The command palette. Rendered by NyonCode\WireCore\GlobalSearch\GlobalSearchPalette.

     Teleported to <body> so the dialog is never clipped by a positioned ancestor
     — the same reason every modal in the framework is. --}}
@php
    $results = $this->results;
    $flat = $this->flatResults();
    $groupLabels = $this->groupLabels();
    $flatIndex = 0;
@endphp
<div>
    <template x-teleport="body">
        <div
                x-data="{
                    get open() { return $wire.open },
                    // Focus once the input is actually on screen.
                    //
                    // `$nextTick(() => $el.focus())` alone loses the race under
                    // load: x-show has not flipped `display` yet, and focus() on
                    // a hidden element is a no-op that reports nothing. The
                    // symptom is a palette that opens and swallows what you
                    // type — measured in a driver sweep, where the focus stayed
                    // on the button that opened it while the dialog was already
                    // visible a moment later.
                    //
                    // Retried per animation frame rather than on a timer: the
                    // frame is exactly when the browser has finished laying the
                    // dialog out, and giving up after ~20 of them keeps a
                    // permanently hidden palette from spinning.
                    focusInput(tries = 20) {
                        const input = $el.querySelector('[data-testid=global-search-input]');

                        if (! input) return;

                        if (input.offsetParent === null && tries > 0) {
                            requestAnimationFrame(() => this.focusInput(tries - 1));

                            return;
                        }

                        input.focus();
                    },
                }"
                x-show="open"
                x-cloak
                @keydown.escape.window="$wire.close()"
                class="fixed inset-0 z-[60] overflow-y-auto"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('wire-core::global-search.title') }}"
                data-testid="global-search"
                style="display: none;"
        >
            <div @click="$wire.close()" class="fixed inset-0 bg-gray-500/70 dark:bg-gray-900/80"></div>

            <div class="relative mx-auto mt-[10vh] w-full max-w-xl px-4">
                <div class="overflow-hidden rounded-2xl bg-white dark:bg-gray-800 shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700">
                    <div class="flex items-center gap-3 border-b border-gray-100 dark:border-gray-700 px-4">
                        {!! icon('outline:magnifying-glass', 'h-5 w-5 text-gray-400') !!}
                        <input
                                type="text"
                                wire:model.live.debounce.250ms="term"
                                x-effect="open && $nextTick(() => focusInput())"
                                @keydown.down.prevent="$wire.moveDown()"
                                @keydown.up.prevent="$wire.moveUp()"
                                @keydown.enter.prevent="$wire.select()"
                                data-testid="global-search-input"
                                placeholder="{{ __('wire-core::global-search.placeholder') }}"
                                class="w-full border-0 bg-transparent py-4 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:ring-0 focus:outline-none"
                        >
                    </div>

                    <div class="max-h-80 overflow-y-auto p-2" data-testid="global-search-results">
                        @forelse($results as $resourceKey => $rows)
                            <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                                {{ $groupLabels[$resourceKey] ?? $resourceKey }}
                            </div>
                            @foreach($rows as $row)
                                @php $isActive = $flatIndex === $this->active; @endphp
                                <button
                                        type="button"
                                        wire:key="gs-{{ $resourceKey }}-{{ $row->recordKey }}"
                                        wire:click="select"
                                        x-on:mouseenter="$wire.set('active', {{ $flatIndex }}, false)"
                                        data-testid="global-search-result"
                                        @if($isActive) data-active="true" @endif
                                        @class([
                                            'flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left',
                                            'bg-primary-50 dark:bg-primary-900/30' => $isActive,
                                            'hover:bg-gray-50 dark:hover:bg-gray-700/50' => ! $isActive,
                                        ])
                                >
                                    @if($row->icon)
                                        {!! icon($row->icon, 'h-4 w-4 shrink-0 text-gray-400') !!}
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-gray-900 dark:text-gray-100">{{ $row->title }}</span>
                                        @if($row->subtitle)
                                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $row->subtitle }}</span>
                                        @endif
                                    </span>
                                </button>
                                @php $flatIndex++; @endphp
                            @endforeach
                        @empty
                            <p class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400" data-testid="global-search-empty">
                                {{ $term === '' ? __('wire-core::global-search.prompt') : __('wire-core::global-search.empty') }}
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
