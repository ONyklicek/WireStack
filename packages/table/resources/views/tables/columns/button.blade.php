{{-- ButtonColumn cell --}}
{{-- Variables: $column, $record --}}
@php
    $buttonLabel = $column->evaluateLabel($record);
    $isDisabled = $column->isDisabledForRecord($record);
    $showLoading = $column->evaluateShowLoading($record);
    $loadingText = $column->evaluateLoadingText($record);
    $url = $column->evaluateUrl($record);
    $openInNewTab = $column->evaluateOpenInNewTab($record);

    $classes = $column->getButtonClasses($record);
    $iconHtml = $column->renderButtonIcon($record);
    $extraAttributes = $column->evaluateExtraAttributes($record);
    $iconOnly = $column->isIconOnly();
    $iconPosition = $column->getButtonIconPosition();
    $wireClick = $column->getWireClick($record);
    $livewireAction = $column->getLivewireAction();
    $disabledTooltip = $isDisabled ? $column->evaluateDisabledTooltip($record) : null;
@endphp

@if($url)
    <a href="{{ $url }}"
       @if($openInNewTab) target="_blank" rel="noopener noreferrer" @endif
       class="{{ $classes }}"
       @if($disabledTooltip) title="{{ $disabledTooltip }}" @endif
       @foreach($extraAttributes as $key => $value) {{ $key }}="{{ $value }}" @endforeach
    >
        @if($iconHtml && $iconPosition === 'before') {!! $iconHtml !!} @endif
        @unless($iconOnly) <span>{{ $buttonLabel }}</span> @endunless
        @if($iconHtml && $iconPosition === 'after') {!! $iconHtml !!} @endif
    </a>
@else
    <button type="button"
            class="{{ $classes }}"
            {!! $wireClick !!}
            @if($isDisabled) disabled @endif
            @if($disabledTooltip) title="{{ $disabledTooltip }}" @endif
            @foreach($extraAttributes as $key => $value) {{ $key }}="{{ $value }}" @endforeach
    >
        <span @if($showLoading) wire:loading.remove wire:target="{{ $livewireAction }}" @endif>
            @if($iconHtml && $iconPosition === 'before') {!! $iconHtml !!} @endif
            @unless($iconOnly) <span>{{ $buttonLabel }}</span> @endunless
            @if($iconHtml && $iconPosition === 'after') {!! $iconHtml !!} @endif
        </span>

        @if($showLoading)
            <span wire:loading wire:target="{{ $livewireAction }}" class="inline-flex items-center gap-1.5">
                @include('wire-table::tables.columns.partials.spinner')
                @if($loadingText)
                    <span>{{ $loadingText }}</span>
                @elseif(!$iconOnly)
                    <span>{{ $buttonLabel }}</span>
                @endif
            </span>
        @endif
    </button>
@endif
