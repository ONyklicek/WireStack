{{-- The menu as a bar under the header, from NyonCode\WireAdmin\View\TopNav.

     The same arrangement as the sidebar — Workspace decides the groups, the
     order, the badges and what is reachable; ActiveNavigation decides what is
     marked — drawn as a bar. Below `lg` it is not drawn at all: the drawer is
     the phone's menu in either shape, and two menus on a phone are two copies
     of every row.

     Its own names throughout (`admin-topnav-*`), never `admin-nav-*`: the
     drawer is in the same document, and a test or a driver counting menu rows
     would count every one twice.

     What does not fit is not wrapped onto a second line — that would move the
     page down by a row on every load while the bar measures. The bar is clipped
     from the first paint, and `wireTopNav` hides what overflows and shows it
     under "More" instead. Before it has measured, the only thing out of place
     is a clipped edge, which is the safe way to be wrong. --}}
@include('wire-core::partials.floating-assets')

<nav
    aria-label="{{ __('wire-admin::messages.navigation') }}"
    x-data="wireTopNav"
    data-testid="admin-topnav" @wireEl('admin-topnav')
    class="hidden h-12 items-center gap-2 border-b border-gray-200 bg-white px-4 lg:flex dark:border-gray-800 dark:bg-gray-900"
>
    <ul x-ref="bar" class="flex min-w-0 flex-1 items-center gap-1 overflow-hidden">
        @foreach ($entries as $entry)
            <li data-topnav-entry="{{ $entry['id'] }}" class="shrink-0">
                {{-- A panel for a group, and for a single entry with children of
                     its own: as a plain link its children would have no way in. --}}
                @php
                    $group = $entry['group'];
                    $panelItems = $group?->getItems() ?? ($entry['item']->hasChildren() ? [$entry['key'] => $entry['item']] : null);
                @endphp
                @if ($panelItems !== null)
                    @php
                        $groupActive = collect($panelItems)->contains(fn ($item, $key) => $active->isActive($item, $key) || $active->hasActiveChild($item));
                        $panelLabel = $group ? $group->getLabel() : $entry['item']->getLabel();
                        $panelIcon = $group ? $group->getIcon() : $entry['item']->getIcon();
                    @endphp
                    <div x-data="wireDropdown({ placement: 'bottom-start', offset: 4 })" x-on:keydown.escape.window="open && (close(), $refs.trigger.focus())">
                        <button
                            type="button"
                            x-ref="trigger"
                            x-on:click="toggle()"
                            x-bind:aria-expanded="open ? 'true' : 'false'"
                            data-testid="admin-topnav-group" @wireEl('admin-topnav-group')
                            data-group="{{ $group ? $group->getKey() : $entry['key'] }}"
                            @if ($groupActive) data-active="true" @endif
                            @class([
                                'flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition',
                                'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' => $groupActive,
                                'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' => ! $groupActive,
                            ])
                        >
                            @if ($panelIcon)
                                {!! icon($panelIcon, 'h-4 w-4') !!}
                            @endif
                            <span>{{ $panelLabel }}</span>
                            {!! icon('outline:chevron-down', 'h-3.5 w-3.5 opacity-60') !!}
                        </button>

                        <template x-teleport="body">
                            <div
                                x-ref="panel"
                                x-show="open"
                                x-cloak
                                x-on:click.outside="$clickedInside($event) || close()"
                                data-testid="admin-topnav-panel" @wireEl('admin-topnav-panel')
                                class="absolute top-0 left-0 z-50 w-64 rounded-lg bg-white p-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                                style="display: none;"
                            >
                                @include('wire-admin::partials.top-nav-items', ['items' => $panelItems, 'testid' => 'admin-topnav-item'])
                            </div>
                        </template>
                    </div>
                @else
                    @include('wire-admin::partials.top-nav-link', ['item' => $entry['item'], 'itemKey' => $entry['key'], 'testid' => 'admin-topnav-item', 'inBar' => true])
                @endif
            </li>
        @endforeach
    </ul>

    {{-- Always takes its place, and is only made invisible when nothing
         overflows. Removing it would widen the bar, the widened bar would fit
         everything, and the button would come back — a bar that flickers
         between two widths on every measurement. --}}
    <div
        x-data="wireDropdown({ placement: 'bottom-end', offset: 4 })"
        x-on:keydown.escape.window="open && (close(), $refs.trigger.focus())"
        {{-- Object syntax, which removes a class that is also written
             statically — the string form only adds, and left `invisible` on. --}}
        x-bind:class="{ 'invisible': ! overflow.length }"
        class="invisible shrink-0"
    >
        <button
            type="button"
            x-ref="trigger"
            x-on:click="toggle()"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            x-bind:tabindex="overflow.length ? 0 : -1"
            data-testid="admin-topnav-more" @wireEl('admin-topnav-more')
            class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white"
        >
            {{ __('wire-admin::messages.more') }}
            {!! icon('outline:chevron-down', 'h-3.5 w-3.5 opacity-60') !!}
        </button>

        <template x-teleport="body">
            <div
                x-ref="panel"
                x-show="open"
                x-cloak
                x-on:click.outside="$clickedInside($event) || close()"
                data-testid="admin-topnav-more-panel" @wireEl('admin-topnav-more-panel')
                class="absolute top-0 left-0 z-50 max-h-[70vh] w-64 overflow-y-auto rounded-lg bg-white p-1 shadow-lg ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                style="display: none;"
            >
                @foreach ($entries as $entry)
                    <div x-show="overflow.includes(@js($entry['id']))" data-topnav-more-for="{{ $entry['id'] }}">
                        @if ($entry['group'])
                            <p class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">{{ $entry['group']->getLabel() }}</p>
                            @include('wire-admin::partials.top-nav-items', ['items' => $entry['group']->getItems(), 'testid' => 'admin-topnav-more-item'])
                        @else
                            @include('wire-admin::partials.top-nav-items', ['items' => [$entry['key'] => $entry['item']], 'testid' => 'admin-topnav-more-item'])
                        @endif
                    </div>
                @endforeach
            </div>
        </template>
    </div>
</nav>
