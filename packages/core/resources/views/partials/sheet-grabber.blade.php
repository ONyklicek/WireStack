@php
    use NyonCode\WireCore\Foundation\Support\MobileSheet;

    /**
     * Drag handle ("grabber") for a mobile bottom sheet. Must be rendered as the
     * FIRST child of the sheet panel — the x-sheet-dismiss directive drags the
     * grabber's parent (the panel). Shown only below the configured breakpoint;
     * from there up the panel is a floating dropdown and the handle is hidden.
     *
     * @var string      $dismiss     Alpine expression that closes the panel.
     * @var string|null $breakpoint  Sheet breakpoint override (sm|md|lg).
     * @var string      $class       Extra classes — a panel with its own padding
     *                               pulls the handle up into it.
     * @var string      $detents     'medium,large' (a list: opens half-height,
     *                               pulls up to 92%) or 'large' (a dialog).
     */
    $dismiss ??= 'close()';
    $breakpoint ??= null;
    $class ??= '';
    $detents ??= 'medium,large';
@endphp
<div
    x-sheet-dismiss="{{ $dismiss }}"
    data-sheet-bp="{{ MobileSheet::px($breakpoint) }}"
    @if($detents !== 'medium,large') data-sheet-detents="{{ $detents }}" @endif
    class="{{ MobileSheet::grabberShow($breakpoint) }} sticky inset-x-0 top-0 z-10 justify-center bg-inherit pt-2.5 pb-1.5 touch-none select-none {{ $class }}"
    aria-hidden="true"
>
    <span class="h-1.5 w-10 rounded-full bg-gray-300 dark:bg-gray-600"></span>
</div>
