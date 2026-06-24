@php
    use NyonCode\WireCore\Infolists\Components\IconEntry;

    assert($field instanceof IconEntry);

    $spanClass = $field->getColumnSpanClass();
    $icon = $field->getResolvedIcon();
    $iconClass = 'w-5 h-5 '.$field->getIconColorClass();
    $tooltip = $field->getTooltip() ?? '';
@endphp

<div class="{{ $spanClass }}">
    @if($field->getLabel())
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
            {{ $field->getLabel() }}
        </div>
    @endif

    <div class="text-sm">
        @if($icon)
            <x-wire::icon :name="$icon" :class="$iconClass" :label="$tooltip"/>
        @else
            <span class="text-gray-400">{{ $field->getPlaceholder() ?? '-' }}</span>
        @endif
    </div>
</div>
