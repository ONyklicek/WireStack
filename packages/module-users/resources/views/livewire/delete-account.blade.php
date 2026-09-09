{{-- Closing the account. Two gates for something with no undo: a dialog that
     has to be opened deliberately, and the account's own password typed into
     it. --}}
<div data-testid="profile-delete-account" @wireEl('profile-delete-account')>
    <x-wire::section
        :heading="__('wire-module-users::messages.delete_account')"
        :description="__('wire-module-users::messages.delete_account_hint')"
    >
        <x-wire::button color="danger" wire:click="confirm" data-testid="delete-account-open">
            {{ __('wire-module-users::messages.delete_account') }}
        </x-wire::button>
    </x-wire::section>

    <x-wire::modal
        wire:model="confirming"
        :heading="__('wire-module-users::messages.delete_account')"
        :description="__('wire-module-users::messages.delete_account_confirm')"
        icon="exclamation-triangle"
        icon-color="danger"
        width="md"
        close-action="cancel"
    >
        {{ $this->form }}

        <x-slot:footer>
            <div class="flex items-center justify-end gap-2">
                <x-wire::button color="gray" outlined wire:click="cancel">
                    {{ __('wire-module-users::messages.cancel') }}
                </x-wire::button>

                <x-wire::button color="danger" wire:click="delete" data-testid="delete-account-confirm">
                    {{ __('wire-module-users::messages.delete_account_forever') }}
                </x-wire::button>
            </div>
        </x-slot:footer>
    </x-wire::modal>
</div>
