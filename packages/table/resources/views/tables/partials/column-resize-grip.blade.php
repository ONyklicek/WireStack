{{-- The edge a header is dragged by (Table::resizableColumns()). The drag, the
     double click that resets the width and ←/→ are wire-table-columns.js;
     the width it settles on is kept by resizeTableColumn(). --}}
{{-- Variables: $column --}}
<span
    data-column-resize
    role="separator"
    aria-orientation="vertical"
    tabindex="0"
    aria-label="{{ __('wire-table::messages.resize_column', ['column' => $column->getLabel()]) }}"
    title="{{ __('wire-table::messages.resize_column', ['column' => $column->getLabel()]) }}"
    data-testid="table-column-resize-{{ $column->getName() }}" @wireEl('table-column-resize')
    class="absolute inset-y-0 -right-1 z-20 w-2 cursor-col-resize touch-none select-none after:absolute after:inset-y-2 after:left-1/2 after:w-px after:-translate-x-1/2 after:bg-gray-300 after:opacity-0 hover:after:opacity-100 focus-visible:outline-none focus-visible:after:opacity-100 focus-visible:after:bg-primary-500 dark:after:bg-gray-600"
></span>
