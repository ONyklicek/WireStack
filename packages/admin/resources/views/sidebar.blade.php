{{-- The menu, from NyonCode\WireAdmin\View\Sidebar.

     Every arrangement decision here belongs to Workspace: which group, in what
     order, under what heading, with what icon and badge, and whether an entry is
     reachable at all. This file decides how that looks, and nothing else — which
     is why it holds no key→URL map. An entry with no URL is a registered thing
     this zone does not route, drawn as an unlinked row rather than left out, so
     a half-routed application can see what it is missing.

     **One element, two shapes.** The drawer a phone slides open and the column a
     desktop keeps are the same `<aside>` moved by a transform, not two copies of
     the menu behind media queries. Two copies would mean every `data-testid` in
     here appears twice, and every test and driver that counts entries would
     silently be counting double. --}}

{{-- The dimming layer behind the phone drawer. Its own element rather than a
     pseudo-element on the aside, because it has to sit *under* the menu and
     *over* the page, and tapping it closes. --}}
<div
    x-data
    x-show="$store.wireAdmin?.mobile"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-on:click="$store.wireAdmin.closeMobile()"
    data-testid="admin-sidebar-overlay" @wireEl('admin-sidebar-overlay')
    class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-[1px] lg:hidden"
></div>

<aside
    data-testid="admin-sidebar" @wireEl('admin-sidebar')
    x-data
    x-on:keydown.escape.window="$store.wireAdmin.closeMobile()"
    x-bind:class="$store.wireAdmin?.mobile ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
    {{-- The width is not bound. It was, and binding it is what made a collapsed
         menu open to 288 pixels on every page load and slide shut again: Alpine
         cannot answer before the first paint, and `transition-[width]` turned
         that one wrong frame into a third of a second of animation. It is now
         `partials/rail.blade.php`'s, decided from `<html data-rail>` before the
         body is parsed — which is also why `lg:w-64` can finally sit here as an
         ordinary class. The rail's rule is an attribute selector and outranks
         it, so the two no longer compete on sheet order. --}}
    class="wire-admin-sidebar fixed inset-y-0 start-0 z-50 flex w-72 flex-col border-e border-gray-200 bg-white transition-transform duration-200 motion-reduce:transition-none lg:sticky lg:top-0 lg:z-auto lg:h-dvh lg:w-64 lg:shrink-0 lg:translate-x-0 lg:shadow-none lg:transition-[width] dark:border-gray-800 dark:bg-gray-900"
>
    <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 dark:border-gray-800">
        <x-wire-admin::brand />

        {{-- Closing from inside the drawer. A phone user whose thumb is on the
             menu should not have to reach the dimmed strip beside it. --}}
        <button
            type="button"
            x-on:click="$store.wireAdmin.closeMobile()"
            data-testid="admin-sidebar-close" @wireEl('admin-sidebar-close')
            class="me-3 shrink-0 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 lg:hidden dark:hover:bg-gray-800 dark:hover:text-gray-300"
        >
            <span class="sr-only">{{ __('wire-admin::messages.close_menu') }}</span>
            {!! icon('outline:x-mark', 'h-5 w-5') !!}
        </button>
    </div>

    <nav
        id="wire-admin-nav"
        aria-label="{{ __('wire-admin::messages.navigation') }}"
        class="flex-1 overflow-x-hidden overflow-y-auto p-3"
    >
        @forelse ($groups as $group)
            {{-- A collapsible group carries its own open state, keyed by the group
                 slug so two menus in one application never share one. Restored
                 from localStorage on init, which is what makes folding survive the
                 next page — a menu that forgets is worse than one that cannot
                 fold. --}}
            <div
                class="mb-5 last:mb-0"
                data-testid="admin-nav-group" @wireEl('admin-nav-group')
                data-group="{{ $group->getKey() }}"
                @if ($group->isCollapsible())
                    x-data="{
                        open: {{ $group->isCollapsed() ? 'false' : 'true' }},
                        key: 'wire-admin.nav.{{ $group->getKey() }}',
                        init() {
                            const stored = window.localStorage?.getItem(this.key);

                            if (stored !== null) this.open = stored === '1';

                            this.$watch('open', (value) => window.localStorage?.setItem(this.key, value ? '1' : '0'));
                        },
                    }"
                @endif
            >
                {{-- What the rail puts where the heading was. Without it the
                     groups run together into one undivided column of icons —
                     the folding is still there, the fact that it is *grouped* is
                     not. First group excluded: a rule above the first row is a
                     line under the brand, not a divider. --}}
                @unless ($loop->first)
                    <div
                        data-rail-only
                        aria-hidden="true"
                        data-testid="admin-nav-rail-divider" @wireEl('admin-nav-rail-divider')
                        class="mx-auto mb-3 h-px w-8 bg-gray-200 dark:bg-gray-700"
                    ></div>
                @endunless

                @if ($group->hasVisibleLabel())
                    {{-- Hidden in the rail rather than removed: the group still
                         owns its entries, and a heading that shrank to an ellipsis
                         in 64 pixels is what made the collapsed menu look like a
                         column of stubs. --}}
                    <div data-rail-hide>
                    @if ($group->isCollapsible())
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            x-bind:aria-expanded="open ? 'true' : 'false'"
                            aria-controls="wire-admin-group-{{ $group->getKey() }}"
                            data-testid="admin-nav-heading" @wireEl('admin-nav-heading')
                            data-collapsible="true"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-1.5 text-[11px] font-semibold tracking-wider text-gray-400 uppercase transition hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
                        >
                            @if ($group->getIcon())
                                {!! icon($group->getIcon(), 'h-3.5 w-3.5') !!}
                            @endif
                            <span class="flex-1 truncate text-start">{{ $group->getLabel() }}</span>
                            <span x-bind:class="open || 'rotate-180'" class="transition">{!! icon('outline:chevron-up', 'h-3.5 w-3.5') !!}</span>
                        </button>
                    @else
                        <p
                            data-testid="admin-nav-heading" @wireEl('admin-nav-heading')
                            class="flex items-center gap-2 px-3 py-1.5 text-[11px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500"
                        >
                            @if ($group->getIcon())
                                {!! icon($group->getIcon(), 'h-3.5 w-3.5') !!}
                            @endif
                            <span class="truncate">{{ $group->getLabel() }}</span>
                        </p>
                    @endif
                    </div>
                @endif

                {{-- `|| railed` is not a nicety. A collapsible group that was
                     folded shut stays folded when the menu narrows — and the
                     heading that would unfold it is hidden in the rail, so the
                     entries under it become unreachable rather than merely
                     hidden. The rail has no folding, so it shows everything. --}}
                <ul
                    id="wire-admin-group-{{ $group->getKey() }}"
                    class="mt-1 space-y-0.5"
                    @if ($group->isCollapsible()) x-show="open || $store.wireAdmin?.railed" x-collapse x-cloak @endif
                >
                    @foreach ($group->getItems() as $key => $item)
                        @include('wire-admin::partials.nav-item', [
                            'item' => $item,
                            'itemKey' => $key,
                            'activeKey' => $activeKey,
                        ])
                    @endforeach
                </ul>
            </div>
        @empty
            {{-- Nothing registered. An empty column reads as a broken menu, so it
                 says which of the two it is. --}}
            <p data-testid="admin-nav-empty" @wireEl('admin-nav-empty') class="px-3 py-2 text-sm text-gray-400 dark:text-gray-500">
                {{ __('wire-admin::messages.empty') }}
            </p>
        @endforelse
    </nav>

    {{-- The foot of the drawer, on a phone only. The top bar owns these two from
         `sm` up and has no room for them below it; the drawer is the one piece of
         shell chrome a phone always has, and it does not depend on whether the
         application passed its own user menu. --}}
    <div class="space-y-2 border-t border-gray-200 p-3 sm:hidden dark:border-gray-800">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('wire-admin::messages.theme_label') }}</span>
            @include('wire-admin::partials.preferences', ['variant' => 'nav', 'only' => 'theme'])
        </div>

        <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('wire-core::messages.density') }}</span>
            @include('wire-admin::partials.preferences', ['variant' => 'nav', 'only' => 'density'])
        </div>
    </div>

    @wireRenderHook('admin.sidebar.end')
</aside>
