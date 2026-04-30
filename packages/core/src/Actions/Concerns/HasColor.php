<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

/**
 * Trait HasColor
 *
 * Centralized Tailwind CSS color class management.
 * Eliminates duplicate getColorClasses/getSolidColorClasses/getOutlinedColorClasses across all Actions.
 *
 * @author Ondřej Nyklíček
 */
trait HasColor
{
    /** @var array<string, string> */
    protected static array $colorClassCache = [];

    /**
     * Get solid (filled) button color classes.
     */
    protected function getSolidColorClasses(?string $color = null): string
    {
        $color ??= $this->getColor();
        $cacheKey = "solid_$color";

        return static::$colorClassCache[$cacheKey] ??= match ($color) {
            'primary',
            'blue' => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-600',
            'success',
            'green' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600',
            'danger',
            'red' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
            'warning',
            'yellow' => 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-500 dark:bg-amber-400 dark:hover:bg-amber-500',
            'gray',
            'secondary' => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
            default => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
        };
    }

    /**
     * Get outlined button color classes.
     */
    protected function getOutlinedColorClasses(?string $color = null): string
    {
        $color ??= $this->getColor();
        $cacheKey = "outlined_$color";

        return static::$colorClassCache[$cacheKey] ??= match ($color) {
            'primary',
            'blue' => 'border border-primary-600 text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-900/20',
            'success',
            'green' => 'border border-emerald-600 text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:border-emerald-400 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'danger',
            'red' => 'border border-red-600 text-red-600 hover:bg-red-50 focus:ring-red-500 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-900/20',
            'warning',
            'yellow' => 'border border-amber-600 text-amber-600 hover:bg-amber-50 focus:ring-amber-500 dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'gray',
            'secondary' => 'border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
            default => 'border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
        };
    }

    /**
     * Get ghost/link-style color classes (for dropdown items).
     */
    protected function getGhostColorClasses(?string $color = null): string
    {
        $color ??= $this->getColor();
        $cacheKey = "ghost_$color";

        return static::$colorClassCache[$cacheKey] ??= match ($color) {
            'danger', 'red' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            'warning', 'yellow' => 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'success',
            'green' => 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20',
            'primary',
            'blue' => 'text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20',
            default => 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
        };
    }

    /**
     * Get icon-only button color classes.
     */
    protected function getIconButtonColorClasses(?string $color = null): string
    {
        $color ??= $this->getColor();
        $cacheKey = "icon_$color";

        return static::$colorClassCache[$cacheKey] ??= match ($color) {
            'primary',
            'blue' => 'text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:text-primary-400 dark:hover:bg-primary-900/20',
            'danger',
            'red' => 'text-red-600 hover:bg-red-50 focus:ring-red-500 dark:text-red-400 dark:hover:bg-red-900/20',
            'success',
            'green' => 'text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            default => 'text-gray-500 hover:bg-gray-100 focus:ring-gray-500 dark:text-gray-400 dark:hover:bg-gray-700',
        };
    }

    /**
     * Get badge color classes (background + text).
     */
    public static function getBadgeColorClasses(string $color): string
    {
        return match ($color) {
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
    }

    /**
     * Get color for modal icon background.
     */
    public static function getModalIconBgClass(string $color): string
    {
        return match ($color) {
            'danger', 'red' => 'bg-red-100 dark:bg-red-900/30',
            'warning', 'yellow' => 'bg-amber-100 dark:bg-amber-900/30',
            'success', 'green' => 'bg-emerald-100 dark:bg-emerald-900/30',
            'info', 'blue' => 'bg-blue-100 dark:bg-blue-900/30',
            default => 'bg-amber-100 dark:bg-amber-900/30',
        };
    }

    /**
     * Get color for modal icon text.
     */
    public static function getModalIconTextClass(string $color): string
    {
        return match ($color) {
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'warning', 'yellow' => 'text-amber-600 dark:text-amber-400',
            'success', 'green' => 'text-emerald-600 dark:text-emerald-400',
            'info', 'blue' => 'text-blue-600 dark:text-blue-400',
            default => 'text-amber-600 dark:text-amber-400',
        };
    }
}
