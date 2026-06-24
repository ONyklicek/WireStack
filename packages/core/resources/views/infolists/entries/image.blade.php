@php
    use NyonCode\WireCore\Infolists\Components\ImageEntry;

    assert($field instanceof ImageEntry);

    $spanClass = $field->getColumnSpanClass();
    $urls = $field->getImageUrls();
    $size = $field->getImageSize();
    $shape = $field->isCircular() ? 'rounded-full' : 'rounded-md';
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            {{ $field->getLabel() }}
        </div>
    @endif

    <div class="text-sm">
        @if(count($urls))
            <div @class(['flex items-center', $field->isStacked() ? '-space-x-2' : 'flex-wrap gap-2'])>
                @foreach($urls as $url)
                    <img
                            src="{{ $url }}"
                            alt="{{ $field->getLabel() }}"
                            @class([
                                'object-cover',
                                $shape,
                                'ring-2 ring-white dark:ring-gray-800' => $field->isStacked(),
                            ])
                            style="width: {{ $size }}px; height: {{ $size }}px;"
                    />
                @endforeach
            </div>
        @else
            <span class="text-gray-400">{{ $field->getPlaceholder() ?? '-' }}</span>
        @endif
    </div>
</div>
