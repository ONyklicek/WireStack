{{-- Signing in with a code, screen one of two: the address.

     The reply to this form is the same whether or not the address belongs to an
     account — it always lands on the challenge — for the reason Fortify's
     forgot-password screen gives the same reply either way: a screen that says
     "no such account" is an account enumerator, and this one would be a faster
     enumerator than that one because nothing has to be typed twice.

     Routed only where `codes.login` is on, so nothing here asks whether the flow
     exists: a screen for a switch that is off is never reached. --}}

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.code_login_heading')"
    :heading="__('wire-module-auth::messages.code_login_heading')"
    :description="__('wire-module-auth::messages.code_login_description')"
>
    <form method="POST" action="{{ route('wire-auth.login-code.store') }}" class="space-y-4" data-testid="auth-login-code-form" @wireEl('auth-login-code-form')>
        @csrf

        {{ $forms->loginCode() }}

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.code_send') }}
        </x-wire::button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-back-link" @wireEl('auth-back-link')>
                {{ __('wire-module-auth::messages.back_to_sign_in') }}
            </a>
        </p>
    </form>
</x-wire-module-auth::screen>
