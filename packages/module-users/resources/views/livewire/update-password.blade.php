{{-- The password card. Its own form and its own button, because it asks a
     question the profile form does not: prove you are the person whose password
     this is. --}}
<div data-testid="profile-password" @wireEl('profile-password')>
    <x-wire::section
        :heading="__('wire-module-users::messages.update_password')"
        :description="__('wire-module-users::messages.update_password_hint')"
    >
        <form wire:submit="save" class="space-y-4">
            {{ $this->form }}

            <div class="flex items-center gap-2">
                <x-wire::button type="submit" data-testid="password-save">
                    {{ __('wire-panels::messages.save') }}
                </x-wire::button>
            </div>
        </form>
    </x-wire::section>
</div>
