{{-- The button that collapses the menu into its rail — in the top bar or at the
     very foot of the menu column (`wire-admin.layout.rail_toggle`). ⌘B is not
     bound here: it belongs to the page once (layout.blade.php), so it works
     wherever this sits and is never bound twice.

     Says which way it goes, rather than naming one direction for both states:
     a handle labelled "collapse the menu" while the menu is already collapsed
     is the control people press twice to find out what it does. The title is
     bound only — a static one beside it offered "Collapse menu" over a
     collapsed rail until Alpine had started.

     Variables: $place ('topbar' | 'sidebar'). --}}
@php
    $inSidebar = $place === 'sidebar';
@endphp
<button
    type="button"
    x-data
    x-on:click="$store.wireAdmin.toggleRail()"
    x-bind:aria-pressed="$store.wireAdmin.rail ? 'true' : 'false'"
    x-bind:aria-keyshortcuts="$store.wireAdmin.modKey === '⌘' ? 'Meta+B' : 'Control+B'"
    x-bind:title="($store.wireAdmin.rail
        ? '{{ __('wire-admin::messages.expand_menu') }}'
        : '{{ __('wire-admin::messages.collapse_menu') }}') + ' (' + $store.wireAdmin.modKey + 'B)'"
    data-testid="admin-rail-toggle" @wireEl('admin-rail-toggle')
    @if ($inSidebar) data-rail-row @endif
    @class([
        'hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:inline-flex dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200',
        '-ms-1' => ! $inSidebar,
        'w-full items-center gap-2 text-sm' => $inSidebar,
    ])
>
    <span class="sr-only" x-show="! $store.wireAdmin.rail">{{ __('wire-admin::messages.collapse_menu') }}</span>
    <span class="sr-only" x-show="$store.wireAdmin.rail" x-cloak>{{ __('wire-admin::messages.expand_menu') }}</span>
    @if ($inSidebar)
        <span x-show="! $store.wireAdmin.rail" class="shrink-0">{!! icon('outline:chevron-double-left', 'h-5 w-5 rtl:rotate-180') !!}</span>
    @else
        <span x-show="! $store.wireAdmin.rail">{!! icon('outline:bars-3', 'h-5 w-5') !!}</span>
    @endif
    <span x-show="$store.wireAdmin.rail" x-cloak class="shrink-0">{!! icon('outline:chevron-double-right', 'h-5 w-5 rtl:rotate-180') !!}</span>
    @if ($inSidebar)
        <span data-rail-hide aria-hidden="true">{{ __('wire-admin::messages.collapse_menu') }}</span>
    @endif
</button>
