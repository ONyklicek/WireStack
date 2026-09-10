{{-- Fortify's "set a new password" view.

     The one screen whose POST is at a different URL from its GET — the link in
     the mail carries the token in the path — so this posts to `password.update`
     by name, and the token rides in the schema as a `Hidden` field.

     Nothing here is trusted for having been in the form: Fortify validates the
     token and the address against the broker. --}}

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.reset_heading')"
    :heading="__('wire-module-auth::messages.reset_heading')"
    :description="__('wire-module-auth::messages.reset_description')"
>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4" data-testid="auth-reset-form" @wireEl('auth-reset-form')>
        @csrf

        {{ $forms->resetPassword($request->route('token'), $request->input('email')) }}

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.reset_password') }}
        </x-wire::button>
    </form>
</x-wire-module-auth::screen>
