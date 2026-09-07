{{-- Fortify's registration view.

     Routed only where `Features::registration()` is on, which is why nothing
     here checks it: a screen for a feature that is off is never reached. --}}
<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.register')"
    :heading="__('wire-module-auth::messages.register_heading')"
    :description="__('wire-module-auth::messages.register_description')"
>
    <form method="POST" action="{{ route('register') }}" class="space-y-4" data-testid="auth-register-form">
        @csrf

        @include('wire-module-auth::partials.field', [
            'name' => 'name',
            'label' => __('wire-module-auth::messages.name'),
            'autocomplete' => 'name',
            'autofocus' => true,
        ])

        @include('wire-module-auth::partials.field', [
            'name' => 'email',
            'type' => 'email',
            'label' => __('wire-module-auth::messages.email'),
            'autocomplete' => 'username',
        ])

        @include('wire-module-auth::partials.field', [
            'name' => 'password',
            'type' => 'password',
            'label' => __('wire-module-auth::messages.password'),
            'autocomplete' => 'new-password',
        ])

        @include('wire-module-auth::partials.field', [
            'name' => 'password_confirmation',
            'type' => 'password',
            'label' => __('wire-module-auth::messages.confirm_password'),
            'autocomplete' => 'new-password',
        ])

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
