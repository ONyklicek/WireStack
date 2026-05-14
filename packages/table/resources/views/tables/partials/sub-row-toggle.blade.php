{{-- Sub-row expand/collapse toggle button --}}
{{-- Variables: $recordKey, $isExpanded --}}
<button
    type="button"
    wire:click="toggleRowExpansion('{{ $recordKey }}')"
    class="inline-flex items-center justify-center w-6 h-6 rounded transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none"
    title="{{ $isExpanded ? __('wire-table::messages.collapse') : __('wire-table::messages.expand') }}"
>
    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform duration-200 {{ $isExpanded ? 'rotate-90' : '' }}"
         fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
</button>
