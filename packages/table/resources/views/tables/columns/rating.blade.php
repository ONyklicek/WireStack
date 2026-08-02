{{-- RatingColumn cell. Column resolves the stars; this partial owns the wrapper. --}}
@php
    /** @var string $starsHtml resolved star svgs (filled / half / empty) */
    /** @var string $displayValue numeric value shown next to the stars ('' when hidden) */
    /** @var string $label accessible "3 out of 5" text */
@endphp

<span class="inline-flex items-center gap-1" role="img" aria-label="{{ $label }}">
    <span class="inline-flex items-center">{!! $starsHtml !!}</span>
    @if($displayValue !== '')
        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $displayValue }}</span>
    @endif
</span>
