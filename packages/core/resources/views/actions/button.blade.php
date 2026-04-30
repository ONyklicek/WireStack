@php
    /** @var \NyonCode\WireCore\Actions\Action $action */
    /** @var \Illuminate\Database\Eloquent\Model|null $record */

    $data = method_exists($action, 'getRenderData') && $record
        ? $action->getRenderData($record)
        : [
            'url' => null,
            'target' => null,
            'classes' => $action->resolveSolidColorClasses() . ' ' . $action->resolveButtonSizeClasses(),
            'tooltip' => $action->getTooltip(),
            'shortcutLabel' => $action->getKeyboardShortcutLabel(),
            'shortcutAlpine' => $action->getAlpineKeydownExpression(),
            'extraAttributes' => method_exists($action, 'getExtraAttributes') ? $action->getExtraAttributes() : [],
            'hasModal' => $action->hasModal(),
            'actionName' => $action->getName(),
            'label' => $action->getLabel(),
            'iconHtml' => $action->getIcon() ? $action->renderIconSvg($action->getIcon(), 'w-4 h-4') : '',
            'iconPosition' => $action->getIconPosition(),
            'hideLabel' => method_exists($action, 'isHideLabel') ? $action->isHideLabel() : false,
            'disabled' => method_exists($action, 'isDisabled') ? $action->isDisabled() : false,
            'showLoading' => method_exists($action, 'hasLoadingIndicator') ? $action->hasLoadingIndicator() : false,
            'loadingText' => method_exists($action, 'getLoadingText') ? $action->getLoadingText() : null,
            'wireModifiers' => method_exists($action, 'getWireClickModifiers') ? $action->getWireClickModifiers() : '',
        ];

    $wireClickAction = $wireClick ?? '';
    $wireModifiers = $wireClickModifiers ?? $data['wireModifiers'] ?? '';
@endphp

@if($data['url'] ?? null)
    <a
        href="{{ $data['url'] }}"
        @if($data['target'] ?? null) target="{{ $data['target'] }}" @endif
        class="{{ $data['classes'] }}"
        @if($data['tooltip'] ?? null) title="{{ $data['tooltip'] }}" @endif
        @if($data['shortcutLabel'] ?? null) data-shortcut="{{ $data['shortcutLabel'] }}" @endif
        @foreach($data['extraAttributes'] ?? [] as $attr => $val)
            {{ $attr }}="{{ $val }}"
        @endforeach
    >
        @include('wire-core::actions.partials.button-content', ['data' => $data])
    </a>
@elseif($wireClickAction)
    <button
        type="button"
        wire:click{{ $wireModifiers }}="{{ $wireClickAction }}"
        class="{{ $data['classes'] }}"
        @if($data['tooltip'] ?? null) title="{{ $data['tooltip'] }}" @endif
        @if($data['disabled'] ?? false) disabled @endif
        @if($data['shortcutAlpine'] ?? null)
            x-on:keydown.{{ $data['shortcutAlpine'] }}.window.prevent="$el.click()"
        @endif
        @if($data['shortcutLabel'] ?? null) data-shortcut="{{ $data['shortcutLabel'] }}" @endif
        @foreach($data['extraAttributes'] ?? [] as $attr => $val)
            {{ $attr }}="{{ $val }}"
        @endforeach
    >
        {{-- Loading spinner --}}
        @if($data['showLoading'] ?? false)
            <svg
                wire:loading
                wire:target="{{ $wireClickAction }}"
                class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span wire:loading.remove wire:target="{{ $wireClickAction }}" class="inline-flex items-center gap-1.5">
                @include('wire-core::actions.partials.button-content', ['data' => $data])
            </span>
            @if($data['loadingText'] ?? null)
                <span wire:loading wire:target="{{ $wireClickAction }}">{{ $data['loadingText'] }}</span>
            @endif
        @else
            @include('wire-core::actions.partials.button-content', ['data' => $data])
        @endif
    </button>
@endif
