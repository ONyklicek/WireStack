{{-- Sub-rows toolbar controls --}}
{{-- Variables: $table, $component --}}
@if($table->hasSubRows())
    <div class="flex items-center gap-2 text-xs">
        @if($table->isSubRowsExpandable())
            <button type="button" wire:click="expandAllRows" data-testid="subrows-expand-all"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <span class="inline-flex items-center gap-1">
                    {!! icon('outline:arrows-pointing-out', 'w-3.5 h-3.5') !!}
                    {{ __('wire-table::messages.expand_all') }}
                </span>
            </button>
            <span class="text-gray-300 dark:text-gray-600">|</span>
            <button type="button" wire:click="collapseAllRows" data-testid="subrows-collapse-all"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <span class="inline-flex items-center gap-1">
                    {!! icon('outline:arrows-pointing-in', 'w-3.5 h-3.5') !!}
                    {{ __('wire-table::messages.collapse_all') }}
                </span>
            </button>
        @endif

        <span class="text-gray-300 dark:text-gray-600">|</span>

        <button type="button" wire:click="toggleFlattenMode" data-testid="subrows-scope-toggle"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
            <span class="inline-flex items-center gap-1">
                @if($component->tableState->get('rows.flattenMode'))
                    {!! icon('outline:bars-3', 'w-3.5 h-3.5') !!}
                    {{ __('wire-table::messages.group_view') }}
                @else
                    {!! icon('outline:bars-2', 'w-3.5 h-3.5') !!}
                    {{ __('wire-table::messages.show_all') }}
                @endif
            </span>
        </button>
    </div>
@endif
