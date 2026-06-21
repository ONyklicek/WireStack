@php
    /** @var array<int, mixed> $components */
    /** @var int $columns */
@endphp

<div @class([
    'wire-infolist grid gap-4',
    'sm:grid-cols-1' => $columns === 1,
    'sm:grid-cols-2' => $columns === 2,
    'sm:grid-cols-2 md:grid-cols-3' => $columns === 3,
    'sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4' => $columns === 4,
])>
    @foreach($components as $component)
        @if($component->isVisible())
            {{ $component }}
        @endif
    @endforeach
</div>
