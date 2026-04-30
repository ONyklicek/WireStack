@php
    /** @var string|array $urls */
    /** @var string $size */
    /** @var bool $circular */
    /** @var bool $stacked */
    /** @var int $limit */
    /** @var int $remaining */
@endphp

@if(is_array($urls))
    <div class="flex {{ $stacked ? '-space-x-2' : 'space-x-2' }} items-center">
        @foreach(array_slice($urls, 0, $limit) as $url)
            <img
                src="{{ $url }}"
                class="{{ $size }} object-cover {{ $circular ? 'rounded-full' : 'rounded-lg' }} {{ $stacked ? 'ring-2 ring-white dark:ring-gray-800' : '' }}"
                loading="lazy"
                alt=""
            >
        @endforeach
        @if($remaining > 0)
            <span class="{{ $size }} flex items-center justify-center text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 {{ $circular ? 'rounded-full' : 'rounded-lg' }} {{ $stacked ? 'ring-2 ring-white dark:ring-gray-800' : '' }}">
                +{{ $remaining }}
            </span>
        @endif
    </div>
@else
    <img
        src="{{ $urls }}"
        class="{{ $size }} object-cover {{ $circular ? 'rounded-full' : 'rounded-lg' }}"
        loading="lazy"
        alt=""
    >
@endif
