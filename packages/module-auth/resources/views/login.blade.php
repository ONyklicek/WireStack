{{-- Fortify's login view.

     A plain form posting to Fortify's own route, and that is the design rather
     than a shortcut. Everything that makes signing in hard — the credential
     check, the rate limiter keyed on e-mail and IP, the session regeneration
     that closes fixation, the hand-off to the two-factor challenge — happens in
     the controller behind this URL, by a maintained owner.

     What was missing was only the markup, and it is no longer written here: the
     fields come from wire-forms in native-submit mode (ADR 0036), declared in
     `Forms\AuthForms`, so they are the same schema, the same chrome and the same
     Alpine as the panel's own forms. The reveal toggle works because
     `@wireStackScripts` already put its controller in this document. The browser
     still does the posting.

     The action is the GET route's URL on purpose: Fortify serves both verbs at
     one path and names the POST differently across its major versions, so the
     name that has been stable is the one to use. --}}
@php($forms = app(\NyonCode\WireModuleAuth\Forms\AuthForms::class))

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.sign_in')"
    :heading="__('wire-module-auth::messages.sign_in_heading')"
    :description="__('wire-module-auth::messages.sign_in_description')"
>
    <form method="POST" action="{{ route('login') }}" class="space-y-4" data-testid="auth-login-form" @wireEl('auth-login-form')>
        @csrf

        {{ $forms->login() }}

        {{-- Drawn from the same switch that creates the route: a link to a
             password reset an application never enabled is a 404 it finds out
             about from a user. --}}
        @if (\NyonCode\WireModuleAuth\Support\Screens::canResetPassword())
            <p class="text-right">
                <a
                    href="{{ route('password.request') }}"
                    class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                    data-testid="auth-forgot-link" @wireEl('auth-forgot-link')
                >{{ __('wire-module-auth::messages.forgot_password') }}</a>
            </p>
        @endif

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.sign_in') }}
        </x-wire::button>

        {{-- Passkeys, where Fortify routes them.

             Beside the password form and not inside it: a discoverable
             credential already knows which account it belongs to, so there is
             nothing to type first. `x-cloak` and `x-show="supported"` are what
             keep the button off a browser that cannot do the ceremony — absent
             beats present-and-broken, and the password is on the same screen.

             The controller is wire-core's `wirePasskey` (ADR 0032 §5): the users
             module's profile card draws the other half of the same behaviour and
             neither package may depend on the other. --}}
        @if (\NyonCode\WireModuleAuth\Support\Screens::hasPasskeys())
            @include('wire-core::partials.passkey-assets')

            <div
                x-data="wirePasskey({
                    routes: {
                        options: @js(route('passkey.login-options')),
                        submit: @js(route('passkey.login')),
                    },
                    autofill: true,
                    failedMessage: @js(__('wire-module-auth::messages.passkey_failed')),
                })"
                x-show="supported"
                x-cloak
                class="space-y-2"
                data-testid="auth-passkey" @wireEl('auth-passkey')
            >
                <x-wire::button
                    type="button"
                    color="gray"
                    outlined
                    class="w-full"
                    icon="outline:finger-print"
                    x-on:click="signIn()"
                    x-bind:disabled="busy"
                    data-testid="auth-passkey-button"
                >
                    {{ __('wire-module-auth::messages.passkey_sign_in') }}
                </x-wire::button>

                <p x-show="error" x-text="error" x-cloak class="text-sm text-red-600 dark:text-red-400" data-testid="auth-passkey-error"></p>
            </div>
        @endif

        {{-- The other way in, where there is one. Same rule as the reset link
             above: the switch that draws it is the switch that routed it. --}}
        @if (\NyonCode\WireModuleAuth\Support\Codes::login())
            <p class="text-center text-sm">
                <a href="{{ route('wire-auth.login-code') }}" class="text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-code-link" @wireEl('auth-code-link')>
                    {{ __('wire-module-auth::messages.code_login_link') }}
                </a>
            </p>
        @endif

        @if (\NyonCode\WireModuleAuth\Support\Screens::canRegister())
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('register') }}" class="text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-register-link" @wireEl('auth-register-link')>
                    {{ __('wire-module-auth::messages.register') }}
                </a>
            </p>
        @endif
    </form>
</x-wire-module-auth::screen>
