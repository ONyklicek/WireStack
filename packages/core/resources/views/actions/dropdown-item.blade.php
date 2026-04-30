@php
    /** @var \NyonCode\WireCore\Actions\Action $action */
    /** @var \Illuminate\Database\Eloquent\Model|null $record */

    $url = $record ? $action->getUrl($record) : null;
    $label = $record ? $action->getLabel($record) : $action->getLabel();
    $disabled = $record ? $action->isDisabled($record) : false;
    $icon = $record ? $action->getIcon($record) : $action->getIcon();
    $color = $record ? $action->getColor($record) : $action->getColor();
    $shortcutLabel = $action->getKeyboardShortcutLabel();
    $shortcutAlpine = $action->getAlpineKeydownExpression();
    $baseClasses = 'group flex w-full items-center px-4 py-2 text-sm';

    if ($disabled) {
        $classes = "{$baseClasses} text-gray-400 dark:text-gray-500 cursor-not-allowed";
    } else {
        $colorClasses = match ($color) {
            'danger', 'red' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            'warning', 'yellow' => 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'success', 'green' => 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20',
            'primary', 'blue' => 'text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20',
            default => 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
        };
        $classes = "{$baseClasses} {$colorClasses}";
    }

    $wireClickAction = $wireClick ?? '';
    $wireModifiers = $wireClickModifiers ?? '';
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
@elseif($wireClickAction)
    <button
        type="button"
        wire:click{{ $wireModifiers }}="{{ $wireClickAction }}"
        @click="open = false"
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
