{{-- Confirmation Dialog Component --}}
{{-- Teleported to <body> so a transformed/overflow ancestor can never break the
     fixed overlay's viewport positioning. (Floating UI N/A — confirmations are
     centered on the viewport, not anchored to a trigger.) --}}
<template x-teleport="body">
<div
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-show="show"
    x-cloak
    style="display: none;"
    @if($closeOnEscape) x-on:keydown.escape.window="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}" @endif
    class="fixed inset-0 z-50 overflow-y-auto"
    @if($id) id="{{ $id }}" @endif
    aria-labelledby="confirmation-modal-title"
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

        {{-- Dialog Panel --}}
        <div
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            @class([
                'relative inline-block transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 px-4 pt-5 pb-4 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:p-6 sm:align-middle',
                $widthClass(),
            ])
        >
            <div class="sm:flex sm:items-start">
                {{-- Icon --}}
                @if($icon)
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full {{ $iconBgClass() }} sm:mx-0 sm:h-10 sm:w-10">
                        <x-wire::icon :name="$icon" :class="'h-6 w-6 ' . $iconColorClass()" />
                    </div>
                @endif

                <div class="mt-3 text-center sm:mt-0 {{ $icon ? 'sm:ml-4' : '' }} sm:text-left flex-1">
                    @if($heading)
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="confirmation-modal-title">
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

                    {{-- Extra body content --}}
                    @if($slot->isNotEmpty())
                        <div class="mt-4">
                            {{ $slot }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Buttons --}}
            <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                {{-- Submit button (hidden for informative dialogs) --}}
                @unless($isInformative)
                    <button
                        type="button"
                        @if($attributes->wire('click')->value()) {{ $attributes->wire('click') }} @endif
                        data-testid="confirmation-confirm"
                        class="{{ $submitButtonClasses() }}"
                    >
                        {{ $submitLabel }}
                    </button>
                @endunless

                {{-- Cancel / Close button --}}
                <button
                    type="button"
                    @click="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}"
                    data-testid="confirmation-cancel"
                    class="mt-3 inline-flex w-full justify-center rounded-xl border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:mt-0 sm:w-auto"
                >
                    {{ $cancelLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
</template>
