{{-- A dashboard the user rearranges: the drag, the size steppers and the mode
     that holds them.

     The chrome around the grid belongs to whoever renders the dashboard — a
     page in an application, `DashboardPage` in wire-panels — not to the grid,
     which is why it is here rather than in `widget-grid`. What the framework
     owns is the four methods this calls.

     Variables: everything `WithWidgets::widgetGridData()` returns --}}
<div data-preview-root class="mx-auto w-full max-w-[1100px] p-5">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-700/80">Customisable dashboard</p>
            <h2 class="mt-1 text-xl font-semibold text-slate-900">{{ $editing ? 'Arranging…' : 'Sales' }}</h2>
        </div>

        <div class="flex items-center gap-2">
            @if($editing)
                <button type="button" wire:click="saveWidgetLayout" data-testid="layout-save"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    {{ __('wire-core::messages.widget_save_layout') }}
                </button>
                <button type="button" wire:click="cancelEditingWidgets" data-testid="layout-cancel"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ __('wire-core::messages.widget_cancel_layout') }}
                </button>
                <button type="button" wire:click="resetWidgetLayout" data-testid="layout-reset"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ __('wire-core::messages.widget_reset_layout') }}
                </button>
            @else
                <button type="button" wire:click="startEditingWidgets" data-testid="layout-edit"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    {{ __('wire-core::messages.widget_customise') }}
                </button>
            @endif
        </div>
    </div>

    <div data-preview-focus>
        @include('wire-core::widgets.widget-grid')
    </div>
</div>
