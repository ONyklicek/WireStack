{{-- Create / edit option modals for a Select-family combobox, rendered only
     while mounted (one open at a time, keyed by state path — see
     InteractsWithSelectCreation). Shared by the base Select view and
     relationship variants (BelongsToSelect) so the modal flow has one owner.

     Expected variables:
       $field     Select   the field owning the option forms
       $livewire  mixed    the bound Livewire host (non-null when included) --}}
@php
    $isCreateModalMounted = $field->hasCreateOptionForm()
        && data_get($livewire, 'mountedCreateOptionSelect') === $field->getStatePath();
    $isEditModalMounted = $field->hasEditOptionForm() && ! $field->isMultiple()
        && data_get($livewire, 'mountedEditOptionSelect') === $field->getStatePath();
@endphp

@if($isCreateModalMounted)
    <x-wire::modal
        wire:model="mountedCreateOptionSelect"
        :heading="$field->getCreateOptionModalHeading()"
        width="md"
        closeAction="unmountCreateOption"
    >
        <div class="space-y-4" wire:key="create-option-{{ $field->getId() }}">
            {{ $field->getCreateOptionForm($livewire) }}
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="unmountCreateOption" data-testid="select-create-cancel"
                    class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                >
                    {{ __('wire-forms::fields.cancel') }}
                </button>
                <button
                    type="button"
                    wire:click="createSelectOption" data-testid="select-create-save"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 transition-colors duration-150"
                >
                    {{ __('wire-forms::fields.create') }}
                </button>
            </div>
        </x-slot:footer>
    </x-wire::modal>
@endif

@if($isEditModalMounted)
    <x-wire::modal
        wire:model="mountedEditOptionSelect"
        :heading="$field->getEditOptionModalHeading()"
        width="md"
        closeAction="unmountEditOption"
    >
        <div class="space-y-4" wire:key="edit-option-{{ $field->getId() }}">
            {{ $field->getEditOptionForm($livewire) }}
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="unmountEditOption" data-testid="select-edit-cancel"
                    class="inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150"
                >
                    {{ __('wire-forms::fields.cancel') }}
                </button>
                <button
                    type="button"
                    wire:click="updateSelectOption" data-testid="select-edit-save"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center rounded-md border border-transparent bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 transition-colors duration-150"
                >
                    {{ __('wire-forms::fields.save') }}
                </button>
            </div>
        </x-slot:footer>
    </x-wire::modal>
@endif
