{{-- Base column cell template --}}
{{-- Variables: $column, $record, $state, $content --}}
@php
    $classes = $column->getTextClasses();
    $icon = $column->getIconName();
    $iconPosition = $column->getIconPosition();
    $url = $column->getUrl($record);
    $openInNewTab = $column->shouldOpenUrlInNewTab();
    $isCopyable = $column->isCopyable();
    $copyMessage = $column->getCopyMessage() ?? 'Zkopírováno!';
    $tooltip = $column->getTooltipText();
    $description = $column->getDescriptionText($record);
    $descriptionPosition = $column->getDescriptionPosition();
    $isHtml = $column->isHtml();

    // Apply text styling
    if ($classes && !$isHtml) {
        $displayContent = '<span class="' . $classes . '">' . e($content) . '</span>';
    } elseif ($classes) {
        $displayContent = '<span class="' . $classes . '">' . $content . '</span>';
    } elseif (!$isHtml) {
        $displayContent = e($content);
    } else {
        $displayContent = $content;
    }
@endphp

@if($icon)
    @if($iconPosition === 'after')
        {!! $displayContent !!} {!! $column->renderIcon($icon) !!}
    @else
        {!! $column->renderIcon($icon) !!} {!! $displayContent !!}
    @endif
    @php $displayContent = '' @endphp
@endif

@if($url)
    <a href="{{ $url }}"
       @if($openInNewTab) target="_blank" @endif
       class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">
        @if($icon && $displayContent === '')
            @if($iconPosition === 'after')
                {!! $content !!} {!! $column->renderIcon($icon) !!}
            @else
                {!! $column->renderIcon($icon) !!} {!! $content !!}
            @endif
        @else
            {!! $displayContent !!}
        @endif
    </a>
    @php $displayContent = '' @endphp
@endif

@if($isCopyable)
    @include('tables.columns.partials.copyable', [
        'content' => $displayContent ?: ($icon ? ($iconPosition === 'after' ? $content . ' ' . $column->renderIcon($icon) : $column->renderIcon($icon) . ' ' . $content) : $content),
        'copyValue' => $state,
        'copyMessage' => $copyMessage,
    ])
    @php $displayContent = '' @endphp
@endif

@if($tooltip && $displayContent)
    <span title="{{ $tooltip }}" class="cursor-help">{!! $displayContent !!}</span>
    @php $displayContent = '' @endphp
@endif

@if($description)
    <div>
        @if($descriptionPosition === 'above')
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
        {!! $displayContent !!}
        @if($descriptionPosition !== 'above')
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>
@elseif($displayContent)
    {!! $displayContent !!}
@endif
