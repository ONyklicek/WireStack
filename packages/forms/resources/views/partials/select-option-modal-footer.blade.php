{{-- Footer of a Select create/edit option modal: cancel + save buttons. Rendered
     via the Htmlable Modal object's footerView (Rule 5). Expects $mode
     ('create'|'edit'), $cancelAction, $saveAction, $saveLabel in scope (passed as
     footerData). --}}
<div class="flex justify-end gap-2">
    <button
        type="button"
        wire:click="{{ $cancelAction }}" data-testid="select-{{ $mode }}-cancel"
        class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
    >
        {{ __('wire-forms::fields.cancel') }}
    </button>
    <button
        type="button"
        wire:click="{{ $saveAction }}" data-testid="select-{{ $mode }}-save"
        wire:loading.attr="disabled"
        class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 transition-colors duration-150"
    >
        {{ $saveLabel }}
    </button>
</div>
