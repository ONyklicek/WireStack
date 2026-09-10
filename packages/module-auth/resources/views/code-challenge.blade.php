{{-- The six digits, for whichever flow mailed them.

     One screen for three: signing in without a password, the mailed second
     factor, and confirming an address. They differ in where the form posts and
     nowhere else — the same boxes, the same "send it again", the same way back —
     so three views would be three copies drifting apart on the next change to
     any of them. The caller passes the URLs (`$action`, `$resend`, `$back`) and
     the address to show, and this owns what the screen looks like.

     The resend button is its own `<form>` rather than a second submit inside the
     first: two submits posting to different URLs is a screen where pressing
     Enter in the code boxes may mail a new code and throw away the one being
     typed. --}}

<x-wire-module-auth::screen
    :title="__('wire-module-auth::messages.code_heading')"
    :heading="__('wire-module-auth::messages.code_heading')"
    :description="$address
        ? __('wire-module-auth::messages.code_description_address', ['address' => $address])
        : __('wire-module-auth::messages.code_description')"
>
    <form method="POST" action="{{ $action }}" class="space-y-4" data-testid="auth-code-form" @wireEl('auth-code-form')>
        @csrf

        {{ $forms->code() }}

        <x-wire::button type="submit" class="w-full" data-testid="auth-submit">
            {{ __('wire-module-auth::messages.code_continue') }}
        </x-wire::button>
    </form>

    <div class="mt-4 flex items-center justify-between gap-3">
        <form method="POST" action="{{ $resend }}" data-testid="auth-code-resend-form" @wireEl('auth-code-resend-form')>
            @csrf

            <x-wire::button type="submit" color="gray" outlined size="sm" data-testid="auth-code-resend">
                {{ __('wire-module-auth::messages.code_resend') }}
            </x-wire::button>
        </form>

        <a href="{{ $back }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400" data-testid="auth-back-link" @wireEl('auth-back-link')>
            {{ __('wire-module-auth::messages.back_to_sign_in') }}
        </a>
    </div>
</x-wire-module-auth::screen>
