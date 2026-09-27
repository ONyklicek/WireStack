{{-- Registering a company. Outside any company, so no trail and no tabs: a
     heading, the two fields, and the button. --}}
<div class="mx-auto max-w-xl space-y-6" data-testid="tenants-register" @wireEl('tenants-register')>
    <div class="space-y-1">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ __('wire-module-tenants::messages.register_heading') }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('wire-module-tenants::messages.register_description') }}</p>
    </div>

    <form wire:submit="save" class="space-y-4">
        <x-wire::section>{{ $this->form }}</x-wire::section>

        <x-wire::button type="submit" data-testid="tenants-register-submit">
            {{ __('wire-module-tenants::messages.register_submit') }}
        </x-wire::button>
    </form>
</div>
