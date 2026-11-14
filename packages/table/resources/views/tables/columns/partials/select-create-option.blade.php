{{-- "Create option" at the foot of a SelectColumn's shared list. Runs in the
     scope of the cell that opened the list, so `recordKey` is that row's. --}}
{{-- Variables: $actionName, $label, $testId --}}
<div class="border-t border-gray-200 dark:border-gray-700 p-1">
    <button
        type="button"
        @click="open = false; $wire.openActionModal(String(recordKey), @js($actionName))"
        data-testid="{{ $testId }}"
        class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 dark:hover:text-primary-300 transition-colors duration-150"
    >
        {!! icon('plus', 'w-4 h-4', 'w-4 h-4 shrink-0') !!}
        <span>{{ $label }}</span>
    </button>
</div>
