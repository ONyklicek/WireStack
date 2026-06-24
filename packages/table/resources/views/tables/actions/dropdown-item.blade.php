@php

    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\Action;

    assert($action instanceof Action);
    assert($record instanceof Model);

    $url = $action->getUrl($record);
    $label = e($action->getLabel($record));
    $disabled = $action->isDisabled($record);
    $icon = $action->getIcon($record);
    $color = $action->getColor($record);
    $shortcutLabel = $action->getKeyboardShortcutLabel();
    $shortcutAlpine = $action->getAlpineKeydownExpression();
    $baseClasses = 'group flex w-full items-center px-4 py-2 text-sm';

    if ($disabled) {
        $classes = "{$baseClasses} text-gray-400 dark:text-gray-500 cursor-not-allowed";
    } else {
        // Color resolution is owned by Foundation HasColor (ghost/menu-item surface).
        $classes = "{$baseClasses} {$action->getMenuItemColorClasses($color)}";
    }
@endphp

@if($url && !$disabled)
    <a href="{{ $url }}" @if($action->shouldOpenUrlInNewTab()) target="_blank" @endif class="{{ $classes }}"
       role="menuitem">
        @if($icon)
            {!! $action->renderIconSvg($icon, 'mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300') !!}
        @endif
        <span class="flex-1">{{ $label }}</span>
        @if($shortcutLabel)
            <kbd class="ml-auto pl-2 text-[10px] font-mono text-gray-400">{{ $shortcutLabel }}</kbd>
        @endif
    </a>
@elseif($disabled)
    <span class="{{ $classes }}" role="menuitem">
        @if($icon)
            {!! $action->renderIconSvg($icon, 'mr-3 h-4 w-4') !!}
        @endif
        <span class="flex-1">{{ $label }}</span>
    </span>
@else
    @php
        $recordKey = $record->getKey();
        $actionName = $action->getName();
        $wireModifiers = $action->getWireClickModifiers();
    @endphp
    <button
            type="button"
            @if($action->hasModal())
                wire:click{{ $wireModifiers }}="openActionModal('{{ $recordKey }}', '{{ $actionName }}')"
            @else
                wire:click{{ $wireModifiers }}="executeTableAction('{{ $recordKey }}', '{{ $actionName }}')"
            @endif
            @click="close()"
            @if($shortcutAlpine)
                x-on:keydown.{{ $shortcutAlpine }}.window.prevent="$el.click()"
            @endif
            class="{{ $classes }}"
            role="menuitem"
    >
        @if($icon)
            {!! $action->renderIconSvg($icon, 'mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300') !!}
        @endif
        <span class="flex-1">{{ $label }}</span>
        @if($shortcutLabel)
            <kbd class="ml-auto pl-2 text-[10px] font-mono text-gray-400">{{ $shortcutLabel }}</kbd>
        @endif
    </button>
@endif
