{{-- Sub-rows toolbar controls --}}
{{-- Variables: $table, $component --}}
@if($table->hasSubRows())
    <div class="flex items-center gap-2 text-xs">
        @if($table->isSubRowsExpandable())
            <button type="button" wire:click="expandAllRows"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                    </svg>
                    {{ __('wire-table::messages.expand_all') }}
                </span>
            </button>
            <span class="text-gray-300 dark:text-gray-600">|</span>
            <button type="button" wire:click="collapseAllRows"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <span class="inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9V4.5M9 9H4.5M9 9L3.5 3.5M9 15v4.5M9 15H4.5M9 15l-5.5 5.5M15 9h4.5M15 9V4.5M15 9l5.5-5.5M15 15h4.5M15 15v4.5m0-4.5l5.5 5.5"/>
                    </svg>
                    {{ __('wire-table::messages.collapse_all') }}
                </span>
            </button>
        @endif

        <span class="text-gray-300 dark:text-gray-600">|</span>

        <button type="button" wire:click="toggleFlattenMode"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <span class="inline-flex items-center gap-1">
                @if($component->flattenMode)
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    {{ __('wire-table::messages.group_view') }}
                @else
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18"/>
                    </svg>
                    {{ __('wire-table::messages.show_all') }}
                @endif
            </span>
        </button>
    </div>
@endif
