{{-- The signed-in person's menu — one definition, drawn in the top bar or at
     the foot of the menu column (`wire-admin.layout.user`).

     Four sources, and all four on either side: the application's own trigger
     (`$user`, written whole), the fallback name and avatar, the application's
     entries (`$userMenu`) and what installed packages registered under
     `PageChrome::USER_MENU` — the profile page, the way out. The registry is
     read here rather than by the layout, so moving the menu cannot drop the
     sign-out.

     Variables: $place ('topbar' | 'sidebar'), $user and $userMenu (slots or null). --}}
@php
    $inSidebar = $place === 'sidebar';
@endphp
@if (isset($user) && $user !== null)
    {{ $user }}
@elseif (auth()->check())
    {{-- In the column the menu opens upwards, from a row as wide as the column;
         in the rail that row is the avatar alone, centred on the icons above it. --}}
    <div @class(['*:w-full' => $inSidebar])>
    <x-wire::dropdown :position="$inSidebar ? 'top-start' : 'bottom-end'" width="w-56" sheet-on-mobile>
        <x-slot:trigger>
            <button
                type="button"
                data-testid="admin-user" @wireEl('admin-user')
                @if ($inSidebar) data-rail-row @endif
                @class([
                    'flex items-center gap-2 transition hover:bg-gray-100 dark:hover:bg-gray-800',
                    'h-9 rounded-full p-1 pe-1.5' => ! $inSidebar,
                    'w-full rounded-lg p-1.5 text-start' => $inSidebar,
                ])
            >
                <x-wire-admin::avatar :user="auth()->user()" class="h-7 w-7 shrink-0" />
                <span
                    @if ($inSidebar) data-rail-hide @endif
                    @class([
                        'truncate text-sm text-gray-700 dark:text-gray-200',
                        'hidden max-w-32 sm:block' => ! $inSidebar,
                        'min-w-0 flex-1' => $inSidebar,
                    ])
                >{{ auth()->user()->name ?? auth()->user()->email ?? '' }}</span>
                <span @if ($inSidebar) data-rail-hide @endif class="shrink-0">{!! icon($inSidebar ? 'outline:chevron-up' : 'outline:chevron-down', 'h-4 w-4 text-gray-500 dark:text-gray-400') !!}</span>
            </button>
        </x-slot:trigger>

        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-700">
            <x-wire-admin::avatar :user="auth()->user()" class="h-9 w-9" />

            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name ?? '' }}</p>
                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email ?? '' }}</p>
            </div>
        </div>

        {{-- Whatever the application puts in the menu, first: an application's
             own entries are not a module's to sort around. The shell itself
             ships no profile page and no sign-out route, because it owns neither. --}}
        {{ $userMenu ?? '' }}

        {{-- And what the installed packages put there: the profile page belongs
             to the users module and the way out to the auth module, and the
             shell can name neither. --}}
        @foreach (app(\NyonCode\WireCore\Foundation\View\PageChrome::class)->views(\NyonCode\WireCore\Foundation\View\PageChrome::USER_MENU) as $userMenuView)
            @include($userMenuView)
        @endforeach
    </x-wire::dropdown>
    </div>
@endif
