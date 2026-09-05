{{-- The menu, from NyonCode\WireAdmin\View\Sidebar.

     Every arrangement decision here belongs to Workspace: which group, in what
     order, under what heading, with what icon and badge, and whether an entry is
     reachable at all. This file decides how that looks, and nothing else — which
     is why it holds no key→URL map. An entry with no URL is a registered thing
     this zone does not route, drawn as an unlinked row rather than left out, so
     a half-routed application can see what it is missing. --}}
<aside
    data-testid="admin-sidebar"
    x-data="{
        open: false,
        // Answered by the media query itself rather than by a resize handler
        // reading `innerWidth`: the query is what the `lg:` classes below are
        // matched on, so one source decides both and they cannot disagree at the
        // boundary. It also fires for a zoom change and for a device rotation,
        // neither of which is a resize event everywhere.
        desktop: window.matchMedia('(min-width: 1024px)').matches,
        watchWidth() {
            const query = window.matchMedia('(min-width: 1024px)');

            query.addEventListener('change', (event) => {
                this.desktop = event.matches;
                // Leaving the phone menu open behind a widening window would
                // hand the desktop column a state nobody set.
                if (event.matches) this.open = false;
            });
        },
    }"
    x-init="watchWidth()"
    class="wire-admin-sidebar lg:sticky lg:top-0 lg:h-dvh lg:w-64 lg:shrink-0"
>
    {{-- The mobile handle. Hidden from assistive tech when the menu is a
         permanent column, because there is nothing to expand at that width. --}}
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        aria-controls="wire-admin-nav"
        data-testid="admin-sidebar-toggle"
        class="m-3 inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm lg:hidden dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
    >
        {!! icon('outline:bars-3', 'h-5 w-5') !!}
        <span>{{ __('wire-admin::messages.menu') }}</span>
    </button>

    <nav
        id="wire-admin-nav"
        aria-label="{{ __('wire-admin::messages.navigation') }}"
        x-cloak
        x-show="open || desktop"
        class="h-full overflow-y-auto border-gray-200 bg-white p-3 lg:block lg:border-r dark:border-gray-800 dark:bg-gray-900"
    >
        @forelse ($groups as $group)
            <div class="mb-5 last:mb-0" data-testid="admin-nav-group" data-group="{{ $group->getKey() }}">
                @if ($group->hasVisibleLabel())
                    <p
                        data-testid="admin-nav-heading"
                        class="flex items-center gap-2 px-3 pb-2 text-xs font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500"
                    >
                        @if ($group->getIcon())
                            {!! icon($group->getIcon(), 'h-4 w-4') !!}
                        @endif
                        {{ $group->getLabel() }}
                    </p>
                @endif

                <ul class="space-y-1">
                    @foreach ($group->getItems() as $key => $item)
                        @php($url = $item->getUrl())
                        @php($isActive = $key === $activeKey)
                        <li>
                            {{-- One tag either way. A registered entry this zone
                                 does not route keeps its row and loses its href,
                                 which is what an unrouted resource already looks
                                 like elsewhere. --}}
                            <a
                                @if ($url) href="{{ $url }}" wire:navigate @else aria-disabled="true" @endif
                                data-testid="admin-nav-item"
                                data-resource="{{ $key }}"
                                @if ($isActive) data-active="true" aria-current="page" @endif
                                @class([
                                    'flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition',
                                    'bg-primary-50 font-medium text-primary-800 dark:bg-primary-950 dark:text-primary-200' => $isActive,
                                    'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' => ! $isActive,
                                    'cursor-default opacity-60' => ! $url,
                                ])
                            >
                                @if ($item->getIcon())
                                    {!! icon($item->getIcon(), 'h-5 w-5 shrink-0 text-gray-400') !!}
                                @endif

                                <span class="flex-1 truncate" data-testid="admin-nav-label">{{ $item->getLabel() }}</span>

                                @if ($item->getBadge())
                                    <x-wire::badge
                                        :color="$item->getBadgeColor() ?? 'gray'"
                                        data-testid="admin-nav-badge"
                                    >{{ $item->getBadge() }}</x-wire::badge>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            {{-- Nothing registered. An empty column reads as a broken menu, so it
                 says which of the two it is. --}}
            <p data-testid="admin-nav-empty" class="px-3 py-2 text-sm text-gray-400 dark:text-gray-500">
                {{ __('wire-admin::messages.empty') }}
            </p>
        @endforelse
    </nav>
</aside>
