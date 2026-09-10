{{-- The passkey card.

     Two halves with two owners, and the seam is visible in the markup: the list
     and the delete button are Livewire's, the "add" button is wire-core's
     `wirePasskey` controller talking to `laravel/passkeys`' own routes. A key is
     created by the browser, in a dialog nothing here can style or rush, so the
     card asks for the list again afterwards rather than pretending to know what
     was added. --}}
<div data-testid="profile-passkeys" @wireEl('profile-passkeys')>
    <x-wire::section
        :heading="__('wire-module-users::messages.passkeys')"
        :description="__('wire-module-users::messages.passkeys_hint')"
    >
        @include('wire-core::partials.passkey-assets')

        {{-- A stale confirmation hides the secret and refuses every button, so
             the card says so. Without this the panel looks broken rather than
             careful: the QR simply is not there and nothing explains why. --}}
        @if ($needsPasswordConfirmation)
            <p class="mb-4 text-sm text-amber-700 dark:text-amber-400" data-testid="passkeys-needs-password">
                {{ __('wire-module-users::messages.password_confirmation_required') }}
                @if ($passwordConfirmationUrl)
                    <a href="{{ $passwordConfirmationUrl }}" class="font-medium underline">
                        {{ __('wire-module-users::messages.password_confirmation_link') }}
                    </a>
                @endif
            </p>
        @endif

        <div class="space-y-4">
            {{-- The keys themselves. "None yet" is a state worth drawing: a
                 person who has just switched this on has to be able to tell an
                 empty list from a card that failed to load. --}}
            @if ($passkeys->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400" data-testid="passkeys-empty">
                    {{ __('wire-module-users::messages.passkeys_empty') }}
                </p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700" data-testid="passkeys-list">
                    @foreach ($passkeys as $passkey)
                        <li class="flex items-center justify-between gap-3 py-2" wire:key="passkey-{{ $passkey->getKey() }}">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $passkey->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('wire-module-users::messages.passkey_added_on', ['date' => $passkey->created_at?->isoFormat('LLL')]) }}
                                </p>
                            </div>

                            <x-wire::button
                                color="danger"
                                outlined
                                size="sm"
                                wire:click="forget('{{ $passkey->getKey() }}')"
                                wire:confirm="{{ __('wire-module-users::messages.passkey_remove_confirm') }}"
                                data-testid="passkey-remove"
                            >
                                {{ __('wire-module-users::messages.passkey_remove') }}
                            </x-wire::button>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Adding one. `usable` is the model's trait rather than the
                 feature: with the feature on and the trait missing, every route
                 answers and the key belongs to nobody — so the card says which
                 two lines are missing instead of offering a button that works
                 and achieves nothing. --}}
            @if ($usable)
                <div
                    x-data="wirePasskey({
                        routes: {
                            options: @js(route('passkey.registration-options')),
                            submit: @js(route('passkey.store')),
                        },
                        on: 'wire-passkey-registered',
                        defaultName: @js(__('wire-module-users::messages.passkey_default_name')),
                        failedMessage: @js(__('wire-module-users::messages.passkey_failed')),
                    })"
                    x-show="supported"
                    x-cloak
                    class="space-y-2"
                    data-testid="passkeys-add"
                >
                    <div class="flex items-end gap-2">
                        <div class="max-w-xs flex-1 space-y-1">
                            <label for="passkey-name" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                                {{ __('wire-module-users::messages.passkey_name') }}
                            </label>
                            <input
                                id="passkey-name"
                                type="text"
                                wire:model="name"
                                x-ref="name"
                                :disabled="busy"
                                data-testid="passkey-name"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                        </div>

                        <x-wire::button
                            type="button"
                            icon="outline:finger-print"
                            x-on:click="register($refs.name.value)"
                            x-bind:disabled="busy"
                            data-testid="passkey-add"
                        >
                            {{ __('wire-module-users::messages.passkey_add') }}
                        </x-wire::button>
                    </div>

                    <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600 dark:text-red-400" data-testid="passkey-error"></p>
                </div>

                {{-- The browser, not the application, is what is missing here. --}}
                <p x-data="wirePasskey({})" x-show="! supported" x-cloak class="text-sm text-gray-500 dark:text-gray-400" data-testid="passkeys-unsupported">
                    {{ __('wire-module-users::messages.passkeys_unsupported') }}
                </p>
            @else
                <p class="text-sm text-amber-700 dark:text-amber-300" data-testid="passkeys-untraited">
                    {{ __('wire-module-users::messages.passkeys_trait_missing') }}
                </p>
            @endif
        </div>
    </x-wire::section>
</div>
