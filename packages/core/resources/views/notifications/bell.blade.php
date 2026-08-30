{{-- Notification bell: unread count, the latest few, and two ways to clear them. --}}
<div class="wire-notification-bell relative" x-data="{ open: false }" @keydown.escape.window="open = false">
    <button
        type="button"
        x-on:click="open = ! open"
        data-testid="notification-bell"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="true"
        class="relative inline-flex items-center rounded-md p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
    >
        {!! icon('outline:bell', 'w-5 h-5') !!}
        <span class="sr-only">{{ __('wire-core::messages.notifications') }}</span>

        @if($unreadCount > 0)
            <span
                data-testid="notification-bell-count"
                class="absolute -top-0.5 -right-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold leading-4 text-white"
            >{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>

    <div
        x-show="open"
        x-on:click.outside="open = false"
        x-cloak
        data-testid="notification-bell-panel"
        class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2 dark:border-gray-700">
            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ __('wire-core::messages.notifications') }}</span>

            @if($unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    data-testid="notification-mark-all"
                    class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                >{{ __('wire-core::messages.mark_all_read') }}</button>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto">
            @forelse($notifications as $notification)
                <div
                    wire:key="notification-{{ $notification->id }}"
                    data-testid="notification-item"
                    @class([
                        'flex items-start gap-3 border-b border-gray-100 px-4 py-3 last:border-b-0 dark:border-gray-700/50',
                        'bg-primary-50/50 dark:bg-primary-900/10' => ! $notification->isRead(),
                    ])
                >
                    <div class="min-w-0 flex-1">
                        @if($notification->data['title'] ?? null)
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $notification->data['title'] }}</p>
                        @endif
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $notification->data['message'] ?? '' }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ $notification->created_at?->diffForHumans() }}</p>
                    </div>

                    @if(! $notification->isRead())
                        <button
                            type="button"
                            wire:click="markAsRead('{{ $notification->id }}')"
                            data-testid="notification-mark-read"
                            class="shrink-0 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            aria-label="{{ __('wire-core::messages.mark_read') }}"
                        >{!! icon('outline:check', 'w-4 h-4') !!}</button>
                    @endif
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                    {{ __('wire-core::messages.no_notifications') }}
                </p>
            @endforelse
        </div>
    </div>
</div>
