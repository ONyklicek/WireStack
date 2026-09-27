@php use NyonCode\WireCore\Foundation\View\Badge; @endphp
{{-- One link of the bar, in the bar itself or inside one of its panels. The
     same questions as a sidebar row, answered by the same object. --}}
@php
    $url = $item->getUrl();
    $isActive = $active->isActive($item, $itemKey);
    $ariaCurrent = $active->ariaCurrent($item, $itemKey);
@endphp
<a
    @if ($url) href="{{ $url }}" wire:navigate @else aria-disabled="true" @endif
    @if ($ariaCurrent) aria-current="{{ $ariaCurrent }}" @endif
    @if ($isActive) data-active="true" @endif
    data-testid="{{ $testid }}"
    @if ($itemKey !== null) data-resource="{{ $itemKey }}" @endif
    @class([
        'flex items-center gap-2 rounded-lg text-sm transition',
        'px-3 py-1.5 whitespace-nowrap' => $inBar,
        'px-3 py-2' => ! $inBar,
        'bg-primary-50 font-medium text-primary-700 dark:bg-primary-950/60 dark:text-primary-200' => $isActive,
        'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white' => ! $isActive,
        'cursor-default opacity-60 hover:bg-transparent' => ! $url,
    ])
>
    @if ($item->getIcon())
        {!! icon($item->getIcon(), 'h-4 w-4 shrink-0') !!}
    @endif
    <span class="truncate">{{ $item->getLabel() }}</span>
    @if ($item->getBadge())
        <span class="ms-auto">{!! \NyonCode\WireCore\Foundation\View\ComponentRenderer::render(new Badge($item->getBadgeColor() ?? 'gray'), $item->getBadge()) !!}</span>
    @endif
</a>
