@php
    use NyonCode\WireCore\Foundation\Schema\Grid;
    use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

    assert($layout instanceof Grid);

    // Both shapes through the canonical owner. The int case used to be a local
    // `match` that reflowed at `sm` and stopped at four columns, while the
    // standalone <x-wire::grid> tag over the same class already delegated and
    // reflowed at `md` — one Grid, two layouts, depending on which surface drew
    // it.
    $columnsClass = ResponsiveGrid::cols($layout->getColumns());
@endphp

<div class="grid gap-4 {{ $columnsClass }}">
    @foreach($layout->getSchema() as $component)
        @if($component->isVisible())
            {{ $component }}
        @endif
    @endforeach
</div>
