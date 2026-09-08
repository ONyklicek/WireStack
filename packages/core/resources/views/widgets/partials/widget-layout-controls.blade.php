{{-- Customise / Save / Cancel / Reset, ready-made.

     The methods behind these are public on `WithWidgets`, and the chrome stays
     the caller's to write — a dashboard inside somebody's own page layout wants
     its buttons where that layout puts buttons, not where this partial does.
     What this is for is the common case: a page that wants the feature and not a
     design exercise.

     Renders nothing at all unless the host opted in, so including it
     unconditionally is safe on a dashboard nobody may rearrange.

     Variables: $customisable, $editing --}}
@if($customisable)
    <div class="wire-widget-layout-controls flex items-center justify-end gap-2">
        @if($editing)
            <button type="button" wire:click="saveWidgetLayout"
                    data-testid="widget-layout-save"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-primary-700">
                {!! icon('outline:check', 'w-4 h-4', 'h-4 w-4') !!}
                {{ __('wire-core::messages.widget_save_layout') }}
            </button>

            <button type="button" wire:click="cancelEditingWidgets"
                    data-testid="widget-layout-cancel"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                {{ __('wire-core::messages.widget_cancel_layout') }}
            </button>

            {{-- Last, and visually quietest: it throws away a layout somebody
                 may have spent a while on, and the two buttons it sits beside
                 are the ones being reached for. --}}
            <button type="button" wire:click="resetWidgetLayout"
                    data-testid="widget-layout-reset"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                {{ __('wire-core::messages.widget_reset_layout') }}
            </button>
        @else
            <button type="button" wire:click="startEditingWidgets"
                    data-testid="widget-layout-edit"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                {!! icon('outline:adjustments-horizontal', 'w-4 h-4', 'h-4 w-4') !!}
                {{ __('wire-core::messages.widget_customise') }}
            </button>
        @endif
    </div>
@endif
