{{-- Fortify's two-factor challenge.

     The screen between a correct password and the panel. Fortify holds the
     half-authenticated session, verifies the TOTP code inside its window,
     consumes a recovery code when one is used and rate-limits both; this is the
     input and the toggle between them.

     The toggle is Alpine rather than two routes, because they are one form
     posting to one URL with a different field — and a `x-ref` focused after the
     switch, because a code input the user has to click into is a second step
     that the earlier version had. --}}
<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.two_factor_heading')"
    :heading="__('wire-module-auth::messages.two_factor_heading')"
>
    <div
        x-data="{
            recovery: false,

            /*
             * Switch, then put the caret in the field that just appeared.
             *
             * Two things go wrong here and neither reports anything. Hiding the
             * field that currently has focus drops focus to <body>, and
             * `focus()` on a field the browser has not laid out yet is a no-op
             * — so a switch that focused on the next tick worked in one
             * direction and silently lost the caret in the other. One frame
             * later, both are true.
             */
            toggle() {
                this.recovery = ! this.recovery;

                const id = this.recovery ? 'recovery_code' : 'code';

                this.$nextTick(() => requestAnimationFrame(() => document.getElementById(id)?.focus()));
            },
        }"
        data-testid="auth-two-factor" @wireEl('auth-two-factor')
    >
        <p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
            <span x-show="! recovery">{{ __('wire-module-auth::messages.two_factor_description') }}</span>
            <span x-show="recovery" x-cloak>{{ __('wire-module-auth::messages.two_factor_recovery_description') }}</span>
        </p>

        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4" data-testid="auth-two-factor-form" @wireEl('auth-two-factor-form')>
            @csrf

            <div x-show="! recovery">
                @include('wire-module-auth::partials.field', [
                    'name' => 'code',
                    'label' => __('wire-module-auth::messages.code'),
                    'autocomplete' => 'one-time-code',
                    'inputmode' => 'numeric',
                    'required' => false,
                    'autofocus' => true,
                ])
            </div>

            <div x-show="recovery" x-cloak>
                @include('wire-module-auth::partials.field', [
                    'name' => 'recovery_code',
                    'label' => __('wire-module-auth::messages.recovery_code'),
                    'autocomplete' => 'one-time-code',
                    'required' => false,
                    'id' => 'recovery_code',
                ])
            </div>

            <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
                {{ __('wire-module-auth::messages.sign_in') }}
            </x-wire::button>

            <p class="text-center">
                <button
                    type="button"
                    x-on:click="toggle()"
                    class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                    data-testid="auth-two-factor-toggle" @wireEl('auth-two-factor-toggle')
                >
                    <span x-show="! recovery">{{ __('wire-module-auth::messages.use_recovery_code') }}</span>
                    <span x-show="recovery" x-cloak>{{ __('wire-module-auth::messages.use_authentication_code') }}</span>
                </button>
            </p>
        </form>
    </div>
</x-wire-module-auth::screen>
