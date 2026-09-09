{{-- Fortify's password-confirmation view.

     Not a sign-in: the person is already signed in and has walked into
     something behind the `password.confirm` middleware. Fortify remembers the
     confirmation for the window in its config and sends them where they were
     going. --}}
<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.confirm_heading')"
    :heading="__('wire-module-auth::messages.confirm_heading')"
    :description="__('wire-module-auth::messages.confirm_description')"
>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4" data-testid="auth-confirm-form" @wireEl('auth-confirm-form')>
        @csrf

        @include('wire-module-auth::partials.field', [
            'name' => 'password',
            'type' => 'password',
            'label' => __('wire-module-auth::messages.password'),
            'autocomplete' => 'current-password',
            'autofocus' => true,
        ])

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.confirm') }}
        </x-wire::button>
    </form>
</x-wire-module-auth::screen>
