<div
    data-preview-root
    class="mx-auto flex w-full max-w-[1280px] items-stretch rounded-[36px] border border-slate-200 bg-[linear-gradient(180deg,_#f8fbff_0%,_#eef4fb_100%)] p-5 shadow-[0_30px_80px_rgba(148,163,184,0.24)]"
>
    <div data-preview-focus class="w-full overflow-hidden rounded-[28px] border border-slate-200 bg-white p-4">
        {{-- Keyboard-navigation legend: the gestures this variant exists to show
             are invisible in a screenshot, so the preview names them. --}}
        @if($variant === 'record-actions-keyboard')
            @php
                $keyHints = [
                    ['↑ / ↓', 'move the active row'],
                    ['Enter', 'primary action (Open)'],
                    ['Shift + Enter', 'secondary action (Preview)'],
                    ['Delete', 'onKey() action (Archive)'],
                    ['Space', 'select the active row'],
                    ['Shift + ↑ / ↓', 'extend the selection'],
                    ['⌘ / Ctrl + A', 'select the page'],
                    ['Menu / right-click', 'row context menu'],
                    ['Click', 'moves the active row too'],
                ];
            @endphp
            <div
                data-testid="keyboard-legend"
                class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Click a row (or press Tab) to give the grid focus, then:
                </p>
                <div class="flex flex-wrap gap-x-5 gap-y-1.5">
                    @foreach($keyHints as [$keys, $does])
                        <span class="flex items-center gap-1.5 text-xs text-slate-600">
                            <kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-[11px] text-slate-700 shadow-sm">{{ $keys }}</kbd>
                            {{ $does }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="{{ $variant === 'selection' ? 'origin-top-left scale-[1]' : '' }}">
            {{ $this->table }}
        </div>
    </div>
</div>
