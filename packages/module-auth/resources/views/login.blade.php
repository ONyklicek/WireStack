{{-- Fortify's login view.

     A plain form posting to Fortify's own route, and that is the design rather
     than a shortcut. Everything that makes signing in hard — the credential
     check, the rate limiter keyed on e-mail and IP, the session regeneration
     that closes fixation, the hand-off to the two-factor challenge — happens in
     the controller behind this URL, by a maintained owner. What was missing was
     only the markup, which Fortify deliberately has no opinion about.

     The action is the GET route's URL on purpose: Fortify serves both verbs at
     one path and names the POST differently across its major versions, so the
     name that has been stable is the one to use. --}}
<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.sign_in')"
    :heading="__('wire-module-auth::messages.sign_in_heading')"
    :description="__('wire-module-auth::messages.sign_in_description')"
>
    <form method="POST" action="{{ route('login') }}" class="space-y-4" data-testid="auth-login-form">
        @csrf

        @include('wire-module-auth::partials.field', [
            'name' => 'email',
            'type' => 'email',
            'label' => __('wire-module-auth::messages.email'),
            'autocomplete' => 'username',
            'autofocus' => true,
        ])

        @include('wire-module-auth::partials.field', [
            'name' => 'password',
            'type' => 'password',
            'label' => __('wire-module-auth::messages.password'),
            'autocomplete' => 'current-password',
        ])

        <div class="flex items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input
                    type="checkbox"
                    name="remember"
                    value="1"
                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                />
                {{ __('wire-module-auth::messages.remember_me') }}
            </label>

            {{-- Drawn from the same switch that creates the route: a link to a
                 password reset an application never enabled is a 404 it finds
                 out about from a user. --}}
            @if (\NyonCode\WireModuleAuth\Support\Screens::canResetPassword())
                <a
                    href="{{ route('password.request') }}"
                    class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                    data-testid="auth-forgot-link"
                >{{ __('wire-module-auth::messages.forgot_password') }}</a>
            @endif
        </div>

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.sign_in') }}
        </x-wire::button>

        @if (\NyonCode\WireModuleAuth\Support\Screens::canRegister())
            <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ route('register') }}" class="text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-register-link">
                    {{ __('wire-module-auth::messages.register') }}
                </a>
            </p>
        @endif
    </form>
</x-wire-module-auth::screen>
