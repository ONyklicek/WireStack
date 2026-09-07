{{-- One signed-out screen, from NyonCode\WireModuleAuth\View\Screen.

     The frame is named, never shipped: with the shell installed this is
     `wire-admin::auth-layout` — the same head, the same theme decision before
     the first paint, the same card — so the login page looks like the panel it
     leads into without this package owning a second layout. --}}
<x-dynamic-component :component="$layout()" :title="$title">
    @if ($heading)
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100" data-testid="auth-heading">{{ $heading }}</h1>
    @endif

    @if ($description)
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif

    {{-- What Fortify put in the session on the way here: a reset link was sent,
         a verification mail was re-sent, a password was changed. Above the form
         rather than below it, because it is the answer to what was just done. --}}
    @if (session('status'))
        <div class="mt-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800 dark:bg-green-500/10 dark:text-green-300" data-testid="auth-status">
            {{ session('status') }}
        </div>
    @endif

    {{-- Every screen's errors in one place, and named: a failed sign-in is
         reported against `email` by Fortify whichever field was wrong, so a
         message drawn only under its own input is a message under the wrong
         one. The per-field messages stay too — this is the summary. --}}
    @if ($errors->any())
        <div class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-500/10 dark:text-red-300" data-testid="auth-errors">
            <ul class="space-y-1">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6">
        {{ $slot }}
    </div>
</x-dynamic-component>
