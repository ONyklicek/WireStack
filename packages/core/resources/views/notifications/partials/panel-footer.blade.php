{{-- The two bulk verbs on the left, the way out on the right — and each is
     absent rather than disabled when it has nothing to do: there is nothing to
     mark when nothing is unread, nothing to clear when nothing has been read,
     and no list to link to when no page package routes the `notifications` key.

     Deliberately no "delete everything": what has been read is what the user has
     already seen, so clearing it cannot lose them something they have not looked
     at — and a button that can is a button an inbox should not have. --}}
<div class="flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
        @if($unreadCount > 0)
            <button
                type="button"
                wire:click="markAllAsRead"
                data-testid="notification-mark-all" @wireEl('notification-mark-all')
                class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
            >{{ __('wire-core::messages.mark_all_read') }}</button>
        @endif

        @if($hasRead)
            <button
                type="button"
                wire:click="clearRead"
                data-testid="notification-clear-read" @wireEl('notification-clear-read')
                class="text-sm font-medium text-gray-500 hover:underline dark:text-gray-400"
            >{{ __('wire-core::messages.clear_read') }}</button>
        @endif
    </div>

    @if($indexUrl)
        <a
            href="{{ $indexUrl }}"
            wire:navigate
            data-testid="notification-view-all" @wireEl('notification-view-all')
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            {{ __('wire-core::messages.view_all_notifications') }}
            {!! icon('outline:arrow-right', 'w-4 h-4') !!}
        </a>
    @endif
</div>
