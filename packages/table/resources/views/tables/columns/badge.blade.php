@php
    /** @var string $displayValue */
    /** @var string $color */
    /** @var string|null $icon */
    /** @var string $size */

    $colorClasses = match ($color) {
        'primary', 'blue' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
        'success', 'green' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'danger', 'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        'warning', 'yellow' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'info', 'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
        'gray', 'secondary' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    };

    $sizeClasses = match ($size) {
        'xs' => 'px-1.5 py-0.5 text-xs',
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-3 py-1.5 text-sm',
        default => 'px-2.5 py-1 text-xs',
    };
@endphp

<span class="inline-flex items-center gap-1 font-medium rounded-full {{ $colorClasses }} {{ $sizeClasses }}">
    @if($icon)
        {!! $icon !!}
    @endif
    {{ $displayValue }}
</span>
