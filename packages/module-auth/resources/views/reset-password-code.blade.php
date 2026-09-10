{{-- Setting a new password from a code instead of a link.

     Fortify's own reset screen takes the token out of the URL it was reached
     through; this one is reached by hand, so the code and the address are typed
     and the token never appears on the screen at all — it is what the code's row
     carries, and the controller swaps one for the other before Fortify's own
     reset runs (ADR 0037 §2).

     The address is filled in from the request that asked for the code, so the
     usual case is three boxes and two passwords. It stays editable: a person who
     asked from one browser and finished in another still has a form they can
     complete. --}}
@php($forms = app(\NyonCode\WireModuleAuth\Forms\AuthForms::class))

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.reset_heading')"
    :heading="__('wire-module-auth::messages.reset_heading')"
    :description="__('wire-module-auth::messages.code_reset_description')"
>
    <form method="POST" action="{{ route('wire-auth.reset-code.store') }}" class="space-y-4" data-testid="auth-reset-code-form" @wireEl('auth-reset-code-form')>
        @csrf

        {{ $forms->resetPasswordCode($address) }}

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.reset_password') }}
        </x-wire::button>

        <p class="text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-code-restart" @wireEl('auth-code-restart')>
                {{ __('wire-module-auth::messages.code_restart') }}
            </a>
        </p>
    </form>
</x-wire-module-auth::screen>
