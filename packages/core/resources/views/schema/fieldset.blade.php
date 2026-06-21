@php
    use NyonCode\WireCore\Foundation\Schema\Fieldset;

    assert($layout instanceof Fieldset);

    $columns = $layout->getColumns();
@endphp

<fieldset class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
    @if($layout->getLabel())
        <legend class="px-2 text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $layout->getLabel() }}
        </legend>
    @endif

    <div @class([
        'grid gap-4',
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
</fieldset>
