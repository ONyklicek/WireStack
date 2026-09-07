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
        <p class="mb-4 text-sm text-green-700 dark:text-green-300" data-testid="auth-verify-sent">
            {{ __('wire-module-auth::messages.verify_sent') }}
        </p>
    @endif

    <div class="flex items-center justify-between gap-3">
        <form method="POST" action="{{ route('verification.send') }}" data-testid="auth-verify-form">
            @csrf

            <x-wire::button type="submit" data-testid="auth-submit">
                {{ __('wire-module-auth::messages.resend_verification') }}
            </x-wire::button>
        </form>

        @if (\NyonCode\WireModuleAuth\Support\Screens::canSignOut())
            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button type="submit" class="text-sm text-gray-500 hover:underline dark:text-gray-400" data-testid="auth-sign-out">
                    {{ __('wire-module-auth::messages.sign_out') }}
                </button>
            </form>
        @endif
    </div>
</x-wire-module-auth::screen>
