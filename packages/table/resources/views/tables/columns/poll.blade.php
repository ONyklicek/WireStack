{{-- PollColumn cell --}}
@php
    /** @var string $stateIconHtml resolved state icon svg (may be empty) */
    /** @var string $content rendered state content */
    /** @var string $allClasses state + color + transition classes for the inner span */
    /** @var bool $shouldPoll */
    /** @var bool $isBadge */
    /** @var bool $showLoadingIndicator */
    /** @var bool $keepContentWhileLoading */
    /** @var string $loadingIndicator */
    /** @var string $position before|after */
    /** @var string $pollDirective wire:poll attribute (only when polling) */
    /** @var string $wireKey */

    $inner = ($stateIconHtml !== '' ? $stateIconHtml.' ' : '').$content;

    if ($allClasses !== '') {
        $inner = '<span class="'.$allClasses.'">'.$inner.'</span>';
    }

    if ($showLoadingIndicator) {
        // keepContentWhileLoading(false): the stale value is hidden during a
        // refresh and only the indicator shows, rather than sitting next to it.
        if (! $keepContentWhileLoading) {
            $inner = '<span wire:loading.remove>'.$inner.'</span>';
        }

        $inner = $position === 'before'
            ? '<span class="inline-flex items-center gap-0"><span wire:loading>'.$loadingIndicator.'</span>'.$inner.'</span>'
            : '<span class="inline-flex items-center gap-0">'.$inner.'<span wire:loading class="ml-1">'.$loadingIndicator.'</span></span>';
    }
@endphp

@if($shouldPoll && $isBadge)
    <span {!! $pollDirective !!} wire:key="{{ $wireKey }}" class="inline-flex items-center rounded-full font-medium">{!! $inner !!}</span>
@elseif($shouldPoll)
    <div {!! $pollDirective !!} wire:key="{{ $wireKey }}">{!! $inner !!}</div>
@else
    {!! $inner !!}
@endif
