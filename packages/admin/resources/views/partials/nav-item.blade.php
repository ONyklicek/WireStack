@php use NyonCode\WireCore\Foundation\View\Badge; @endphp
{{-- One row of the menu, and the list under it when it has children.

     Included rather than inlined in the sidebar because a row is drawn in two
     places — the top level and, without its own children, one level down — and
     the two must not drift into looking different.

     **A tooltip and a menu are not the same object.** In the rail the label is
     hidden and a row with children has nowhere to put them, so both come back on
     hover — but as *different things*, which is the whole of what an earlier
     attempt got wrong. It gave every row the same menu-sized card, so pointing
     at an entry with no children answered "what is this icon?" with a panel
     holding one word. SAP Fiori's side navigation states the rule this follows:
     collapsed, "a tooltip with the corresponding label pops up on-hover", and
     "subitems appear in a popover". A label is a tooltip. A list is a menu.

       - no children → a tooltip. One line, dark, unmistakably not a menu.
       - children    → a popover, holding the same child rows the wide menu draws
                       under the parent.

     "The same child rows" is meant literally: the popover renders them through
     this very partial, so how a child row looks has one definition. The earlier
     version hand-wrote a second copy of it — url, active state, badge colour,
     the disabled case — which is a copy that diverges the first time either side
     is touched.

     What the rail still has to answer for on its own is a row with no label
     beside it: the accessible name, and a badge that has nowhere to sit. Both are
     below, and both are the row's own — not a second copy of it. --}}
@php($url = $item->getUrl())
@php($isChild = $child ?? false)
@php($children = $isChild ? [] : $item->getChildren())
@php($isActive = isset($itemKey) && $itemKey === $activeKey)
@php($isCurrent = $url !== null && rtrim($url, '/') === rtrim(url()->current(), '/'))
@php($hasActiveChild = collect($children)->contains(fn ($c) => $c->getUrl() !== null && rtrim((string) $c->getUrl(), '/') === rtrim(url()->current(), '/')))
@php($isActive = $isActive || ($isCurrent && ! isset($itemKey)))
{{-- Resolved once, for both places a badge is drawn — the pill beside the label,
     and the dot on the icon when there is no label to sit beside. Written twice
     it drifted the moment the dot fell back to `primary` and the pill to `gray`:
     the same count changed colour for being narrow. --}}
@php($badgeColor = $item->getBadgeColor() ?? 'gray')

@php($panelId = 'wire-admin-flyout-'.md5(($itemKey ?? '').'|'.($item->getLabel() ?? '')))

<li
    @if ($children)
        x-data="{
            expanded: {{ $hasActiveChild ? 'true' : 'false' }},
            key: 'wire-admin.sub.{{ md5($item->getLabel() ?? '') }}',
            init() {
                const stored = window.localStorage?.getItem(this.key);

                // A child of this branch being the current page wins over what
                // was stored: a menu that folds away the page you are on is a
                // menu that has lost track of where you are.
                if (stored !== null && ! {{ $hasActiveChild ? 'true' : 'false' }}) this.expanded = stored === '1';

                this.$watch('expanded', (value) => window.localStorage?.setItem(this.key, value ? '1' : '0'));
            },
        }"
    @endif
>
    {{-- Top-level rows only. A child is drawn inside the popover or inside the
         expanded submenu, and neither has a width problem of its own. --}}
    <div
        @if (! $isChild)
            {{-- Which side it opens on is the document's, not this file's: in an
                 RTL locale the column is on the right and a panel that still flew
                 right would open over the page it belongs beside. Read from the
                 computed direction rather than the `dir` attribute, because an
                 application that sets the direction in CSS sets no attribute. --}}
            x-data="wireFlyout({
                placement: getComputedStyle(document.documentElement).direction === 'rtl' ? 'left-start' : 'right-start',
                offset: 0,
            })"
            {{-- Hover opens it, and the condition is passed in rather than read
                 by the controller: whether a flyout applies at all is this
                 surface's question, and a controller that reached into
                 `$store.wireAdmin` would only ever work in a sidebar. --}}
            x-on:mouseenter="enter($store.wireAdmin?.railed)"
            x-on:mouseleave="leave()"
            {{-- Keyboard reaches it too. Tabbing onto the row in the rail is the
                 same act as pointing at it, and `focusin`/`focusout` bubble from
                 the row, which `focus` does not. --}}
            x-on:focusin="enter($store.wireAdmin?.railed)"
            x-on:focusout="leave()"
            {{-- `dismiss()` rather than `close()`: focus goes back to a row that
                 opens on focus, so a plain close is a close and an immediate
                 reopen, and the key reads as doing nothing. --}}
            x-on:keydown.escape="open && (dismiss(), $refs.trigger?.focus())"
            data-testid="admin-nav-row" @wireEl('admin-nav-row')
        @endif
        class="relative"
    >
    <{{ $children ? 'button' : 'a' }}
        @if (! $isChild) x-ref="trigger" @endif
        @if ($children)
            type="button"
            {{-- In the rail the click owns the popover; in the wide menu it owns
                 the disclosure. What it must not do is what it used to: re-open
                 the entire menu, which answered "show me what is under this" by
                 undoing the collapse the user had just asked for. --}}
            x-on:click="$store.wireAdmin?.railed
                ? (toggle(), open && $event.detail === 0 && $nextTick(() => $refs.panel?.querySelector('a')?.focus()))
                : (expanded = ! expanded)"
            x-bind:aria-expanded="$store.wireAdmin?.railed ? (open ? 'true' : 'false') : (expanded ? 'true' : 'false')"
            {{-- Written twice on purpose: the static value is what the document
                 ships with and is right for the menu as rendered, and the binding
                 re-points it at the popover the moment the same button starts
                 opening that instead. --}}
            aria-controls="wire-admin-sub-{{ md5($item->getLabel() ?? '') }}"
            x-bind:aria-controls="$store.wireAdmin?.railed ? '{{ $panelId }}' : 'wire-admin-sub-{{ md5($item->getLabel() ?? '') }}'"
        @elseif ($url)
            href="{{ $url }}"
            wire:navigate
            x-on:click="$store.wireAdmin.closeMobile()"
        @else
            aria-disabled="true"
        @endif
        @if (! $isChild)
            {{-- The label below is `display: none` in the rail, and a hidden
                 element carries no accessible name — so every row would announce
                 itself as its own badge, or as nothing at all. Stated on the
                 element instead, where the width cannot take it away. --}}
            aria-label="{{ $item->getLabel() }}"
        @endif
        @if (isset($itemKey))
            data-testid="admin-nav-item" @wireEl('admin-nav-item')
            data-resource="{{ $itemKey }}"
        @else
            data-testid="admin-nav-child" @wireEl('admin-nav-child')
        @endif
        @if ($isActive || $hasActiveChild) data-active="true" @endif
        @if ($isActive) aria-current="page" @endif
        @if (! $isChild) data-rail-row @endif
        @class([
            'group relative flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm transition max-sm:py-2.5',
            'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' => $isActive,
            'font-medium text-gray-900 dark:text-gray-100' => $hasActiveChild && ! $isActive,
            'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' => ! $isActive,
            'cursor-default opacity-60 hover:bg-transparent' => ! $url && ! $children,
        ])
    >
        {{-- "You are here", against the column's own edge rather than inside the
             row. The tinted background says it too — but a tint is the same shape
             as a hover, and in a rail of nine identical squares that is the whole
             of the difference. This is the mark you can find without reading
             anything.

             A parent whose *child* is the page counts. In the wide menu the open
             child list says where you are; in the rail there is no list. --}}
        @if (($isActive || $hasActiveChild) && ! $isChild)
            <span
                aria-hidden="true"
                data-testid="admin-nav-active-mark" @wireEl('admin-nav-active-mark')
                class="bg-primary-600 dark:bg-primary-400 absolute inset-y-1.5 -start-3 w-1 rounded-e-full"
            ></span>
        @endif

        {{-- Top-level rows always draw something here, icon or not: the rail
             hides every label, so an entry that declared no icon would collapse
             to an empty 64-pixel row with nothing to aim at. Children are exempt
             — they are never drawn in the rail, and an initial beside each one
             would be noise in a submenu that already sits under its parent. --}}
        @if ($item->getIcon() || isset($itemKey))
            {{-- The badge is positioned against this, not against the row: in the
                 rail there is no room beside the icon, and a badge that took some
                 pushed the icon off centre — which is what a collapsed menu looks
                 wrong for before anyone can say why. --}}
            <span class="relative shrink-0">
                @if ($item->getIcon())
                    {!! icon($item->getIcon(), 'h-5 w-5 '.($isActive ? 'text-primary-600 dark:text-primary-300' : 'text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300')) !!}
                @else
                    <span @class([
                        'flex h-5 w-5 items-center justify-center rounded-sm text-[10px] font-semibold',
                        'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-200' => $isActive,
                        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => ! $isActive,
                    ])>{{ mb_strtoupper(mb_substr((string) $item->getLabel(), 0, 1)) }}</span>
                @endif

                @if ($item->getBadge())
                    {{-- The same colour the pill carries, from the canonical
                         solid-fill resolver rather than a hard-coded
                         `bg-primary-600`. A count that is red because it is
                         overdue must not turn brand-blue for being narrow —
                         colour is the only thing a dot this small still says. --}}
                    <span
                        data-rail-only
                        data-testid="admin-nav-badge-dot" @wireEl('admin-nav-badge-dot')
                        class="{{ Badge::getSolidBgClass($badgeColor) }} absolute -end-1.5 -top-1.5 min-w-[1rem] rounded-full px-1 text-center text-[10px] leading-4 font-semibold text-white ring-2 ring-white dark:ring-gray-900"
                    >{{ $item->getBadge() }}</span>
                @endif
            </span>
        @endif

        <span
            @if (! $isChild) data-rail-hide @endif
            class="flex-1 truncate text-start"
            data-testid="admin-nav-label" @wireEl('admin-nav-label')
        >{{ $item->getLabel() }}</span>

        @if ($item->getBadge())
            <span @if (! $isChild) data-rail-hide @endif>
                <x-wire::badge
                    :color="$badgeColor"
                    data-testid="admin-nav-badge"
                >{{ $item->getBadge() }}</x-wire::badge>
            </span>
        @endif

        @if ($children)
            <span
                data-rail-hide
                x-bind:class="expanded || '-rotate-90'"
                class="text-gray-400 transition"
            >{!! icon('outline:chevron-down', 'h-4 w-4 rtl:-scale-x-100') !!}</span>
        @endif
    </{{ $children ? 'button' : 'a' }}>

        @if (! $isChild)
            {{-- What the rail took away, given back beside the column.

                 Teleported to `<body>` because the nav it would otherwise live in
                 is `overflow-x-hidden overflow-y-auto` — a panel drawn in place
                 would be clipped at 64 pixels, which is the whole problem it
                 exists to solve. Everything about *where* it lands is the
                 canonical floating primitive's; this file only says what is in
                 it.

                 The `ps-5` is not spacing, it is the bridge — which is also why
                 the anchor sits at `offset: 0`. Floating UI pins this *padded*
                 box against the row, so the visible surface stands clear of the
                 rail while the whole way to it stays one hoverable element. Any
                 gap between the two is a strip where the pointer is over neither
                 and the panel closes mid-reach. --}}
            <template x-teleport="body">
                <div
                    x-ref="panel"
                    id="{{ $panelId }}"
                    x-show="open"
                    x-cloak
                    x-on:mouseenter="enter()"
                    x-on:mouseleave="leave()"
                    {{-- Focus crossing into the panel is the same event as the
                         pointer crossing into it, and for the same reason: the
                         panel is teleported, so focus arriving here has *left*
                         the trigger's subtree and the row's `focusout` has
                         already scheduled the close. --}}
                    x-on:focusin="enter()"
                    x-on:focusout="leave()"
                    x-on:keydown.escape.stop="dismiss(), $refs.trigger?.focus()"
                    x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
                    x-transition:enter-start="opacity-0 -translate-x-1 scale-95 rtl:translate-x-1"
                    x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                    x-transition:leave="transition ease-in duration-100 motion-reduce:transition-none"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    data-testid="{{ $children ? 'admin-nav-flyout' : 'admin-nav-tip' }}"
                    @if (isset($itemKey)) data-resource="{{ $itemKey }}" @endif
                    class="absolute top-0 left-0 z-50 origin-left ps-5 rtl:origin-right"
                    style="display: none;"
                >
                    @if ($children)
                        {{-- A menu, because what is in it is a list. Same ring and
                             shadow as every other floating panel in the framework:
                             this is the collapsed menu's dropdown and should not
                             read as a different kind of object from the action menu
                             two inches away. --}}
                        <div class="max-w-72 min-w-52 rounded-xl bg-white p-1.5 shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                            {{-- The parent's own name, as the heading of its list
                                 rather than as a row you might click by accident.
                                 It links only when the parent is itself a page. --}}
                            <{{ $url ? 'a' : 'p' }}
                                @if ($url)
                                    href="{{ $url }}"
                                    wire:navigate
                                    x-on:click="close()"
                                @endif
                                data-testid="admin-nav-flyout-label" @wireEl('admin-nav-flyout-label')
                                @class([
                                    'flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold tracking-wider uppercase',
                                    'text-gray-400 dark:text-gray-500' => ! $isActive,
                                    'text-primary-600 dark:text-primary-400' => $isActive,
                                    'transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700/70 dark:hover:text-gray-300' => (bool) $url,
                                ])
                            >
                                <span class="flex-1 truncate">{{ $item->getLabel() }}</span>
                            </{{ $url ? 'a' : 'p' }}>

                            {{-- The children, drawn by the same partial that draws
                                 them under the parent in the wide menu. A second
                                 hand-written copy is what this replaced, and a copy
                                 diverges the first time either side is touched. --}}
                            <ul class="mt-0.5 space-y-0.5" x-on:click="close()">
                                @foreach ($children as $sub)
                                    @include('wire-admin::partials.nav-item', [
                                        'item' => $sub,
                                        'itemKey' => null,
                                        'activeKey' => $activeKey,
                                        'child' => true,
                                    ])
                                @endforeach
                            </ul>
                        </div>
                    @else
                        {{-- A tooltip, because what is in it is a word. Dark, one
                             line, no padding of a menu and no icon: whatever this
                             is, it must not be mistakable for something you can
                             open. --}}
                        <span
                            role="tooltip"
                            class="block rounded-md bg-gray-900 px-2 py-1 text-xs font-medium whitespace-nowrap text-white shadow-lg dark:bg-gray-700"
                        >{{ $item->getLabel() }}</span>
                    @endif
                </div>
            </template>
        @endif
    </div>

    @if ($children)
        <ul
            id="wire-admin-sub-{{ md5($item->getLabel() ?? '') }}"
            {{-- Plain `x-show`, no `x-collapse`. Collapse animates a height and
                 only then hides, so folding the rail left the first child row
                 showing as a stray sliver under the icon for the length of the
                 animation — and that is exactly the moment the menu is being
                 looked at. --}}
            {{-- Two questions, two owners. Whether this branch is *folded* is
                 runtime and Alpine's; whether the menu is a rail is CSS's, from
                 `<html data-rail>`, like every other thing the width hides. It
                 used to ask the store for both, and the store's answer is a
                 second statement of the media query rather than the same one —
                 so the two could disagree, and a disagreement rendered this list
                 inside the 64-pixel column, where `overflow-x-hidden` showed it
                 as a stray vertical rule and a sliver of a highlighted row. --}}
            data-rail-hide
            x-show="expanded"
            x-cloak
            class="ms-[1.4rem] mt-0.5 space-y-0.5 border-s border-gray-200 ps-2 dark:border-gray-800"
        >
            @foreach ($children as $sub)
                @include('wire-admin::partials.nav-item', [
                    'item' => $sub,
                    'itemKey' => null,
                    'activeKey' => $activeKey,
                    'child' => true,
                ])
            @endforeach
        </ul>
    @endif
</li>
