@php
    use NyonCode\WireCore\Infolists\Components\RepeatableEntry;

    assert($field instanceof RepeatableEntry);

    $spanClass = $field->getColumnSpanClass('col-span-full');
    $columns = $field->getColumns();
    $rows = $field->getRows();
    $rowActions = $field->getActions();
    $gridCols = match ($columns) {
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 md:grid-cols-3',
        4 => 'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
        default => 'sm:grid-cols-1',
    };
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
                @foreach($rows as $rowIndex => $entries)
                    <div @class(['rounded-lg border border-gray-200 dark:border-gray-700 p-4' => $field->isContained()])>
                        <div @class(['grid gap-4', $gridCols])>
                            @foreach($entries as $entry)
                                @if($entry->isVisible())
                                    {{ $entry }}
                                @endif
                            @endforeach
                        </div>

                        @if($rowActions !== [])
                            <div class="mt-3 flex flex-wrap items-center justify-end gap-1.5">
                                @foreach($rowActions as $rowAction)
                                    @include('wire-core::partials.component-action', ['action' => $rowAction, 'rowKey' => $rowIndex])
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <span class="text-gray-400">{{ $field->getPlaceholder() ?? '-' }}</span>
        @endif
    </div>
</div>
