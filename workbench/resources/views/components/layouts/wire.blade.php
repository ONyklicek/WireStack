{{-- Layout for full-page Livewire components registered by Route::wireResources().

     A full-page component needs one and the framework deliberately does not
     supply it: what a page is wrapped in — the shell, the nav, the assets — is
     the application's, exactly as the routes themselves are. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Wire' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-100 text-slate-950 antialiased">
    <div class="mx-auto max-w-7xl px-4 py-8">
        {{-- The shell's command palette. Here rather than on one page because
             that is where an application puts it, and because it is what makes
             the zone real: the palette derives its zone from the route it was
             rendered on, so the same markup links into `admin` on an admin page
             and into `business` on a business one, with nothing declared. --}}
        <button
                type="button"
                x-data
                x-on:click="$dispatch('open-global-search')"
                data-testid="global-search-trigger"
                class="mb-6 inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm text-slate-500"
        >
            Search… <kbd class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">⌘K</kbd>
        </button>

        @livewire('wire-global-search')

        {{ $slot }}
    </div>

    <x-wire-notifications::toast-container />
    @livewireScripts
</body>
</html>
