{{-- Sub-row expand/collapse toggle button --}}
{{-- Variables: $recordKey, $isExpanded --}}
{{-- ⌥/Alt-click promotes the click to the master toggle, the shortcut spreadsheet
     and grid users already expect. Plain clicks toggle just this row. --}}
<button
    type="button"
    x-on:click="$event.altKey
        ? $wire.toggleAllRowExpansion()
        : $wire.toggleRowExpansion(@js((string) $recordKey))"
    data-testid="table-row-expand"
    aria-expanded="{{ $isExpanded ? 'true' : 'false' }}"
    aria-label="{{ $isExpanded ? __('wire-table::messages.collapse') : __('wire-table::messages.expand') }}"
    class="inline-flex items-center justify-center w-6 h-6 rounded transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none"
    title="{{ $isExpanded ? __('wire-table::messages.collapse') : __('wire-table::messages.expand') }}"
>
    {!! icon('outline:chevron-right', 'w-4 h-4', 'text-gray-500 dark:text-gray-400 transition-transform duration-200 '.($isExpanded ? 'rotate-90' : '')) !!}
</button>
