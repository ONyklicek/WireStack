{{-- Slide-Over Panel Component --}}
{{-- Teleported to <body> so a transformed/overflow ancestor can never break the
     fixed overlay's viewport positioning. (Floating UI N/A — slide-overs are
     edge-pinned to the viewport, not anchored to a trigger.) --}}
@php
    // wire:model comes from the <x-wire-modals::slide-over> tag's attribute bag
    // (consumer path) or the Htmlable SlideOver object, which passes $wireModel
    // (Rule 5). isset()/?? keep $attributes untouched off the component path.
    $modelBinding = $wireModel ?? (isset($attributes) ? $attributes->wire('model') : null);
@endphp
<template x-teleport="body" wire:key="wire-modal-slideover">
<div
    x-data="{ show: @entangle($modelBinding) }"
    x-show="show"
    x-cloak
    style="display: none;@if($zIndex !== null) z-index: {{ $zIndex }};@endif"
    @if($closeOnEscape) x-on:keydown.escape.window="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}" @endif
    class="fixed inset-0 z-50 overflow-hidden"
    @if($id) id="{{ $id }}" @endif
    aria-labelledby="slide-over-title"
    role="dialog"
    aria-modal="true"
>
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

    <div class="fixed {{ $style->positionClasses() }} flex max-w-full">
        {{-- Panel --}}
        <div
            x-show="show"
            x-transition:enter="transform transition ease-in-out duration-300"
            x-transition:enter-start="{{ $style->translateEnterStart() }}"
            x-transition:enter-end="{{ $style->translateEnterEnd() }}"
            x-transition:leave="transform transition ease-in-out duration-300"
            x-transition:leave-start="{{ $style->translateLeaveStart() }}"
            x-transition:leave-end="{{ $style->translateLeaveEnd() }}"
            class="{{ $style->widthWrapperClasses() }} {{ $style->widthClass() }}"
        >
            <div class="flex flex-col bg-white dark:bg-gray-800 shadow-xl {{ $style->panelClasses() }}">
                {{-- Header --}}
                <div @class([
                    'px-4 py-6 sm:px-6',
                    'sticky top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700' => $stickyHeader,
                ])>
                    <div class="flex items-start justify-between">
                        <div>
                            @if($heading)
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white" id="slide-over-title">
                                    {{ $heading }}
                                </h2>
                            @endif

                            @if($description)
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $description }}
                                </p>
                            @endif
                        </div>

                        {{-- Close button --}}
                        <div class="ml-3 flex h-7 items-center">
                            {{-- Negative margin grows the tap area to ~36px without
                                 shifting the icon's visual position. --}}
                            <button
                                type="button"
                                @click="show = false; {{ $closeAction ? "\$wire.{$closeAction}()" : '' }}"
                                data-testid="slide-over-close"
                                aria-label="{{ __('Close') }}"
                                class="-m-1.5 rounded-md p-1.5 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 touch-manipulation"
                            >
                                <span class="sr-only">{{ __('Close') }}</span>
                                {!! icon('outline:x-mark', 'h-6 w-6') !!}
                            </button>
                        </div>
                    </div>

                    @isset($header)
                        <div class="mt-4">
                            {{ $header }}
                        </div>
                    @endisset
                </div>

                {{-- Body --}}
                {{-- overscroll-contain stops the scroll chaining to the page behind
                     once the body reaches its top/bottom edge. --}}
                <div @class([
                    'relative flex-1 px-4 sm:px-6 py-4 overflow-y-auto overscroll-contain',
                ])
                    @if($maxHeight) style="max-height: {{ $maxHeight }}" @endif
                >
                    {{-- Body: a component slot (consumer tag), an @include'd partial
                         ($bodyView), or a pre-rendered Htmlable ($body) from the
                         SlideOver object (Rule 5). --}}
                    @if(isset($bodyView))
                        @include($bodyView, $bodyData ?? [])
                    @elseif(! empty($body))
                        {!! $body !!}
                    @else
                        {{ $slot ?? '' }}
                    @endif
                </div>

                {{-- Footer --}}
                @if(isset($footerView) || isset($footer))
                    <div @class([
                        'px-4 py-4 sm:px-6 border-t border-gray-200 dark:border-gray-700',
                        'sticky bottom-0 z-10 bg-white dark:bg-gray-800' => $stickyFooter,
                    ])>
                        @isset($footerView)
                            @include($footerView, $footerData ?? [])
                        @else
                            {!! $footer !!}
                        @endisset
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</template>
