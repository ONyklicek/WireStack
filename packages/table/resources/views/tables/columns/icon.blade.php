@php
    /** @var string $iconSvg */
    /** @var string $size */
    /** @var string $colorClass */
    /** @var string|null $tooltip */
@endphp

<div class="flex items-center" @if($tooltip) title="{{ $tooltip }}" @endif>
    <svg class="{{ $size }} {{ $colorClass }}" fill="currentColor" viewBox="0 0 20 20">
        {!! $iconSvg !!}
    </svg>
</div>
