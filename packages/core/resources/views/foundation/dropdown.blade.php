<div
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    class="relative inline-block text-left"
    {{ $attributes }}
>
    <div x-on:click="open = !open">
        {{ $trigger ?? '' }}
    </div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-cloak
        @class([
            $width . ' absolute z-50 mt-2 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10',
            'right-0' => $position === 'bottom-end',
            'left-0' => $position === 'bottom-start',
        ])
    >
        <div class="py-1">
            {{ $slot }}
        </div>
    </div>
</div>
