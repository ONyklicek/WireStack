@php
    use NyonCode\WireCore\Actions\BulkAction;
    assert($action instanceof BulkAction);

    $classes = 'inline-flex items-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 '
        . match ($action->getSize()) { 'xs' => 'px-2 py-1 text-xs', 'sm' => 'px-2.5 py-1.5 text-sm', 'md' => 'px-3 py-2 text-sm', 'lg' => 'px-4 py-2.5 text-base', default => 'px-2.5 py-1.5 text-sm' }
        . ' ' . ($action->isOutlined()
            ? match ($action->getColor()) {
                'primary', 'blue' => 'border border-primary-600 text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-900/20',
                'danger', 'red' => 'border border-red-600 text-red-600 hover:bg-red-50 focus:ring-red-500 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-900/20',
                default => 'border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
            }
            : match ($action->getColor()) {
                'primary', 'blue' => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
                'danger', 'red' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
                'success', 'green' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
                'warning', 'yellow' => 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-500',
                default => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
            });

    $wireClickAction = $wireClick ?? '';
    $wireModifiers = $wireClickModifiers ?? '';
@endphp

<button
        type="button"
        @if($wireClickAction)
            wire:click{{ $wireModifiers }}="{{ $wireClickAction }}"
        @endif
        class="{{ $classes }}"
        @if($action->getTooltip()) title="{{ $action->getTooltip() }}" @endif
>
    @if($action->getIcon())
        {!! $action->renderIconSvg($action->getIcon(), 'w-4 h-4') !!}
    @endif
    <span>{{ $action->getLabel() }}</span>
</button>
