{{-- The handle and the size steppers, drawn over a tile while the dashboard is
     being rearranged.

     Only in edit mode, and that is the whole reason edit mode exists: a
     dashboard somebody is reading should be a dashboard, not a dashboard
     wearing controls. Outside the mode this partial is never included and the
     tile is the markup it always was.

     `x-sort:handle` is the plugin's own attribute — the drag starts here rather
     than anywhere on the card, so a click inside a widget still belongs to the
     widget.

     Variables: $widget --}}
@php
    $editKey = $widget->getKey();
    $editWidth = is_int($widget->getColumnSpan()) ? $widget->getColumnSpan() : 1;
    $editHeight = $widget->getRowSpan() ?? 1;
@endphp
<div class="absolute -top-3 right-2 z-10 flex items-center gap-1 rounded-full border border-gray-200 bg-white px-1.5 py-1 shadow-sm dark:border-gray-700 dark:bg-gray-800">
    <span x-sort:handle
          data-testid="widget-drag-{{ $editKey }}"
          role="button"
          tabindex="0"
          aria-label="{{ __('wire-core::messages.widget_reorder') }}"
          class="cursor-grab p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
        {!! icon('outline:bars-3', 'w-4 h-4', 'h-4 w-4') !!}
    </span>

    {{-- Steppers rather than a drag-to-resize corner: a span is 1–4 columns and
         1–6 rows, so there are eleven reachable sizes in total and a button says
         which one you are getting. A corner would need pointer maths, a preview
         and a grid that can be measured mid-drag, to land on the same eleven. --}}
    <button type="button"
            wire:click="{{ $widget->getResizeExpression(max(1, $editWidth - 1), $editHeight) }}"
            @disabled($editWidth <= 1)
            data-testid="widget-narrower-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_narrower') }}"
            class="rounded p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-left', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
    <button type="button"
            wire:click="{{ $widget->getResizeExpression(min(4, $editWidth + 1), $editHeight) }}"
            @disabled($editWidth >= 4)
            data-testid="widget-wider-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_wider') }}"
            class="rounded p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-right', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
    <button type="button"
            wire:click="{{ $widget->getResizeExpression($editWidth, max(1, $editHeight - 1)) }}"
            @disabled($editHeight <= 1)
            data-testid="widget-shorter-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_shorter') }}"
            class="rounded p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-up', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
    <button type="button"
            wire:click="{{ $widget->getResizeExpression($editWidth, min(6, $editHeight + 1)) }}"
            @disabled($editHeight >= 6)
            data-testid="widget-taller-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_taller') }}"
            class="rounded p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-down', 'w-4 h-4', 'h-4 w-4') !!}
    </button>

    <button type="button"
            wire:click="{{ $widget->getRemoveExpression() }}"
            data-testid="widget-remove-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_remove') }}"
            class="ml-0.5 rounded p-0.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400">
        {!! icon('outline:x-mark', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
</div>
