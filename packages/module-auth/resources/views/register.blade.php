{{-- Fortify's registration view.

     Routed only where `Features::registration()` is on, which is why nothing
     here checks it: a screen for a feature that is off is never reached.

     The fields are `Forms\AuthForms::register()`, so an application that takes a
     company name or a phone number at sign-up adds it there — and tells
     `Fortify::createUsersUsing()` what to do with it, which is the half no view
     ever owned. --}}

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.register')"
    :heading="__('wire-module-auth::messages.register_heading')"
    :description="__('wire-module-auth::messages.register_description')"
>
    <form method="POST" action="{{ route('register') }}" class="space-y-4" data-testid="auth-register-form" @wireEl('auth-register-form')>
        @csrf

        {{ $forms->register() }}

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.register') }}
        </x-wire::button>

        <p class="text-center text-sm text-gray-500 dark:text-gray-400">
            {{ __('wire-module-auth::messages.already_registered') }}
            <a href="{{ route('login') }}" class="text-primary-600 hover:underline dark:text-primary-400">
                {{ __('wire-module-auth::messages.sign_in') }}
            </a>
        </p>
    </form>
</x-wire-module-auth::screen>
