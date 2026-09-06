{{-- SplitColumn cell --}}
@php
    /** @var string $layout horizontal|vertical */
    /** @var string $alignClass */
    /** @var string $gapClass */
    /** @var string $imageHtml pre-rendered image column markup (may be empty) */
    /** @var array<int, string> $textColumns pre-rendered child column markup */
@endphp

@if($layout === 'horizontal' && $imageHtml)
    <div class="flex {{ $alignClass }} {{ $gapClass }}">
        {!! $imageHtml !!}
        <div class="min-w-0 flex-1">{!! implode('', $textColumns) !!}</div>
    </div>
@elseif($layout === 'horizontal')
    <div class="flex {{ $alignClass }} {{ $gapClass }}">@foreach(array_merge([$imageHtml], $textColumns) as $h)<div>{!! $h !!}</div>@endforeach</div>
@else
    {{-- $alignClass is empty unless the author set one: a column has always
         stretched its children, and the row's default would re-lay-out every
         existing vertical split. --}}
    <div class="flex flex-col {{ $alignClass }} {{ $gapClass }}">{!! implode('', $imageHtml ? array_merge([$imageHtml], $textColumns) : $textColumns) !!}</div>
@endif
