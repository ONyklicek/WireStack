@php
    use NyonCode\WireCore\Foundation\Schema\Tab;

    assert($layout instanceof Tab);
    $columns = $layout->getColumns();
    $columnsClass = is_array($columns) ? \NyonCode\WireCore\Foundation\Support\ResponsiveGrid::cols($columns) : '';
@endphp

<div @class([
    'grid gap-4',
    $columnsClass,
    'sm:grid-cols-1' => $columns === 1,
    'sm:grid-cols-2' => $columns === 2,
    'sm:grid-cols-2 md:grid-cols-3' => $columns === 3,
    'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4' => $columns === 4,
])>
    @foreach($layout->getSchema() as $component)
        @if($component->isVisible())
            {{ $component }}
        @endif
    @endforeach
</div>
