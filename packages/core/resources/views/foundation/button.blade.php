@php
    $tag = $href ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $type }}" @endif
    @if($disabled) disabled @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800']) }}
>
    @if($icon && $iconPosition === 'before')
        {!! icon($icon, 'w-4 h-4') !!}
    @endif
    {{ $slot }}
    @if($icon && $iconPosition === 'after')
        {!! icon($icon, 'w-4 h-4') !!}
    @endif
</{{ $tag }}>
