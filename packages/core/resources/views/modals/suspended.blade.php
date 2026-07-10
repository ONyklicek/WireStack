{{-- A suspended (parent) action modal stacked behind the active one.

     Rendered as a dimmed, non-interactive shell so a stacked modal reads as
     "the previous modal is still open behind this one". It carries no form,
     footer, or close controls — only the top (active) modal is interactive; this
     one is resumed into the active slot when the modal above it closes.

     Expects:
       - $modalData: resolved modal config (heading/description/width/slideOver)
       - $zIndex:    the stacking z-index for this depth (lower than the active) --}}
@php
    use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

    $isSlideOver = (bool) ($modalData['slideOver'] ?? false);
    $widthClass = HasModalProperties::getMaxWidthClass($modalData['width'] ?? 'md');
@endphp

<template x-teleport="body">
    <div
        class="fixed inset-0 overflow-hidden pointer-events-none"
        style="z-index: {{ $zIndex }};"
        aria-hidden="true"
    >
        {{-- Backdrop (stacks with the active modal's backdrop for a layered depth cue) --}}
        <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 backdrop-blur-sm"></div>

        @if($isSlideOver)
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="w-screen {{ $widthClass }} translate-x-2 opacity-60">
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
                <div class="relative inline-block w-full transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 text-left align-bottom shadow-xl sm:align-middle scale-95 opacity-60 {{ $widthClass }}">
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
