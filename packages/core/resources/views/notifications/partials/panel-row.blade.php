{{-- One row of the panel. Its own partial because the day grouping wraps runs of
     them, and a row written inline inside two nested loops is a row nobody can
     read. --}}
<div
    wire:key="notification-{{ $item['id'] }}"
    data-testid="notification-item"
    @class([
        'group relative flex items-start gap-3 px-4 py-3 transition-colors sm:px-6',
        'hover:bg-gray-50 dark:hover:bg-gray-700/30' => $item['read'],
        'bg-primary-50/40 hover:bg-primary-50/70 dark:bg-primary-900/10 dark:hover:bg-primary-900/20' => ! $item['read'],
    ])
>
    <span class="mt-0.5 shrink-0 {{ $item['tint'] }}">{!! icon($item['icon'], 'w-5 h-5') !!}</span>

    <div class="min-w-0 flex-1">
        {{-- A real anchor when the notification goes somewhere, so the link can
             be copied, middle-clicked and read by anything that reads links —
             while the plain left click goes through the server, which is what
             marks it read on the way past. Without a destination it is the same
             text, unwrapped. --}}
        @if($item['url'])
            <a
                href="{{ $item['url'] }}"
                wire:click.prevent="open('{{ $item['id'] }}')"
                data-testid="notification-open"
                class="block rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                @include('wire-core::notifications.partials.panel-item', ['item' => $item])
            </a>
        @else
            @include('wire-core::notifications.partials.panel-item', ['item' => $item])
        @endif

        @if($item['actions'])
            <div class="mt-2 flex flex-wrap items-center gap-2">
                @foreach($item['actions'] as $action)
                    @include('wire-core::notifications.partials.panel-action', ['action' => $action])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Unread, said twice on purpose: a dot for a reader who cannot see the
         tinted row, and text for one who cannot see either. The dot gives way to
         the verbs on hover — the same corner, and only one of them is useful at
         a time. --}}
    @unless($item['read'])
        <span
            aria-hidden="true"
            data-testid="notification-unread-dot"
            class="mt-2 h-2 w-2 shrink-0 rounded-full bg-primary-500 transition-opacity group-hover:opacity-0 group-focus-within:opacity-0 dark:bg-primary-400"
        ></span>
        <span class="sr-only">{{ __('wire-core::messages.notifications_unread') }}</span>
    @endunless

    {{-- The two verbs, on hover so a list of ten is a list and not a wall of
         buttons. `focus-within` keeps them reachable by keyboard, where hover is
         not a thing that happens. --}}
    <div @class([
        'absolute right-3 top-2 flex items-center gap-0.5 rounded-md bg-white/90 p-0.5 opacity-0 shadow-sm ring-1 ring-gray-200 backdrop-blur transition-opacity',
        'group-hover:opacity-100 group-focus-within:opacity-100 sm:right-5 dark:bg-gray-800/90 dark:ring-gray-600',
    ])>
        @if($item['read'])
            <button
                type="button"
                wire:click="markAsUnread('{{ $item['id'] }}')"
                data-testid="notification-mark-unread"
                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                aria-label="{{ __('wire-core::messages.mark_unread') }}"
            >{!! icon('outline:arrow-uturn-left', 'w-4 h-4') !!}</button>
        @else
            <button
                type="button"
                wire:click="markAsRead('{{ $item['id'] }}')"
                data-testid="notification-mark-read"
                class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                aria-label="{{ __('wire-core::messages.mark_read') }}"
            >{!! icon('outline:check', 'w-4 h-4') !!}</button>
        @endif

        <button
            type="button"
            wire:click="delete('{{ $item['id'] }}')"
            data-testid="notification-delete"
            class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/30 dark:hover:text-red-400"
            aria-label="{{ __('wire-core::messages.delete_notification') }}"
        >{!! icon('outline:trash', 'w-4 h-4') !!}</button>
    </div>
</div>
