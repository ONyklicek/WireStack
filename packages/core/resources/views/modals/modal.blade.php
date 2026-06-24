{{-- General Modal Component --}}
{{-- Teleported to <body> so a transformed/overflow ancestor can never break the
     fixed overlay's viewport positioning. (Floating UI N/A — modals are centered,
     not anchored to a trigger.) --}}
<template x-teleport="body">
<div
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-show="show"
    x-cloak
    @if($closeOnEscape) x-on:keydown.escape.window="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}" @endif
    class="fixed inset-0 z-50 overflow-y-auto"
    @if($id) id="{{ $id }}" @endif
    aria-labelledby="modal-title"
    role="dialog"
    aria-modal="true"
>
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        {{-- Backdrop --}}
        <div
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80 backdrop-blur-sm transition-opacity"
            @if($closeOnClickAway) @click="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}" @endif
        ></div>

        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        {{-- Modal Panel --}}
        <div
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            @class([
                'relative inline-block w-full transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 text-left align-bottom shadow-xl transition-all sm:my-8 sm:align-middle',
                $widthClass(),
                'max-h-screen sm:max-h-none' => $fullScreenOnMobile,
            ])
            @if($maxHeight) style="max-height: {{ $maxHeight }}" @endif
        >
            {{-- Header --}}
            @if($heading || $icon || isset($header))
                <div @class([
                    'px-4 pt-5 sm:px-6 sm:pt-6',
                    'sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 pb-4' => $stickyHeader,
                ])>
                    <div class="sm:flex sm:items-start">
                        @if($icon)
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full {{ $iconBgClass() }} sm:mx-0 sm:h-10 sm:w-10">
                                <x-wire::icon :name="$icon" :class="'h-6 w-6 ' . $iconColorClass()" />
                            </div>
                        @endif

                        <div class="mt-3 text-center sm:mt-0 {{ $icon ? 'sm:ml-4' : '' }} sm:text-left flex-1">
                            @if($heading)
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">
                                    {{ $heading }}
                                </h3>
                            @endif

                            @if($description)
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $description }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Close button --}}
                        <button
                            type="button"
                            @click="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}"
                            class="ml-auto -mr-1.5 rounded-lg p-1.5 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500"
                        >
                            <span class="sr-only">{{ __('Close') }}</span>
                            <x-wire::icon name="outline:x-mark" size="h-5 w-5" />
                        </button>
                    </div>

                    @isset($header)
                        {{ $header }}
                    @endisset
                </div>
            @endif

            {{-- Body --}}
            <div @class([
                'px-4 pb-4 sm:px-6 sm:pb-6',
                'pt-4' => $heading || $icon,
                'pt-5 sm:pt-6' => !$heading && !$icon,
                'overflow-y-auto' => $maxHeight,
            ])>
                {{ $slot }}
            </div>

            {{-- Footer --}}
            @isset($footer)
                <div @class([
                    'px-4 pb-4 sm:px-6 sm:pb-6',
                    'sticky bottom-0 z-10 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 pt-4' => $stickyFooter,
                ])>
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
</template>
