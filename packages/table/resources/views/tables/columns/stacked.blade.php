{{-- StackedColumn cell --}}
@php
    /** @var string|null $avatarUrl */
    /** @var string $avatarClasses */
    /** @var array<int, array{class: string, value: string}> $items */
    /** @var bool $customStack */
@endphp

@php
    $lines = function () use ($items) {
        $html = '';
        foreach ($items as $item) {
            $html .= '<p class="'.e($item['class']).'">'.e($item['value']).'</p>';
        }
        return $html;
    };
@endphp

@if($customStack)
    @if($avatarUrl)
        <div class="flex items-center gap-3">
            <img src="{{ $avatarUrl }}" class="{{ $avatarClasses }} object-cover" alt="">
            <div>{!! $lines() !!}</div>
        </div>
    @else
        <div>{!! $lines() !!}</div>
    @endif
@elseif($avatarUrl && count($items))
    <div class="flex items-center gap-3">
        <img src="{{ $avatarUrl }}" class="{{ $avatarClasses }} object-cover" alt="">
        <div>{!! $lines() !!}</div>
    </div>
@elseif($avatarUrl)
    <img src="{{ $avatarUrl }}" class="{{ $avatarClasses }} object-cover" alt="">
@elseif(count($items))
    {!! $lines() !!}
@else
    <span class="text-gray-400">—</span>
@endif
