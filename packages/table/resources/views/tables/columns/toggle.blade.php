@php
    /** @var bool $state */
    /** @var string $recordKey */
    /** @var string $columnName */
    /** @var string $onColor */
    /** @var string $offColor */
    /** @var bool $disabled */
@endphp

<button
    type="button"
    @unless($disabled)
        wire:click="toggleColumnValue('{{ $recordKey }}', '{{ $columnName }}')"
    @endunless
    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 {{ $state ? $onColor : $offColor }} {{ $disabled ? 'opacity-50 cursor-not-allowed' : '' }}"
    role="switch"
    aria-checked="{{ $state ? 'true' : 'false' }}"
>
    <span
        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $state ? 'translate-x-5' : 'translate-x-0' }}"
    ></span>
</button>
