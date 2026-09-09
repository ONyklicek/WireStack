{{-- The mark at the top of the menu, from NyonCode\WireAdmin\View\Brand.

     Both forms are in the document and CSS picks between them. Rendering only
     the one the current state needs would mean re-rendering the header every
     time the rail is toggled, and the logo would pop in a frame after the rest
     of the menu — the one place on the page where that is unmissable.

     **Which is exactly what happened while the picking was Alpine's.** `x-show`
     runs after the first paint, so a collapsed menu drew the *wordmark*, clipped
     to 31 pixels by the header's own `overflow-hidden`, and replaced it with the
     square one frame later. Every load, every `wire:navigate`, in the corner the
     comment above already said you cannot miss. The state is `data-rail` on
     `<html>` now, stamped before the body is parsed — see
     `partials/rail.blade.php` — so the corner is right in the first frame and
     this file names no store at all.

     `data-rail-row` is the same attribute the menu rows use, and for the same
     reason: in the rail it centres its element and drops the inline padding. It
     needs `flex-1` beside it, because centring a 32-pixel anchor inside itself
     leaves it wherever the header put it — which is what left the logo hard
     against the left edge while every icon below it sat centred, 16 pixels
     further in. --}}
<a
    href="{{ $url() }}"
    wire:navigate
    data-testid="admin-brand-mark" @wireEl('admin-brand-mark')
    data-rail-row
    class="flex h-16 min-w-0 flex-1 items-center gap-2.5 overflow-hidden px-4"
>
    {{-- The rail's square. Shown only when the menu is narrow, and only on a
         desktop — a phone drawer is always wide, so the rail state must not
         reach it, which is why `[data-rail-only]` is hidden outright and
         revealed only inside the desktop media query. --}}
    <span data-rail-only class="shrink-0">
        @if ($mark())
            <img src="{{ $mark() }}" alt="{{ $name() }}" class="h-8 w-8 rounded-lg object-contain">
        @else
            <span class="bg-primary-600 flex h-8 w-8 items-center justify-center rounded-lg text-sm font-semibold text-white">
                {{ $initial() }}
            </span>
        @endif
    </span>

    {{-- The wide form: a logo if one is configured, otherwise the same square
         beside the name, which is a brand rather than an empty corner. --}}
    <span data-rail-hide class="flex min-w-0 items-center gap-2.5">
        @if ($logo() || $logoDark())
            @if ($logo())
                <img
                    src="{{ $logo() }}"
                    alt="{{ $name() }}"
                    style="height: {{ $height() }}px"
                    @class(['w-auto object-contain', 'dark:hidden' => (bool) $logoDark()])
                >
            @endif

            @if ($logoDark())
                <img
                    src="{{ $logoDark() }}"
                    alt="{{ $name() }}"
                    style="height: {{ $height() }}px"
                    @class(['w-auto object-contain', 'hidden dark:block' => (bool) $logo()])
                >
            @endif
        @else
            <span class="bg-primary-600 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-semibold text-white">
                {{ $initial() }}
            </span>
            <span class="truncate text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $name() }}</span>
        @endif
    </span>
</a>
