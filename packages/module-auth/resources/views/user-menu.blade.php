{{-- The way out, in the user menu.

     Registered into PageChrome::USER_MENU rather than written into a layout
     slot by every application: the shell owns no auth and cannot name a logout
     route, and before that region existed the only place for this form was a
     slot each application filled by hand — the same fifteen lines, against
     whichever packages it happened to have installed.

     The row is `<x-wire::menu-item>` from wire-core, so it looks like every
     other row of the menu without this package depending on the shell that
     draws it.

     `Route::has()` at render, never at boot: routes are not loaded when
     providers register their chrome, so a check there answers false for a route
     Fortify is about to declare. --}}
@if (\NyonCode\WireModuleAuth\Support\Screens::canSignOut())
    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <x-wire::menu-item type="submit" icon="outline:arrow-right-start-on-rectangle" data-testid="auth-sign-out">
            {{ __('wire-module-auth::messages.sign_out') }}
        </x-wire::menu-item>
    </form>
@endif
