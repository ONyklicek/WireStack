{{-- ImageColumn cell --}}
@php
    /** @var string $url */
    /** @var string $sizeClasses */
    /** @var string $shapeClasses */
    /** @var string $ringClasses */
@endphp

<img src="{{ $url }}" alt="" class="{{ $sizeClasses }} {{ $shapeClasses }} {{ $ringClasses }} object-cover">
