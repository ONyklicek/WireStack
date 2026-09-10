{{-- Two-factor, as a screen over Fortify.

     Three states, not two, and the middle one is the reason: a secret exists
     the moment the panel is opened, so a user who opened it and closed the tab
     has one and is protected by nothing. A card that showed "on" there would be
     telling them they are safe. --}}
<div data-testid="profile-two-factor" @wireEl('profile-two-factor')>
    <x-wire::section
        :heading="__('wire-module-users::messages.two_factor')"
        :description="__('wire-module-users::messages.two_factor_hint')"
    >
        {{-- A stale confirmation hides the secret and refuses every button, so
             the card says so. Without this the panel looks broken rather than
             careful: the QR simply is not there and nothing explains why. --}}
        @if ($needsPasswordConfirmation)
            <p class="mb-4 text-sm text-amber-700 dark:text-amber-400" data-testid="two-factor-needs-password">
                {{ __('wire-module-users::messages.password_confirmation_required') }}
                @if ($passwordConfirmationUrl)
                    <a href="{{ $passwordConfirmationUrl }}" class="font-medium underline">
                        {{ __('wire-module-users::messages.password_confirmation_link') }}
                    </a>
                @endif
            </p>
        @endif

        <div class="space-y-4">
            <div class="flex items-center gap-2">
                @if ($confirmed)
                    <x-wire::badge color="success" data-testid="two-factor-state">
                        {{ __('wire-module-users::messages.two_factor_on') }}
                    </x-wire::badge>
                @elseif ($pending)
                    <x-wire::badge color="warning" data-testid="two-factor-state">
                        {{ __('wire-module-users::messages.two_factor_pending') }}
                    </x-wire::badge>
                @else
                    <x-wire::badge color="gray" data-testid="two-factor-state">
                        {{ __('wire-module-users::messages.two_factor_off') }}
                    </x-wire::badge>
                @endif
            </div>

            {{-- ── Setting it up ─────────────────────────────────────────── --}}
            @if ($pending)
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('wire-module-users::messages.two_factor_scan') }}
                </p>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                    @if ($qrCode)
                        <div class="inline-block rounded-lg bg-white p-3" data-testid="two-factor-qr" @wireEl('two-factor-qr')>
                            {!! $qrCode !!}
                        </div>
                    @endif

                    @if ($setupKey)
                        {{-- Beside the picture, not instead of it: a desktop
                             authenticator or a locked-down camera is common
                             enough that a setup screen with only a QR code on it
                             is one some people cannot finish. --}}
                        <div class="min-w-0 space-y-1">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ __('wire-module-users::messages.two_factor_setup_key') }}
                            </p>
                            <code
                                class="block break-all rounded-md bg-gray-100 px-3 py-2 font-mono text-sm dark:bg-gray-900"
                                data-testid="two-factor-setup-key" @wireEl('two-factor-setup-key')
                            >{{ $setupKey }}</code>
                        </div>
                    @endif
                </div>

                {{-- The same field the challenge on the way in draws, rather
                     than a second opinion about what a six-digit code looks
                     like: `wire-forms`' OtpInput, bound straight to this
                     component's `$code` (ADR 0037 §6). The label, the error and
                     the numeric keypad come with it. --}}
                <div class="max-w-xs" data-testid="two-factor-code" @wireEl('two-factor-code')>
                    {{ $this->codeField() }}
                </div>
            @endif

            {{-- ── Recovery codes ────────────────────────────────────────── --}}
            @if ($codes !== [])
                <div class="space-y-2" data-testid="two-factor-recovery-codes" @wireEl('two-factor-recovery-codes')>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('wire-module-users::messages.recovery_codes_hint') }}
                    </p>

                    <div class="grid gap-1 rounded-md bg-gray-100 p-3 font-mono text-sm sm:grid-cols-2 dark:bg-gray-900">
                        @foreach ($codes as $recoveryCode)
                            <div>{{ $recoveryCode }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ── The way in and the way out ────────────────────────────── --}}
            <div class="flex flex-wrap items-center gap-2">
                @if ($pending)
                    <x-wire::button color="primary" wire:click="confirm" data-testid="two-factor-confirm">
                        {{ __('wire-module-users::messages.two_factor_activate') }}
                    </x-wire::button>
                @elseif (! $confirmed)
                    <x-wire::button color="primary" wire:click="enable" data-testid="two-factor-enable">
                        {{ __('wire-module-users::messages.two_factor_enable') }}
                    </x-wire::button>
                @endif

                @if ($confirmed)
                    <x-wire::button color="gray" outlined wire:click="toggleRecoveryCodes" data-testid="two-factor-show-codes">
                        {{ __('wire-module-users::messages.recovery_codes_show') }}
                    </x-wire::button>

                    <x-wire::button color="gray" outlined wire:click="regenerateRecoveryCodes" data-testid="two-factor-regenerate">
                        {{ __('wire-module-users::messages.recovery_codes_regenerate') }}
                    </x-wire::button>
                @endif

                {{-- Available from the pending state too, and that is the point:
                     a half-finished setup has to be leavable in both directions
                     or it is a trap. --}}
                @if ($pending || $confirmed)
                    <x-wire::button color="danger" outlined wire:click="disable" data-testid="two-factor-disable">
                        {{ __('wire-module-users::messages.two_factor_disable') }}
                    </x-wire::button>
                @endif
            </div>
        </div>
    </x-wire::section>
</div>
