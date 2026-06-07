{{-- ToggleColumn cell --}}
@php
    /** @var bool $state */
    /** @var string $recordKey */
    /** @var string $columnName */
    /** @var bool $disabled */
    /** @var string $onColorClass canonical "on" track fill from HasColor::getSolidBgClass */

    $bgColor = $state ? $onColorClass : 'bg-gray-200 dark:bg-gray-700';
    $cursorClass = $disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer';
    $translateClass = $state ? 'translate-x-5' : 'translate-x-0';
@endphp

<button
    type="button"
    @unless($disabled)
        wire:click="updateTableCell('{{ $recordKey }}', '{{ $columnName }}', {{ $state ? 'false' : 'true' }})"
    @endunless
    class="relative inline-flex h-6 w-11 flex-shrink-0 {{ $cursorClass }} rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 {{ $bgColor }}"
    role="switch"
    aria-checked="{{ $state ? 'true' : 'false' }}"
>
    <span class="sr-only">Toggle</span>
    <span
        aria-hidden="true"
        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $translateClass }}"
    ></span>
</button>
