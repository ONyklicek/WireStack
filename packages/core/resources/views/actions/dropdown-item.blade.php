@php

    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\Action;
    use NyonCode\WireCore\Actions\Contracts\ResolvesActionClick;
    use NyonCode\WireCore\Actions\Support\MountActionClickResolver;

    assert($action instanceof Action);
    assert($record instanceof Model);

    /** @var ResolvesActionClick $click */
    $click ??= new MountActionClickResolver();

    $url = $action->getUrl($record);
    $label = e($action->getLabel($record));
    $disabled = $action->isDisabled($record);
    $icon = $action->getIcon($record);
    $color = $action->getColor($record);
    $shortcutLabel = $action->getKeyboardShortcutLabel();
    $shortcutAlpine = $action->getAlpineKeydownExpression();
    // text-left: the button variant would otherwise center the label (UA default
    // for <button>), while <a> items align left — menu rows must match.
    $baseClasses = 'group flex w-full items-center px-4 py-2 text-sm text-left';

    if ($disabled) {
        $classes = "{$baseClasses} text-gray-400 dark:text-gray-500 cursor-not-allowed";
    } else {
        // Color resolution is owned by Foundation HasColor (ghost/menu-item surface).
        $classes = "{$baseClasses} {$action->getMenuItemColorClasses($color)}";
    }
@endphp

@if($url && !$disabled)
    <a href="{{ $url }}" @if($action->shouldOpenUrlInNewTab()) target="_blank" @endif class="{{ $classes }}"
       role="menuitem" data-testid="menu-action-{{ $action->getName() }}">
        @if($icon)
            {!! $action->renderIconSvg($icon, 'mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300') !!}
        @endif
        <span class="flex-1">{{ $label }}</span>
        @if($shortcutLabel)
            <kbd class="ml-auto pl-2 text-[10px] font-mono text-gray-400">{{ $shortcutLabel }}</kbd>
        @endif
    </a>
@elseif($disabled)
    <span class="{{ $classes }}" role="menuitem" data-testid="menu-action-{{ $action->getName() }}" aria-disabled="true">
        @if($icon)
            {!! $action->renderIconSvg($icon, 'mr-3 h-4 w-4') !!}
        @endif
        <span class="flex-1">{{ $label }}</span>
    </span>
@else
    @php
        $actionName = $action->getName();
        $wireModifiers = $action->getWireClickModifiers();
        // Host-owned click expression: core stays agnostic of table/form methods.
        $wireClick = $click->clickHandler($action, $record);
    @endphp
    <button
            type="button"
            wire:click{{ $wireModifiers }}="{{ $wireClick }}"
            @click="close()"
            @if($shortcutAlpine)
                x-on:keydown.{{ $shortcutAlpine }}.window.prevent="$el.click()"
            @endif
            class="{{ $classes }}"
            role="menuitem"
            data-testid="menu-action-{{ $actionName }}"
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
