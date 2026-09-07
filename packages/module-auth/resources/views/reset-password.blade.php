{{-- Fortify's "set a new password" view.

     The one screen whose POST is at a different URL from its GET — the link in
     the mail carries the token in the path — so this posts to `password.update`
     by name and carries the token and the address in hidden inputs. Fortify
     validates both against the broker; nothing here is trusted for being in the
     form. --}}
<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.reset_heading')"
    :heading="__('wire-module-auth::messages.reset_heading')"
    :description="__('wire-module-auth::messages.reset_description')"
>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" data-testid="auth-reset-form">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        @include('wire-module-auth::partials.field', [
            'name' => 'email',
            'type' => 'email',
            'label' => __('wire-module-auth::messages.email'),
            'value' => old('email', $request->input('email')),
            'autocomplete' => 'username',
        ])

        @include('wire-module-auth::partials.field', [
            'name' => 'password',
            'type' => 'password',
            'label' => __('wire-module-auth::messages.new_password'),
            'autocomplete' => 'new-password',
            'autofocus' => true,
        ])

        @include('wire-module-auth::partials.field', [
            'name' => 'password_confirmation',
            'type' => 'password',
            'label' => __('wire-module-auth::messages.confirm_password'),
            'autocomplete' => 'new-password',
        ])

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.reset_password') }}
        </x-wire::button>
    </form>
</x-wire-module-auth::screen>
