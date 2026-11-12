{{-- A column's header filter behind a funnel (ColumnFilterLayout::Popover).

     The funnel sits beside the column label and is marked while the column is
     filtered, so a narrowed table says *where* it is narrowed without a chip
     row. It opens the one filter — operator picker, value, Clear — in a panel
     sized for the filter rather than for the column: a 60px column no longer
     means a 60px text box. On a phone the panel is a bottom sheet with the
     grabber, like every other floating panel.

     The control inside is exactly the one the filter row draws
     (Column::renderFilter()), bound to the same `columnFilters.<name>` state, so
     URLs, saved views and chips do not know which layout drew it. --}}
{{-- Variables: $column, $value (the column's filter state), $isFiltered,
     $sheetOnMobile, $sheetBp, $sheetBpPx, $sheetPanel, $sheetMotion,
     $sheetBackdrop --}}
@php
    $columnName = $column->getName();
    $columnLabel = $column->getLabel();
@endphp
<span
    x-data="wireDropdown({ placement: 'bottom-start', offset: 6{{ $sheetOnMobile ? ', sheetOnMobile: true, sheetBreakpoint: '.$sheetBpPx : '' }} })"
    @keydown.escape.window="open && $event.preventDefault(); close()"
    class="relative inline-flex align-middle normal-case"
    data-testid="column-filter-{{ $columnName }}" @wireEl('column-filter')
>
    <button
        x-ref="trigger"
        @click="toggle()"
        type="button"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="dialog"
        aria-label="{{ __('wire-table::messages.column_filter_open', ['column' => $columnLabel]) }}"
        title="{{ __('wire-table::messages.column_filter_open', ['column' => $columnLabel]) }}"
        data-testid="column-filter-trigger-{{ $columnName }}"
        @if($isFiltered) data-filtered @endif
        @class([
            'relative -my-1 inline-flex items-center justify-center rounded-md p-1.5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 touch-manipulation',
            'text-primary-600 bg-primary-50 hover:bg-primary-100 dark:text-primary-400 dark:bg-primary-500/15 dark:hover:bg-primary-500/25' => $isFiltered,
            'text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-700' => ! $isFiltered,
        ])
    >
        {!! icon('outline:funnel', 'h-3.5 w-3.5', $isFiltered ? 'fill-current' : '') !!}
        @if($isFiltered)
            <span class="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-primary-600 ring-2 ring-white dark:bg-primary-400 dark:ring-gray-800" aria-hidden="true"></span>
            <span class="sr-only">{{ __('wire-table::messages.column_filter_active') }}</span>
        @endif
    </button>

    <template x-teleport="body">
        <div>
            @if($sheetOnMobile)
                <div
                    x-show="open"
                    x-cloak
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="close()"
                    class="fixed inset-0 z-40 bg-gray-500/60 dark:bg-gray-900/70 {{ $sheetBackdrop }}"
                ></div>
            @endif

            <div
                x-ref="panel"
                x-show="open"
                @click.outside="$clickedInside($event) || close()"
                @if($sheetOnMobile) x-focus-trap="open" tabindex="-1" data-sheet-bp="{{ $sheetBpPx }}" @endif
                role="dialog"
                aria-label="{{ __('wire-table::messages.column_filter_open', ['column' => $columnLabel]) }}"
                data-testid="column-filter-panel-{{ $columnName }}" @wireEl('column-filter-panel')
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 {{ $sheetOnMobile ? $sheetMotion : '' }}"
                @class([
                    'absolute top-0 left-0 z-50 w-72 origin-top-left rounded-xl bg-white text-left normal-case tracking-normal font-normal shadow-lg ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700',
                    $sheetPanel => $sheetOnMobile,
                ])
                x-cloak
                style="display: none;"
            >
                @if($sheetOnMobile)
                    @include('wire-core::partials.sheet-grabber', ['dismiss' => 'close()', 'breakpoint' => $sheetBp])
                @endif

                <div class="flex items-center justify-between gap-3 px-4 pt-3">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $columnLabel }}</span>
                    @if($isFiltered)
                        <button
                            type="button"
                            wire:click="removeColumnFilter(@js($columnName))"
                            data-testid="column-filter-clear-{{ $columnName }}"
                            class="rounded-md px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                        >{{ __('wire-table::messages.column_filter_clear') }}</button>
                    @endif
                </div>

                <div class="px-4 pb-4 pt-3">
                    {!! $column->renderFilter($value) !!}
                </div>

                {{-- A phone has no "click outside" worth the name: the sheet says
                     how to leave it. From the breakpoint up, clicking away or
                     Escape is enough and the button would be noise. --}}
                @if($sheetOnMobile)
                    <div class="{{ \NyonCode\WireCore\Foundation\Support\MobileSheet::showBelow($sheetBp) }} border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        <button
                            type="button"
                            @click="close()"
                            class="w-full rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500"
                        >{{ __('wire-table::messages.column_filter_done') }}</button>
                    </div>
                @endif
            </div>
        </div>
    </template>
</span>
