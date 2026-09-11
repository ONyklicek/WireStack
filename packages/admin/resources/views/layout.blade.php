{{-- The page frame, from NyonCode\WireAdmin\View\Layout.

     A full-page Livewire component needs a layout and the framework deliberately
     does not supply one — until an application installs this package and names
     it. Nothing here is set by a service provider: `livewire.component_layout`
     stays the application's line to write (ADR 0028 §2).

     Rendered on a full page load only, which is what makes the sidebar's zone
     read safe: inside a Livewire update the current route name is
     `livewire.update` and every zone-derived answer would be null (ADR 0027). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full"
      data-density="{{ \NyonCode\WireCore\Foundation\Enums\Density::configured()->value }}"
      data-shape="{{ \NyonCode\WireCore\Foundation\Enums\Shape::configured()->value }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>

    {{-- First in the head, before the application's own assets: the theme
         decision is the one thing that has to be made before anything is
         painted, and a blocking script above it is a white page waiting to
         happen. Same order as the auth layout. --}}
    @include('wire-admin::partials.theme')

    {{-- The spacing scale, keyed on the attribute above. --}}
    @include('wire-core::partials.density')
    @include('wire-core::partials.shape')

    {{-- And the menu's width, for the same reason and one frame earlier than
         Alpine can manage it. Only this layout includes it: the auth frame has
         no menu to collapse. --}}
    @include('wire-admin::partials.rail')

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
    {{-- In the head with the theme script, and for the same reason: parsed
         between `</head>` and `<body>` the browser relocated this tag into the
         body, where Livewire re-runs it on every `wire:navigate` — one more
         `alpine:init` listener per visit, on an event that has already fired. --}}
    <script>
        document.addEventListener('alpine:init', () => {
            // One store rather than two components talking to each other: the rail
            // is set in the top bar and read in the sidebar, and a page that
            // remembers neither is a page that fights the user every navigation.
            Alpine.store('wireAdmin', {
                // Read from the same owner that put the attribute on the
                // document, so the store cannot believe one thing while the page
                // shows another — the same arrangement as the theme below.
                rail: window.wireAdminRail.get(),

                // The *choice*, which is one of three — not whether the page is
                // currently dark, which is an outcome. Holding the outcome is what
                // made "system" impossible to return to. Read from the same owner
                // that put the class on the document, so the switch cannot show
                // one thing while the page shows another.
                theme: window.wireAdminTheme.get(),

                // Same arrangement as the theme: read from the owner that put
                // the attribute on the document, so the switch cannot show one
                // thing while the page shows another.
                density: window.wireDensity.get(),

                // Never restored from storage, unlike the rail: a phone menu that
                // reopened itself on every page would be covering the page it just
                // navigated to.
                mobile: false,

                // Whether the viewport is wide enough for a column at all. The
                // rail is a *desktop* shape — below `lg` the same menu is a
                // drawer — so a stored rail preference must not follow it onto a
                // phone. Held as state rather than asked per read, because every
                // row in the menu asks and `matchMedia` per row per frame is not
                // free.
                //
                // **1024px twice, deliberately.** The other copy is the media
                // query in partials/rail.blade.php, which is what actually
                // collapses the column — this one only decides whether hover
                // opens a panel. Move one without the other and the two disagree
                // about what "in the rail" means, which is not a subtle failure:
                // it renders the wide menu's parts inside a 64-pixel column.
                wide: window.matchMedia('(min-width: 1024px)').matches,

                init() {
                    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
                        this.wide = event.matches;

                        // A drawer is a thing a phone has. Left standing when the
                        // window is widened it makes `railed` disagree with the
                        // CSS beside it, which decides the same question from a
                        // media query and knows nothing about drawers.
                        if (event.matches && this.mobile) this.closeMobile();
                    });
                },

                // The one question the menu asks. Three flags answer it and no
                // view should have to recombine them — the old `rail && ! mobile`
                // was written in nine places and silently disagreed with the `lg:`
                // classes beside it on a phone whose rail had been collapsed on a
                // laptop.
                get railed() {
                    return this.rail && this.wide && ! this.mobile;
                },

                // What this keyboard calls the modifier. The palette's ⌘K beside
                // it is written as a literal, which is wrong on Windows and was
                // simply never noticed; a control that names its own shortcut
                // should at least name the right key.
                get modKey() {
                    const platform = navigator.userAgentData?.platform ?? navigator.platform ?? '';

                    return /mac|iphone|ipad|ipod/i.test(platform) ? '⌘' : 'Ctrl+';
                },

                openMobile() {
                    this.mobile = true;
                    document.body.classList.add('overflow-hidden', 'lg:overflow-auto');
                },

                closeMobile() {
                    this.mobile = false;
                    document.body.classList.remove('overflow-hidden', 'lg:overflow-auto');
                },

                toggleRail() {
                    this.rail = window.wireAdminRail.set(! this.rail);
                },

                // Storing the choice and turning it into a class is one rule and
                // lives in one place — see partials/theme.blade.php. This is the
                // switch, not a second copy of what the switch means.
                /** Choose a density for this browser. The default stays the config's. */
                setDensity(density) {
                    this.density = window.wireDensity.set(density);
                },

                setTheme(theme) {
                    this.theme = window.wireAdminTheme.set(theme);
                },
            });
        });
    </script>
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
            <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-gray-200 bg-white/90 px-4 backdrop-blur-sm dark:border-gray-800 dark:bg-gray-900/90" @wireEl('admin-topbar')>
                {{-- The phone handle. It lives in the top bar rather than inside
                     the drawer it opens, which is the whole fix: the old one was
                     a button stacked above the menu, so on a phone the page began
                     with a menu button and a gap where the header should be. --}}
                <button
                    type="button"
                    x-data
                    x-on:click="$store.wireAdmin.openMobile()"
                    x-bind:aria-expanded="$store.wireAdmin.mobile ? 'true' : 'false'"
                    aria-controls="wire-admin-nav"
                    data-testid="admin-sidebar-toggle" @wireEl('admin-sidebar-toggle')
                    class="-ms-1 inline-flex rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                >
                    <span class="sr-only">{{ __('wire-admin::messages.menu') }}</span>
                    {!! icon('outline:bars-3', 'h-5 w-5') !!}
                </button>

                {{-- Says which way it goes, rather than naming one direction for
                     both states: a handle labelled "collapse the menu" while the
                     menu is already collapsed is the control people press twice
                     to find out what it does.

                     The keyboard reaches it too, on the binding every editor and
                     admin has settled on for this one control. Bound on the
                     window rather than the button — a shortcut you must first
                     focus the control to use is not a shortcut — and declined
                     while the caret is in a field, because in a rich-text editor
                     the same chord is bold. --}}
                <button
                    type="button"
                    x-data
                    x-on:click="$store.wireAdmin.toggleRail()"
                    x-on:keydown.window="
                        ($event.metaKey || $event.ctrlKey)
                            && ! $event.altKey
                            && $event.key?.toLowerCase() === 'b'
                            && ! $event.target?.closest?.('input, textarea, select, [contenteditable]')
                            && ($event.preventDefault(), $store.wireAdmin.toggleRail())
                    "
                    x-bind:aria-pressed="$store.wireAdmin.rail ? 'true' : 'false'"
                    x-bind:aria-keyshortcuts="$store.wireAdmin.modKey === '⌘' ? 'Meta+B' : 'Control+B'"
                    x-bind:title="($store.wireAdmin.rail
                        ? '{{ __('wire-admin::messages.expand_menu') }}'
                        : '{{ __('wire-admin::messages.collapse_menu') }}') + ' (' + $store.wireAdmin.modKey + 'B)'"
                    data-testid="admin-rail-toggle" @wireEl('admin-rail-toggle')
                    title="{{ __('wire-admin::messages.collapse_menu') }}"
                    class="-ms-1 hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:inline-flex dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                >
                    <span class="sr-only" x-show="! $store.wireAdmin.rail">{{ __('wire-admin::messages.collapse_menu') }}</span>
                    <span class="sr-only" x-show="$store.wireAdmin.rail" x-cloak>{{ __('wire-admin::messages.expand_menu') }}</span>
                    <span x-show="! $store.wireAdmin.rail">{!! icon('outline:bars-3', 'h-5 w-5') !!}</span>
                    <span x-show="$store.wireAdmin.rail" x-cloak>{!! icon('outline:chevron-double-right', 'h-5 w-5 rtl:rotate-180') !!}</span>
                </button>

                {{-- The brand moved into the sidebar header, where a logo belongs
                     and where the rail can shrink it to a square. What stays here
                     is the slot: an application that passes one gets its markup in
                     the top bar, and one that passes nothing gets no duplicate of
                     the logo it just set. --}}
                @isset ($brand)
                    <span class="truncate font-semibold" data-testid="admin-brand" @wireEl('admin-brand')>{{ $brand }}</span>
                @endisset

                {{-- The palette trigger, in the chrome rather than on a page,
                     because that is what makes a zone real: the palette derives
                     its zone from the route it was rendered on, so the same
                     markup links into `admin` on an admin page and into
                     `business` on a business one, with nothing declared. --}}
                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('open-global-search')"
                    data-testid="global-search-trigger" @wireEl('global-search-trigger')
                    class="ms-auto inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 py-2.5 ps-3 pe-2 text-sm sm:py-1.5 text-gray-500 transition hover:border-gray-300 hover:bg-white sm:w-64 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700"
                >
                    {!! icon('outline:magnifying-glass', 'h-4 w-4 shrink-0') !!}
                    <span class="hidden flex-1 text-start sm:block">{{ __('wire-admin::messages.search') }}</span>
                    <kbd class="hidden rounded-sm border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] sm:block dark:border-gray-700 dark:bg-gray-900">⌘K</kbd>
                </button>

                {{ $topbar ?? '' }}

                {{-- What a module asked to have *seen* rather than merely
                     present: a team switcher, a tenant picker, an environment
                     badge. Same registry as the modals at the end of the body,
                     same reason — the shell sits at the top of the graph and
                     cannot name a module's views, and a module cannot reach into
                     this file. --}}
                @foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views(\NyonCode\WireCore\Foundation\View\PageChrome::TOPBAR) as $topbarView)
                    @include($topbarView)
                @endforeach

                {{-- The bell is a Livewire component in wire-core and was already
                     there; what was missing was a shell to mount it in. Only where
                     notifications are actually stored, though — see the component. --}}
                @if ($showsNotifications())
                    @livewire('wire-notification-bell')
                @endif

                {{-- The theme and the density switch, from `sm` up: on a phone
                     they are at the foot of the menu drawer instead, because
                     this row cannot hold them and the user menu at 390px. --}}
                @include('wire-admin::partials.preferences', ['variant' => 'topbar'])

                {{-- The application's own menu, and a name when it has not written
                     one: an admin whose top bar cannot say who is signed in reads
                     as unfinished, and this is the smallest honest default. --}}
                @if (isset($user))
                    {{ $user }}
                @elseif (auth()->check())
                    {{-- The canonical dropdown from wire-core, not a second one
                         written here: it already owns the floating placement, the
                         outside click, the escape key and the phone bottom sheet,
                         and a menu in the chrome is exactly what those were for. --}}
                    <x-wire::dropdown position="bottom-end" width="w-56" sheet-on-mobile>
                        <x-slot:trigger>
                            <button
                                type="button"
                                data-testid="admin-user" @wireEl('admin-user')
                                class="flex items-center gap-2 rounded-full p-1 ps-1 transition hover:bg-gray-100 dark:hover:bg-gray-800"
                            >
                                <x-wire-admin::avatar :user="auth()->user()" class="h-8 w-8" />
                                <span class="hidden max-w-32 truncate text-sm text-gray-700 sm:block dark:text-gray-200">
                                    {{ auth()->user()->name ?? auth()->user()->email ?? '' }}
                                </span>
                                {!! icon('outline:chevron-down', 'h-4 w-4 text-gray-400') !!}
                            </button>
                        </x-slot:trigger>

                        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                            <x-wire-admin::avatar :user="auth()->user()" class="h-9 w-9" />

                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name ?? '' }}</p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email ?? '' }}</p>
                            </div>
                        </div>

                        {{-- Whatever the application puts in the menu, first: an
                             application's own entries are not a module's to sort
                             around. The shell itself still ships no profile page
                             and no sign-out route, because it owns neither. --}}
                        {{ $userMenu ?? '' }}

                        {{-- And what the installed packages put there. Same
                             registry as the top bar and the end of the body, same
                             reason: the profile page belongs to the users module
                             and the way out belongs to the auth module, and the
                             shell can name neither. --}}
                        @foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views(\NyonCode\WireCore\Foundation\View\PageChrome::USER_MENU) as $userMenuView)
                            @include($userMenuView)
                        @endforeach
                    </x-wire::dropdown>
                @endif
                @wireRenderHook('admin.topbar.end')
            </header>

            <main id="wire-admin-main" class="p-4" data-testid="admin-content" @wireEl('admin-content')>
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewire('wire-global-search')

    <x-wire-notifications::toast-container />

    {{-- What other packages asked the shell to render once per page: a media
         picker something else opens, a host for a modal that has to outlive the
         page that raised it. The shell names none of them and they name no
         layout — see NyonCode\WireCore\Foundation\View\PageChrome. --}}
    @foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views() as $chromeView)
        @include($chromeView)
    @endforeach

    @livewireScripts
</body>
</html>
