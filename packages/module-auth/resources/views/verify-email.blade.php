{{-- Fortify's "confirm your address" view.

     Reached by someone who is signed in but not verified, so it is the one
     signed-out-looking screen with a signed-in user behind it — and the only
     way onward is the link in their mail. The way out is here for the same
     reason it is in the panel's menu: a screen you cannot leave is a trap. --}}
<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.verify_heading')"
    :heading="__('wire-module-auth::messages.verify_heading')"
    :description="__('wire-module-auth::messages.verify_description')"
>
    @if (session('status') === 'verification-link-sent')
        <p class="mb-4 text-sm text-green-700 dark:text-green-300" data-testid="auth-verify-sent" @wireEl('auth-verify-sent')>
            {{ __('wire-module-auth::messages.verify_sent') }}
        </p>
    @endif

    {{-- The other way to confirm, where the code flow is on: a mail whose link
         a client rewrote is a dead end, and this is the way out of it. A form
         rather than a link, because reaching that screen is what mails the
         code. --}}
    @if (\NyonCode\WireModuleAuth\Support\Codes::verifyEmail())
        <form method="POST" action="{{ route('wire-auth.verify-email-code.send') }}" class="mb-4" data-testid="auth-verify-code-form" @wireEl('auth-verify-code-form')>
            @csrf

            <button type="submit" class="text-sm text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-code-link" @wireEl('auth-code-link')>
                {{ __('wire-module-auth::messages.code_verify_link') }}
            </button>
        </form>
    @endif

    <div class="flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}" data-testid="auth-verify-form" @wireEl('auth-verify-form')>
            @csrf

            <x-wire::button type="submit" data-testid="auth-submit">
                {{ __('wire-module-auth::messages.resend_verification') }}
            </x-wire::button>
        </form>

        @if (\NyonCode\WireModuleAuth\Support\Screens::canSignOut())
            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="text-sm text-gray-500 hover:underline dark:text-gray-400" data-testid="auth-sign-out" @wireEl('auth-sign-out')>
                    {{ __('wire-module-auth::messages.sign_out') }}
                </button>
            </form>
        @endif
    </div>
</x-wire-module-auth::screen>
