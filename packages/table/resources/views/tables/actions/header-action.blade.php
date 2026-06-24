@php

    use NyonCode\WireCore\Actions\HeaderAction;

    assert($action instanceof HeaderAction);

    $loadingState = $action->getLoadingStateData();
    $shortcutLabel = $action->getKeyboardShortcutLabel();
    $shortcutAlpine = $action->getAlpineKeydownExpression();

    // Size scale is header-button specific; color is owned by Foundation HasColor.
    $classes = 'inline-flex items-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 '
        . match ($action->getSize()) { 'xs' => 'px-2 py-1 text-xs', 'sm' => 'px-3 py-1.5 text-sm', 'md' => 'px-4 py-2 text-sm', 'lg' => 'px-5 py-2.5 text-base', default => 'px-3 py-1.5 text-sm' }
        . ' ' . $action->getButtonColorClasses();
@endphp

@if($action->getUrl())
    <a
            href="{{ $action->getUrl() }}"
            @if($action->shouldOpenUrlInNewTab()) target="_blank" @endif
            class="{{ $classes }} relative"
            @if($action->getTooltip()) title="{{ $action->getTooltip() }}" @endif
    >
        @if($action->getIcon())
            {!! $action->renderIconSvg($action->getIcon(), 'w-4 h-4') !!}
        @endif
        <span>{{ $action->getLabel() }}</span>
        @if($shortcutLabel)
            <kbd
                    class="hidden sm:inline-block ml-1 px-1 py-0.5 text-[10px] font-mono bg-white/20 rounded opacity-60">{{ $shortcutLabel }}</kbd>
        @endif
        {!! $action->getBadgeHtml() !!}
    </a>
@else
    @php
        $wireAction = $action->hasModal()
            ? "openHeaderActionModal('{$action->getName()}')"
            : "executeHeaderAction('{$action->getName()}')";
    @endphp
    <button
            type="button"
            wire:click{{ $loadingState['wireModifiers'] }}="{{ $wireAction }}"
            class="{{ $classes }} relative"
            @if($action->getTooltip()) title="{{ $action->getTooltip() }}" @endif
            @if($shortcutAlpine)
                x-on:keydown.{{ $shortcutAlpine }}.window.prevent="$el.click()"
            @endif
    >
        {{-- Loading spinner --}}
        @if($loadingState['showLoading'])
            @include('wire-core::partials.spinner', ['wireTarget' => $wireAction, 'class' => 'w-4 h-4'])
            <span wire:loading.remove wire:target="{{ $wireAction }}" class="inline-flex items-center gap-1.5">
        @endif

                @if($action->getIcon())
                    {!! $action->renderIconSvg($action->getIcon(), 'w-4 h-4') !!}
                @endif
        <span>{{ $action->getLabel() }}</span>
        @if($shortcutLabel)
                    <kbd
                            class="hidden sm:inline-block ml-1 px-1 py-0.5 text-[10px] font-mono bg-white/20 rounded opacity-60">{{ $shortcutLabel }}</kbd>
                @endif

                @if($loadingState['showLoading'])
            </span>
            @if($loadingState['loadingText'])
                <span wire:loading wire:target="{{ $wireAction }}">{{ $loadingState['loadingText'] }}</span>
            @endif
        @endif

        {!! $action->getBadgeHtml() !!}
    </button>
@endif
