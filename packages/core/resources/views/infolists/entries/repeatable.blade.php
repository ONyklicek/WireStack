@php
    use NyonCode\WireCore\Infolists\Components\RepeatableEntry;

    assert($field instanceof RepeatableEntry);

    $span = $field->getColumnSpan();
    $spanClass = match (true) {
        $span === 'full' => 'col-span-full',
        $span === 2 => 'sm:col-span-2',
        $span === 3 => 'sm:col-span-3',
        $span === 4 => 'sm:col-span-4',
        default => 'col-span-full',
    };
    $columns = $field->getColumns();
    $rows = $field->getRows();
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
            {{ $field->getLabel() }}
        </div>
    @endif

    <div class="text-sm">
        @if(count($rows))
            <div class="space-y-3">
                @foreach($rows as $entries)
                    <div @class([
                        'grid gap-4',
                        'rounded-lg border border-gray-200 dark:border-gray-700 p-4' => $field->isContained(),
                        'sm:grid-cols-1' => $columns === 1,
                        'sm:grid-cols-2' => $columns === 2,
                        'sm:grid-cols-2 md:grid-cols-3' => $columns === 3,
                        'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4' => $columns === 4,
                    ])>
                        @foreach($entries as $entry)
                            @if($entry->isVisible())
                                {{ $entry }}
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            <span class="text-gray-400">{{ $field->getPlaceholder() ?? '-' }}</span>
        @endif
    </div>
</div>
