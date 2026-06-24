@php
    use NyonCode\WireTable\Table;

    assert($table instanceof Table);
    /** @var mixed $records */
    /** @var mixed $component */

    $isReorderable = $table->isReorderable();
    $isColumnReorderable = $table->isColumnReorderable();
    $hasSortableFeatures = $isReorderable || $isColumnReorderable;
    $isReordering = $isReorderable && $component->isTableReordering();
    $orderColumn = $isReorderable ? $table->getOrderColumn() : null;
@endphp

@if($hasSortableFeatures)
    @php
        // Apply user-defined column order before the inner view renders so that
        // $table->getColumns() (called by wire-table::tables.index in multiple
        // places) returns columns in the persisted order instead of the original
        // PHP-defined order. Without this, Livewire's morph undoes the visual
        // reordering on every re-render.
        if ($isColumnReorderable && method_exists($component, 'getReorderableColumns')) {
            $reorderedColumns = $component->getReorderableColumns();
            if (!empty($reorderedColumns)) {
                $table->columns($reorderedColumns);
            }
        }
    @endphp

    <div
            x-data="wireSortable({
                rowReorderable: {{ $isReorderable ? 'true' : 'false' }},
                columnReorderable: {{ $isColumnReorderable ? 'true' : 'false' }},
                isReordering: @entangle('isReordering'),
                orderColumn: '{{ $orderColumn }}',
                animation: {{ config('wire-sortable.animation', 150) }},
                dragHandleHtml: @js($table->getDragHandleHtml()->toHtml()),
            })"
            class="wire-sortable-wrapper"
    >
        @include('wire-table::tables.index', [
            'table' => $table,
            'records' => $records,
            'component' => $component,
        ])

        @include('wire-sortable::partials.scripts')
    </div>
@else
    @include('wire-table::tables.index', [
        'table' => $table,
        'records' => $records,
        'component' => $component,
    ])
@endif
