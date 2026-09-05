{{-- The page frame, from NyonCode\WireAdmin\View\Layout.

     A full-page Livewire component needs a layout and the framework deliberately
     does not supply one — until an application installs this package and names
     it. Nothing here is set by a service provider: `livewire.component_layout`
     stays the application's line to write (ADR 0028 §2).

     Rendered on a full page load only, which is what makes the sidebar's zone
     read safe: inside a Livewire update the current route name is
     `livewire.update` and every zone-derived answer would be null (ADR 0027). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- The application's own stylesheet and scripts: `@vite([...])`, a CDN
         tag, whatever it uses. The shell cannot guess an entry name and does not
         try. --}}
    {{ $head ?? '' }}

    @livewireStyles

    {{-- In the head, deliberately. Every wireStack Alpine controller has to be in
         the initial document — that is what survives Livewire's cached
         Back/Forward navigation, and a controller arriving late is the one thing
         ADR 0024 forbids. --}}
    @wireStackScripts
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    {{-- Keyboard users reach the page without walking the whole menu first. --}}
    <a
        href="#wire-admin-main"
        class="sr-only focus:not-sr-only focus:absolute focus:m-3 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:text-sm dark:focus:bg-gray-900"
    >{{ __('wire-admin::messages.skip_to_content') }}</a>

    <div class="lg:flex">
        <x-wire-admin::sidebar :linked-only="$linkedOnly" />

        <div class="min-w-0 flex-1">
            <header class="flex items-center gap-4 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <span class="font-semibold" data-testid="admin-brand">{{ $brand ?? config('app.name') }}</span>

                {{-- The palette trigger, in the chrome rather than on a page,
                     because that is what makes a zone real: the palette derives
                     its zone from the route it was rendered on, so the same
                     markup links into `admin` on an admin page and into
                     `business` on a business one, with nothing declared. --}}
                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('open-global-search')"
                    data-testid="global-search-trigger"
                    class="ms-auto inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                >
                    {{ __('wire-admin::messages.search') }}
                    <kbd class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800">⌘K</kbd>
                </button>

                {{ $topbar ?? '' }}
                {{ $user ?? '' }}
            </header>

            <main id="wire-admin-main" class="p-4" data-testid="admin-content">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewire('wire-global-search')

    <x-wire-notifications::toast-container />

    @livewireScripts
</body>
</html>
