{{-- The handle and the size steppers, drawn over a tile while the dashboard is
     being rearranged.

     Only in edit mode, and that is the whole reason edit mode exists: a
     dashboard somebody is reading should be a dashboard, not a dashboard
     wearing controls. Outside the mode this partial is never included and the
     tile is the markup it always was.

     `x-sort:handle` is the plugin's own attribute — the drag starts here rather
     than anywhere on the card, so a click inside a widget still belongs to the
     widget.

     Variables: $widget, $columns --}}
@php
    use NyonCode\WireCore\Widgets\Support\WidgetSizeOffer;

    $editKey = $widget->getKey();
    $editWidth = is_int($widget->getColumnSpan()) ? $widget->getColumnSpan() : 1;
    $editHeight = $widget->getRowSpan() ?? 1;

    // What this widget may be given on *this* grid: its own `sizes()` where it
    // declared any, and never more columns than the dashboard has. Both bounds
    // come from one owner because they are the same question — see
    // WidgetSizeOffer, and note that a button with nowhere to go answers null
    // and is drawn disabled, which is the only place a user sees the offer.
    $offer = WidgetSizeOffer::for($widget, $columns ?? 2);

    $narrower = $offer->narrower($editWidth, $editHeight);
    $wider = $offer->wider($editWidth, $editHeight);
    $shorter = $offer->shorter($editWidth, $editHeight);
    $taller = $offer->taller($editWidth, $editHeight);
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

    {{-- Steppers rather than a drag-to-resize corner: a span is a handful of
         sizes, so a button can say which one you are getting. A corner would
         need pointer maths, a preview and a grid that can be measured mid-drag,
         to land on the same few. --}}
    <button type="button"
            @if($narrower) wire:click="{{ $widget->getResizeExpression($narrower[0], $narrower[1]) }}" @endif
            @disabled($narrower === null)
            data-testid="widget-narrower-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_narrower') }}"
            class="rounded-sm p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-left', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
    <button type="button"
            @if($wider) wire:click="{{ $widget->getResizeExpression($wider[0], $wider[1]) }}" @endif
            @disabled($wider === null)
            data-testid="widget-wider-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_wider') }}"
            class="rounded-sm p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-right', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
    <button type="button"
            @if($shorter) wire:click="{{ $widget->getResizeExpression($shorter[0], $shorter[1]) }}" @endif
            @disabled($shorter === null)
            data-testid="widget-shorter-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_shorter') }}"
            class="rounded-sm p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-up', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
    <button type="button"
            @if($taller) wire:click="{{ $widget->getResizeExpression($taller[0], $taller[1]) }}" @endif
            @disabled($taller === null)
            data-testid="widget-taller-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_taller') }}"
            class="rounded-sm p-0.5 text-gray-400 hover:text-gray-600 disabled:opacity-30 dark:hover:text-gray-300">
        {!! icon('outline:chevron-down', 'w-4 h-4', 'h-4 w-4') !!}
    </button>

    <button type="button"
            wire:click="{{ $widget->getRemoveExpression() }}"
            data-testid="widget-remove-{{ $editKey }}"
            aria-label="{{ __('wire-core::messages.widget_remove') }}"
            class="ml-0.5 rounded-sm p-0.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400">
        {!! icon('outline:x-mark', 'w-4 h-4', 'h-4 w-4') !!}
    </button>
</div>
