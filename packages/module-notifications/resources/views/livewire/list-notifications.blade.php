{{-- The inbox.

     Its own template rather than a table's, because everything a table's
     chrome offers this screen is something it has to take back: a header row
     over one column, a sort control over one order, a checkbox for a selection
     nobody makes, a page-size select for a reader who scrolls. What is left
     when all of that goes is the list below — and it is shorter than the
     configuration that suppressed it.

     Every branch here is on presentation state the component already resolved:
     which tab is on, which day a run of rows belongs to, whether a row is read,
     where it goes, what can be done about it. --}}
<div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6">

    @if($breadcrumbs)
        <nav class="mb-1 flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500" aria-label="{{ __('wire-core::messages.breadcrumbs') }}">
            @foreach($breadcrumbs as $crumb)
                @if(! $loop->last)
                    <span>{{ $crumb['label'] }}</span><span aria-hidden="true">/</span>
                @endif
            @endforeach
        </nav>
    @endif

    {{-- The page's action sits beside its title, not in a strip above the rows.
         Quiet, because marking everything read is housekeeping — the canon gives
         a screen one primary action and it is the one the screen exists for. --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>

        @if($unreadCount > 0)
            <button
                type="button"
                wire:click="markAllAsRead"
                data-testid="notification-mark-all" @wireEl('notification-mark-all')
                class="inline-flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-sm font-medium text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200"
            >{!! icon('outline:check', 'w-4 h-4') !!}{{ __('wire-module-notifications::messages.mark_all_read') }}</button>
        @endif
    </div>

    {{-- Tabs and search, on the page — not in a panel header. Three tabs rather
         than a Filters button: there are exactly three states an inbox has, and
         a button that opens a panel to choose between three is a button too
         many.

         The row wraps, and when the search box is the thing that wrapped it takes
         the whole line: a 13rem field held to the right of a phone is a field
         with a hand in front of it. --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="inline-flex gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-800" role="group" aria-label="{{ __('wire-module-notifications::messages.state') }}">
            @foreach(['all' => __('wire-core::messages.notifications_all'), 'unread' => __('wire-core::messages.notifications_unread'), 'read' => __('wire-module-notifications::messages.read')] as $key => $label)
                <button
                    type="button"
                    wire:click="setTab('{{ $key }}')"
                    data-testid="notification-tab-{{ $key }}"
                    aria-pressed="{{ $tab === $key ? 'true' : 'false' }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition',
                        'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' => $tab === $key,
                        'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200' => $tab !== $key,
                    ])
                >
                    {{ $label }}
                    @if($key === 'unread' && $unreadCount > 0)
                        <span class="rounded-full bg-red-600 px-1.5 text-[10px] font-bold leading-4 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        <label class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-400 focus-within:border-primary-500 focus-within:ring-2 focus-within:ring-primary-500/20 sm:ml-auto sm:w-auto sm:min-w-[13rem] dark:border-gray-700 dark:bg-gray-800">
            {!! icon('outline:magnifying-glass', 'w-4 h-4') !!}
            <span class="sr-only">{{ __('wire-table::messages.search') }}</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                data-testid="notification-search" @wireEl('notification-search')
                placeholder="{{ __('wire-table::messages.search') }}"
                class="w-full border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white"
            >
        </label>
    </div>

    {{-- The list. Rounded, hairline-divided, and nothing above it. --}}
    <div
        class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
        wire:loading.class="opacity-60"
        wire:target="setTab,search,markAllAsRead,markAsRead,markAsUnread,delete,gotoPage,nextPage,previousPage"
    >
        @forelse($groups as $heading => $rows)
            <p class="sticky top-0 z-10 border-b border-gray-100 bg-gray-50/95 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 backdrop-blur sm:px-5 dark:border-gray-700/60 dark:bg-gray-900/95 dark:text-gray-400">{{ $heading }}</p>

            @foreach($rows as $item)
                @include('wire-module-notifications::livewire.partials.row', ['item' => $item])
            @endforeach
        @empty
            <div class="px-6 py-16">
                @include('wire-core::partials.empty-state', [
                    'icon' => $tab === 'unread' ? 'outline:check-circle' : 'outline:bell-slash',
                    'heading' => match(true) {
                        $search !== '' => __('wire-module-notifications::messages.empty_search'),
                        $tab === 'unread' => __('wire-core::messages.notifications_all_read'),
                        $tab === 'read' => __('wire-module-notifications::messages.empty_read'),
                        default => __('wire-module-notifications::messages.empty_heading'),
                    },
                    'description' => $search !== ''
                        ? __('wire-module-notifications::messages.empty_search_hint')
                        : ($tab === 'all' ? __('wire-module-notifications::messages.empty_description') : null),
                ])
            </div>
        @endforelse
    </div>

    {{-- Paging only when there is more than one page. A count under a list of
         four is the last thing that made this read as a table. --}}
    @if($page->hasPages())
        <div class="mt-4">{{ $page->links() }}</div>
    @endif
</div>
