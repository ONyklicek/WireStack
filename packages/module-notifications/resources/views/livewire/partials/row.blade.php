{{-- One notification.

     The tile is the row's anchor and the only colour on the screen; unread is
     said three ways — tone, weight and an edge — because colour alone is a thing
     not every reader receives. The verbs live behind one quiet trigger that
     appears on hover and on keyboard focus, so a list of thirty is a list and
     not a wall of buttons. --}}
<div
    wire:key="notification-{{ $item['id'] }}"
    data-testid="notification-item" @wireEl('notification-item')
    x-data="{ open: false }"
    @class([
        'group relative flex items-start gap-3.5 border-b border-gray-100 px-4 py-3.5 transition last:border-b-0 sm:px-5 dark:border-gray-700/60',
        'hover:bg-gray-50 dark:hover:bg-gray-700/30' => $item['read'],
        'border-l-[3px] border-l-primary-500 bg-primary-50/70 hover:bg-primary-50 dark:border-l-primary-400 dark:bg-primary-500/10 dark:hover:bg-primary-500/15' => ! $item['read'],
    ])
>
    <span class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] {{ $item['tile'] }}">{!! icon($item['icon'], 'w-[18px] h-[18px]') !!}</span>

    <div class="min-w-0 flex-1">
        {{-- A real anchor when the notification goes somewhere, so the link can be
             copied and middle-clicked — while the plain left click goes through
             the server, which is what marks it read on the way past. --}}
        @if($item['url'])
            <a
                href="{{ $item['url'] }}"
                wire:click.prevent="open('{{ $item['id'] }}')"
                data-testid="notification-open" @wireEl('notification-open')
                class="block rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
            >
                @include('wire-module-notifications::livewire.partials.text', ['item' => $item])
            </a>
        @else
            @include('wire-module-notifications::livewire.partials.text', ['item' => $item])
        @endif

        @if($item['actions'])
            <div class="mt-2.5 flex flex-wrap items-center gap-2">
                @foreach($item['actions'] as $action)
                    @if(! empty($action['url']))
                        <a href="{{ $action['url'] }}" data-testid="notification-action" @wireEl('notification-action') class="{{ $action['classes'] }}">{{ $action['label'] }}</a>
                    @elseif(! empty($action['event']))
                        <button
                            type="button"
                            x-on:click="window.Livewire.dispatch(@js($action['event']), @js($action['payload'] ?? []))"
                            data-testid="notification-action" @wireEl('notification-action')
                            class="{{ $action['classes'] }}"
                        >{{ $action['label'] }}</button>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    {{-- Unread, said once more for a reader who cannot see the tone. --}}
    @unless($item['read'])
        <span class="sr-only">{{ __('wire-core::messages.notifications_unread') }}</span>
    @endunless

    <div class="relative shrink-0" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
        <button
            type="button"
            x-on:click="open = ! open"
            :aria-expanded="open ? 'true' : 'false'"
            data-testid="notification-menu" @wireEl('notification-menu')
            aria-haspopup="true"
            class="rounded-md p-1.5 text-gray-400 opacity-0 transition hover:bg-gray-100 hover:text-gray-600 focus-visible:opacity-100 group-hover:opacity-100 group-focus-within:opacity-100 dark:hover:bg-gray-700 dark:hover:text-gray-200"
            aria-label="{{ __('wire-module-notifications::messages.more_actions') }}"
        >{!! icon('outline:ellipsis-horizontal', 'w-4 h-4') !!}</button>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.120ms
            class="absolute right-0 z-20 mt-1 w-56 rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-600 dark:bg-gray-700"
            role="menu"
        >
            @if($item['read'])
                <button type="button" role="menuitem" wire:click="markAsUnread('{{ $item['id'] }}')" x-on:click="open = false" data-testid="notification-mark-unread" @wireEl('notification-mark-unread') class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600">
                    {!! icon('outline:arrow-uturn-left', 'w-4 h-4') !!}{{ __('wire-module-notifications::messages.mark_unread') }}
                </button>
            @else
                <button type="button" role="menuitem" wire:click="markAsRead('{{ $item['id'] }}')" x-on:click="open = false" data-testid="notification-mark-read" @wireEl('notification-mark-read') class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-600">
                    {!! icon('outline:check', 'w-4 h-4') !!}{{ __('wire-core::messages.mark_read') }}
                </button>
            @endif

            <hr class="my-1 border-gray-100 dark:border-gray-600">

            <button type="button" role="menuitem" wire:click="delete('{{ $item['id'] }}')" x-on:click="open = false" data-testid="notification-delete" @wireEl('notification-delete') class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30">
                {!! icon('outline:trash', 'w-4 h-4') !!}{{ __('wire-core::messages.delete_notification') }}
            </button>
        </div>
    </div>
</div>
