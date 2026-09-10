{{-- One signed-out screen, from NyonCode\WireModuleAuth\View\Screen.

     The frame is named, never shipped: with the shell installed this is
     `wire-admin::auth-layout` — the same head, the same theme decision before
     the first paint, the same card — so the login page looks like the panel it
     leads into without this package owning a second layout. --}}
<x-dynamic-component :component="$layout()" :title="$title">
    @if ($heading)
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100" data-testid="auth-heading" @wireEl('auth-heading')>{{ $heading }}</h1>
    @endif

    @if ($description)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif

    {{-- What Fortify put in the session on the way here: a reset link was sent,
         a verification mail was re-sent, a password was changed. Above the form
         rather than below it, because it is the answer to what was just done.

         Both boxes are `<x-wire::callout>`, the same surface the panel uses for
         a notice behind the door. They used to be hand-written green and red
         Tailwind — a second opinion about what a notice looks like, in the one
         place a user compares the two sides of the door. The wrapper carries the
         spacing and the hook name, because those belong to this screen. --}}
    @if (session('status'))
        <div class="mt-4" data-testid="auth-status" @wireEl('auth-status')>
            <x-wire::callout color="success">{{ session('status') }}</x-wire::callout>
        </div>
    @endif

    {{-- Every screen's errors in one place, and named: a failed sign-in is
         reported against `email` by Fortify whichever field was wrong, so a
         message drawn only under its own input is a message under the wrong
         one. The per-field messages stay too — this is the summary. --}}
    @if ($errors->any())
        <div class="mt-4" data-testid="auth-errors" @wireEl('auth-errors')>
            <x-wire::callout color="danger">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </x-wire::callout>
        </div>
    @endif

    <div class="mt-6">
        {{ $slot }}
    </div>
</x-dynamic-component>
