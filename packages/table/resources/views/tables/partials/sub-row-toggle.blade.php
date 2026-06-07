{{-- Sub-row expand/collapse toggle button --}}
{{-- Variables: $recordKey, $isExpanded --}}
<button
    type="button"
    wire:click="toggleRowExpansion('{{ $recordKey }}')"
    class="inline-flex items-center justify-center w-6 h-6 rounded transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none"
    title="{{ $isExpanded ? __('wire-table::messages.collapse') : __('wire-table::messages.expand') }}"
>
    <x-wire::icon
        name="outline:chevron-right"
        size="w-4 h-4"
        class="text-gray-500 dark:text-gray-400 transition-transform duration-200 {{ $isExpanded ? 'rotate-90' : '' }}"
    />
</button>
