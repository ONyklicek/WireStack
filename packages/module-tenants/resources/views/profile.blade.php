{{-- The company's own page: its name and slug, and — for its owner — the one
     action in the module that takes a whole company out of reach, behind its
     typed name rather than a yes/no. --}}
<div class="wire-resource-page space-y-4 sm:space-y-6" data-testid="tenants-profile" @wireEl('tenants-profile')>
    @include('wire-panels::pages.partials.header')

    <form wire:submit="save" class="space-y-4">
        <x-wire::section>{{ $this->form }}</x-wire::section>

        @if ($owner)
            <x-wire::button type="submit" data-testid="tenants-profile-save">
                {{ __('wire-module-tenants::messages.save') }}
            </x-wire::button>
        @endif
    </form>

    @if ($owner)
        <x-wire::section data-testid="tenants-delete">
            <div class="space-y-3">
                <h2 class="text-base font-semibold text-red-700 dark:text-red-400">{{ __('wire-module-tenants::messages.delete_heading') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('wire-module-tenants::messages.delete_description', ['company' => $companyName]) }}</p>
                <input
                    type="text"
                    wire:model="confirmation"
                    aria-label="{{ __('wire-module-tenants::messages.delete_confirm_label') }}"
                    placeholder="{{ $companyName }}"
                    data-testid="tenants-delete-confirmation"
                    class="w-full max-w-sm rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"
                >
                <x-wire::button color="danger" wire:click="deleteCompany" data-testid="tenants-delete-submit">
                    {{ __('wire-module-tenants::messages.delete_submit') }}
                </x-wire::button>
            </div>
        </x-wire::section>
    @endif
</div>
