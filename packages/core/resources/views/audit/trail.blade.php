{{-- Audit Trail Timeline --}}
@props([
    'entries' => [],
    'emptyMessage' => __('wire-core::audit.no_entries'),
])

<div class="space-y-1">
    @forelse($entries as $entry)
        <div class="relative flex gap-x-4 pb-6 last:pb-0">
            {{-- Timeline connector --}}
            @unless($loop->last)
                <div class="absolute left-3 top-8 -bottom-2 w-px bg-gray-200 dark:bg-gray-700"></div>
            @endunless

            {{-- Event icon --}}
            <div class="relative flex h-6 w-6 flex-none items-center justify-center">
                @switch($entry->event)
                    @case('created')
                        <div class="h-5 w-5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 flex items-center justify-center">
                            {!! icon('outline:plus', 'h-3 w-3', 'text-emerald-600 dark:text-emerald-400') !!}
                        </div>
                        @break
                    @case('updated')
                    @case('cell_updated')
                        <div class="h-5 w-5 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                            {!! icon('outline:pencil', 'h-3 w-3', 'text-blue-600 dark:text-blue-400') !!}
                        </div>
                        @break
                    @case('deleted')
                        <div class="h-5 w-5 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center">
                            {!! icon('outline:trash', 'h-3 w-3', 'text-red-600 dark:text-red-400') !!}
                        </div>
                        @break
                    @case('bulk_action')
                        <div class="h-5 w-5 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                            {!! icon('outline:queue-list', 'h-3 w-3', 'text-amber-600 dark:text-amber-400') !!}
                        </div>
                        @break
                    @default
                        <div class="h-5 w-5 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                            <div class="h-1.5 w-1.5 rounded-full bg-gray-400"></div>
                        </div>
                @endswitch
            </div>

            {{-- Event content --}}
            <div class="flex-auto">
                <div class="flex items-baseline justify-between gap-x-4">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        @if($entry->user)
                            {{ $entry->user->name ?? __('wire-core::audit.unknown_user') }}
                        @else
                            <span class="text-gray-500 dark:text-gray-400">{{ __('wire-core::audit.system') }}</span>
                        @endif

                        <span class="font-normal text-gray-500 dark:text-gray-400">
                            {{ __('wire-core::audit.event_' . $entry->event) }}
                        </span>
                    </p>

                    <time
                        datetime="{{ $entry->created_at->toIso8601String() }}"
                        class="flex-none text-xs text-gray-500 dark:text-gray-400"
                        title="{{ $entry->created_at->format('Y-m-d H:i:s') }}"
                    >
                        {{ $entry->created_at->diffForHumans() }}
                    </time>
                </div>

                {{-- Changes diff, from the one partial that draws a diff in this
                     stack. It used to be this table written out here as well as
                     on the audit entry's own page, and the two had already
                     drifted: this copy printed a boolean `true` as `1`, because
                     the value rule lived in a Blade ternary rather than in
                     ChangeSet. --}}
                @php($changes = \NyonCode\WireCore\Foundation\ValueObjects\ChangeSet::fromDiff($entry->getChangeDiff()))
                @unless($changes->isEmpty())
                    <div class="mt-2">
                        @include('wire-core::partials.change-table', ['rows' => $changes->rows, 'dense' => true])
                    </div>
                @endunless

                {{-- Metadata (IP) --}}
                @if(!empty($entry->metadata['ip']))
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        IP: {{ $entry->metadata['ip'] }}
                    </p>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-8">
            {!! icon('outline:clock', 'h-8 w-8', 'mx-auto text-gray-400 dark:text-gray-500') !!}
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
        </div>
    @endforelse
</div>
