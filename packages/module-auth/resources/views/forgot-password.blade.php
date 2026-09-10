{{-- Fortify's "request a reset link" view.

     Posts to the same path it was served from — Fortify names the GET
     `password.request` and the POST `password.email` at one URL. The reply is
     always the same whether or not the address exists, which is Fortify's
     decision and the right one: a form that says "no such account" is an
     account enumerator. --}}
@php($forms = app(\NyonCode\WireModuleAuth\Forms\AuthForms::class))

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.forgot_heading')"
    :heading="__('wire-module-auth::messages.forgot_heading')"
    :description="__('wire-module-auth::messages.forgot_description')"
>
    <form method="POST" action="{{ route('password.request') }}" class="space-y-4" data-testid="auth-forgot-form" @wireEl('auth-forgot-form')>
        @csrf

        {{ $forms->forgotPassword() }}

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.send_reset_link') }}
        </x-wire::button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-back-link" @wireEl('auth-back-link')>
                {{ __('wire-module-auth::messages.back_to_sign_in') }}
            </a>
        </p>
    </form>
</x-wire-module-auth::screen>
