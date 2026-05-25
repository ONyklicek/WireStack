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
                            <svg class="h-3 w-3 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                        </div>
                        @break
                    @case('updated')
                    @case('cell_updated')
                        <div class="h-5 w-5 rounded-full bg-blue-100 dark:bg-blue-900/50 flex items-center justify-center">
                            <svg class="h-3 w-3 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Z" />
                            </svg>
                        </div>
                        @break
                    @case('deleted')
                        <div class="h-5 w-5 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center">
                            <svg class="h-3 w-3 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </div>
                        @break
                    @case('bulk_action')
                        <div class="h-5 w-5 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center">
                            <svg class="h-3 w-3 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
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

                {{-- Changes diff --}}
                @php($changes = $entry->getChanges())
                @if(!empty($changes))
                    <div class="mt-2 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('wire-core::audit.field') }}</th>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('wire-core::audit.old_value') }}</th>
                                    <th class="px-3 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">{{ __('wire-core::audit.new_value') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($changes as $field => $diff)
                                    <tr>
                                        <td class="px-3 py-1.5 font-medium text-gray-700 dark:text-gray-300">{{ $field }}</td>
                                        <td class="px-3 py-1.5 text-red-600 dark:text-red-400">
                                            @if($diff['old'] !== null)
                                                <span class="bg-red-50 dark:bg-red-900/20 px-1 rounded">{{ is_array($diff['old']) ? json_encode($diff['old']) : $diff['old'] }}</span>
                                            @else
                                                <span class="text-gray-400 italic">{{ __('wire-core::audit.empty') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-1.5 text-emerald-600 dark:text-emerald-400">
                                            @if($diff['new'] !== null)
                                                <span class="bg-emerald-50 dark:bg-emerald-900/20 px-1 rounded">{{ is_array($diff['new']) ? json_encode($diff['new']) : $diff['new'] }}</span>
                                            @else
                                                <span class="text-gray-400 italic">{{ __('wire-core::audit.empty') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

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
            <svg class="mx-auto h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $emptyMessage }}</p>
        </div>
    @endforelse
</div>
