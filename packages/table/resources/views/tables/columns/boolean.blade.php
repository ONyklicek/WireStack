@php
    /** @var bool $state */
    /** @var string $trueColor */
    /** @var string $falseColor */
    /** @var string $trueIcon */
    /** @var string $falseIcon */
@endphp

@if($state)
    <div class="flex items-center">
        <svg class="w-5 h-5 {{ $trueColor }}" fill="currentColor" viewBox="0 0 20 20">
            {!! $trueIcon !!}
        </svg>
    </div>
@else
    <div class="flex items-center">
        <svg class="w-5 h-5 {{ $falseColor }}" fill="currentColor" viewBox="0 0 20 20">
            {!! $falseIcon !!}
        </svg>
    </div>
@endif
