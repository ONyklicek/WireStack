{{-- A suspended (parent) action modal stacked behind the active one.

     Rendered as a dimmed, non-interactive shell so a stacked modal reads as
     "the previous modal is still open behind this one". It carries no form,
     footer, or close controls — only the top (active) modal is interactive; this
     one is resumed into the active slot when the modal above it closes.

     It deliberately paints NO backdrop of its own: the active modal on top
     already draws a single full-viewport scrim over everything beneath it, so
     stacking N of these does not compound into a near-black overlay. Depth is
     conveyed by progressively scaling and lifting each parent (a "stacked cards"
     cue) — `$depthBelowTop` is how many levels this shell sits below the active
     modal (1 = directly beneath it).

     Expects:
       - $modalData:      resolved modal config (heading/description/width/slideOver)
       - $zIndex:         the stacking z-index for this depth (below the active modal)
       - $depthBelowTop:  levels below the active modal (>= 1) --}}
@php
    use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

    $isSlideOver = (bool) ($modalData['slideOver'] ?? false);
    $widthClass = HasModalProperties::getMaxWidthClass($modalData['width'] ?? 'md');

    // Recede each deeper level a little more: smaller and lifted, clamped so a
    // deep stack never scales away to nothing.
    $depth = max(1, (int) ($depthBelowTop ?? 1));
    $scale = max(0.9, 1 - $depth * 0.03);
    $lift = min(24, $depth * 8);
@endphp

<template x-teleport="body">
    <div
        class="fixed inset-0 overflow-hidden pointer-events-none"
        style="z-index: {{ $zIndex }};"
        aria-hidden="true"
        inert
    >
        @if($isSlideOver)
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div
                    class="w-screen {{ $widthClass }} opacity-70"
                    style="transform: translateX({{ $lift }}px);"
                >
                    <div class="flex h-full flex-col bg-white dark:bg-gray-800 shadow-2xl">
                        <div class="px-4 pt-6 sm:px-6">
                            @if(! empty($modalData['heading']))
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $modalData['heading'] }}</h3>
                            @endif
                            @if(! empty($modalData['description']))
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $modalData['description'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div
                    class="relative inline-block w-full transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 text-left align-bottom shadow-xl sm:align-middle opacity-70 {{ $widthClass }}"
                    style="transform: translateY(-{{ $lift }}px) scale({{ $scale }});"
                >
                    <div class="px-4 pt-5 sm:px-6 sm:pt-6 pb-5">
                        @if(! empty($modalData['heading']))
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $modalData['heading'] }}</h3>
                        @endif
                        @if(! empty($modalData['description']))
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $modalData['description'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</template>
