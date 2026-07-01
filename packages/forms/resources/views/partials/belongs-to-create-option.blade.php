{{-- Create-option footer for a searchable BelongsToSelect, rendered inside the
     shared combobox panel via its $panelFooter slot.
     Variables: $field (BelongsToSelect with a create-option form). --}}
<div class="border-t border-gray-200 dark:border-gray-600 p-2">
    <button
        type="button"
        wire:click="mountAction('{{ $field->getName() }}_create_option')"
        class="w-full px-3 py-2 text-left text-sm text-primary-600 dark:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors duration-150"
    >
        + {{ __('Create new') }}
    </button>
</div>
