{{-- Polling Indicator --}}
@php
    $pollingConfig = $component->getTablePollingConfig();
@endphp

@if($pollingConfig['enabled'] ?? false)
    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
        @if($pollingConfig['active'] ?? false)
            {{-- Active indicator --}}
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
            </span>
            <span>Auto {{ $pollingConfig['interval'] ?? '5s' }}</span>
        @else
            {{-- Paused indicator --}}
            <span class="relative flex h-2 w-2">
                <span class="relative inline-flex rounded-full h-2 w-2 bg-gray-400"></span>
            </span>
            <span>{{ __('wire-table::messages.paused') }}</span>
        @endif

        <button
            type="button"
            wire:click="toggleTablePolling"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 underline decoration-dotted underline-offset-2"
        >
            {{ ($pollingConfig['active'] ?? false) ? __('wire-table::messages.stop') : __('wire-table::messages.start') }}
        </button>
    </div>
@endif
