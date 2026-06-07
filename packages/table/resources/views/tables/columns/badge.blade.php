{{-- BadgeColumn cell --}}
@php
    /** @var string $sizeClasses canonical badge sizing from HasSize::getBadgeSizeClasses */
    /** @var string $colorClasses canonical soft palette from HasColor::getBadgeColorClasses */
    /** @var string $iconHtml resolved icon svg (may be empty) */
    /** @var string $displayValue formatted (already-prepared) cell value */
@endphp

<span class="inline-flex items-center {{ $sizeClasses }} {{ $colorClasses }} rounded-full font-medium">
    {!! $iconHtml !!}{!! $displayValue !!}
</span>
