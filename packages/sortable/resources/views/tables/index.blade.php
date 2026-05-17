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
    <div
            x-data="wireSortable({
                rowReorderable: {{ $isReorderable ? 'true' : 'false' }},
                columnReorderable: {{ $isColumnReorderable ? 'true' : 'false' }},
                isReordering: @entangle('isReordering'),
                orderColumn: '{{ $orderColumn }}',
                animation: {{ config('wire-sortable.animation', 150) }},
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
