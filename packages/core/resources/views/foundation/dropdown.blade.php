@include('wire-core::partials.floating-assets')

<div
    x-data="wireDropdown({ placement: '{{ $position }}' })"
    @keydown.escape.window="close()"
    class="relative inline-block text-left"
    {{ $attributes }}
>
    <div x-ref="trigger" x-on:click="toggle()">
        {{ $trigger ?? '' }}
    </div>

    {{-- Teleported to <body> + positioned by Floating UI so the menu floats above
         any overflow/stacking context instead of being clipped. --}}
    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="open"
            @click.outside="close()"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-cloak
            @class([
                $width . ' absolute top-0 left-0 z-50 rounded-lg bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10',
                'origin-top-right' => $position === 'bottom-end',
                'origin-top-left' => $position === 'bottom-start',
            ])
        >
            <div class="py-1">
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
