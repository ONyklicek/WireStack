<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Trait HasColor
 *
 * Centralized Tailwind CSS color class management.
 * Used across Actions, Badges, Notifications, and Form components.
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
            'info',
            'cyan' => 'bg-cyan-600 text-white hover:bg-cyan-700 focus:ring-cyan-500 dark:bg-cyan-500 dark:hover:bg-cyan-600',
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
            'info',
            'cyan' => 'border border-cyan-600 text-cyan-600 hover:bg-cyan-50 focus:ring-cyan-500 dark:border-cyan-400 dark:text-cyan-400 dark:hover:bg-cyan-900/20',
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
     * Get badge color classes (soft background + text).
     *
     * Canonical palette shared by Badges, BadgeColumn, PollColumn and any soft
     * "pill" surface. Semantic names resolve to a fixed Tailwind hue (success →
     * emerald, info → cyan, blue → primary); raw Tailwind hues (sky, violet,
     * indigo, orange, teal) are also accepted for finer control. Literal class
     * strings are kept verbatim so Tailwind's JIT scanner can see them.
     */
    public static function getBadgeColorClasses(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            'success', 'green', 'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'danger', 'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'warning', 'yellow', 'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'info', 'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
            'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
            'gray', 'secondary' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'teal' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Get plain text color classes (foreground only, no background).
     *
     * Canonical source for text-tinted cells, icons and inline states. Same
     * palette vocabulary as {@see getBadgeColorClasses()}. Replaces the various
     * ad-hoc `text-green-500` / `text-primary-600` maps that lived in table
     * columns, so a single hue is used everywhere (e.g. success is always
     * emerald, never green).
     */
    public static function getTextColorClasses(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'text-primary-600 dark:text-primary-400',
            'success', 'green', 'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'warning', 'yellow', 'amber' => 'text-amber-600 dark:text-amber-400',
            'info', 'cyan' => 'text-cyan-600 dark:text-cyan-400',
            'sky' => 'text-sky-600 dark:text-sky-400',
            'gray', 'secondary' => 'text-gray-600 dark:text-gray-400',
            'purple' => 'text-purple-600 dark:text-purple-400',
            'violet' => 'text-violet-600 dark:text-violet-400',
            'indigo' => 'text-indigo-600 dark:text-indigo-400',
            'orange' => 'text-orange-600 dark:text-orange-400',
            'teal' => 'text-teal-600 dark:text-teal-400',
            'pink' => 'text-pink-600 dark:text-pink-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    /**
     * Get text/link button color classes (foreground + hover underline).
     *
     * Canonical source for the "link" button variant (no background, hover
     * darkens + underlines). Same hue vocabulary as {@see getTextColorClasses()}
     * so a link button matches the rest of the palette (info → cyan, success →
     * emerald, blue → primary). Literal class strings are kept verbatim for
     * Tailwind's JIT scanner.
     */
    public static function getLinkColorClasses(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline',
            'success', 'green', 'emerald' => 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 hover:underline',
            'danger', 'red' => 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 hover:underline',
            'warning', 'yellow', 'amber' => 'text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 hover:underline',
            'info', 'cyan' => 'text-cyan-600 hover:text-cyan-800 dark:text-cyan-400 dark:hover:text-cyan-300 hover:underline',
            'gray', 'secondary' => 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 hover:underline',
            default => 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 hover:underline',
        };
    }

    /**
     * Get a solid background-fill class only (no text/hover/focus).
     *
     * Canonical source for surfaces that need just a filled color block — e.g.
     * the "on" track of a toggle switch or a solid count badge. Same hue
     * vocabulary as the rest of the palette (success → emerald, blue → primary,
     * info → cyan), so these surfaces never drift back to raw Tailwind hues.
     */
    public static function getSolidBgClass(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'bg-primary-600',
            'success', 'green', 'emerald' => 'bg-emerald-600',
            'danger', 'red' => 'bg-red-600',
            'warning', 'yellow', 'amber' => 'bg-amber-500',
            'info', 'cyan' => 'bg-cyan-500',
            'gray', 'secondary' => 'bg-gray-600',
            'purple' => 'bg-purple-600',
            'pink' => 'bg-pink-600',
            default => 'bg-primary-600',
        };
    }

    /**
     * Get a soft (muted) background-fill class only (no text/hover/focus).
     *
     * Canonical companion to {@see getSolidBgClass()} for surfaces that need a
     * low-contrast tinted block — e.g. the "off" track of a toggle switch. Same
     * hue vocabulary as the rest of the palette (success → emerald, blue →
     * primary, info → cyan), with a neutral gray default so an unset color does
     * not read as a warning. Class strings are kept literal so Tailwind's JIT
     * scanner can see them, which also keeps the mapping a safe allow-list.
     */
    public static function getSoftBgClass(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'bg-primary-200 dark:bg-primary-900',
            'success', 'green', 'emerald' => 'bg-emerald-200 dark:bg-emerald-900',
            'danger', 'red' => 'bg-red-200 dark:bg-red-900',
            'warning', 'yellow', 'amber' => 'bg-amber-200 dark:bg-amber-900',
            'info', 'cyan' => 'bg-cyan-200 dark:bg-cyan-900',
            'purple' => 'bg-purple-200 dark:bg-purple-900',
            'pink' => 'bg-pink-200 dark:bg-pink-900',
            'gray', 'secondary' => 'bg-gray-200 dark:bg-gray-700',
            default => 'bg-gray-200 dark:bg-gray-700',
        };
    }

    /**
     * Get gradient fill classes (`from-* to-*`) for a progress/bar fill.
     *
     * Canonical source for the filled portion of bar/progress surfaces such as
     * the bar chart widget. Returns only the gradient stop classes — the consumer
     * pairs them with a `bg-gradient-to-{t,r}` direction utility. Chart hues are
     * intentionally literal (`blue` → `blue-500/600`, `green` → `green-500/600`,
     * `gray` → `slate-400/500`) to match the documented chart palette; the brand
     * `primary` alias and other raw hues are accepted too. Class strings are kept
     * literal so Tailwind's JIT scanner can see them, which is also what makes the
     * mapping a safe allow-list (no arbitrary class injection from owner-supplied
     * color names). Foreground companion: {@see getFillTextClasses()}.
     */
    public static function getGradientFillClasses(string $color): string
    {
        return match ($color) {
            'primary' => 'from-primary-500 to-primary-600',
            'blue' => 'from-blue-500 to-blue-600',
            'green' => 'from-green-500 to-green-600',
            'success', 'emerald' => 'from-emerald-500 to-emerald-600',
            'danger', 'red' => 'from-red-500 to-red-600',
            'warning', 'yellow', 'amber' => 'from-amber-500 to-amber-600',
            'info', 'cyan' => 'from-cyan-500 to-cyan-600',
            'sky' => 'from-sky-500 to-sky-600',
            'purple' => 'from-purple-500 to-purple-600',
            'violet' => 'from-violet-500 to-violet-600',
            'indigo' => 'from-indigo-500 to-indigo-600',
            'orange' => 'from-orange-500 to-orange-600',
            'teal' => 'from-teal-500 to-teal-600',
            'pink' => 'from-pink-500 to-pink-600',
            'gray', 'secondary' => 'from-slate-400 to-slate-500',
            default => 'from-primary-500 to-primary-600',
        };
    }

    /**
     * Get literal-hue accent text classes that match {@see getGradientFillClasses()}.
     *
     * Foreground companion for chart labels/values/icons. Unlike the semantic
     * {@see getTextColorClasses()} (which remaps `blue` → `primary`,
     * `green` → `emerald`), this resolver keeps the documented chart palette
     * literal (`blue` → `text-blue-600`, `green` → `text-green-600`,
     * `gray` → `text-slate-600`) so the accent hue always matches its bar fill.
     */
    public static function getFillTextClasses(string $color): string
    {
        return match ($color) {
            'primary' => 'text-primary-600 dark:text-primary-400',
            'blue' => 'text-blue-600 dark:text-blue-400',
            'green' => 'text-green-600 dark:text-green-400',
            'success', 'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'warning', 'yellow', 'amber' => 'text-amber-600 dark:text-amber-400',
            'info', 'cyan' => 'text-cyan-600 dark:text-cyan-400',
            'sky' => 'text-sky-600 dark:text-sky-400',
            'purple' => 'text-purple-600 dark:text-purple-400',
            'violet' => 'text-violet-600 dark:text-violet-400',
            'indigo' => 'text-indigo-600 dark:text-indigo-400',
            'orange' => 'text-orange-600 dark:text-orange-400',
            'teal' => 'text-teal-600 dark:text-teal-400',
            'pink' => 'text-pink-600 dark:text-pink-400',
            'gray', 'secondary' => 'text-slate-600 dark:text-slate-400',
            default => 'text-primary-600 dark:text-primary-400',
        };
    }

    /**
     * Get color for modal icon background.
     *
     * Canonical source for the rounded icon "chip" behind modal / confirmation
     * dialog icons. Semantic names map to fixed hues; `primary` and `gray` are
     * supported for neutral modals, and the default arm is neutral gray so a
     * modal without an explicit icon color does not look like a warning.
     */
    public static function getModalIconBgClass(string $color): string
    {
        return match ($color) {
            'danger', 'red' => 'bg-red-100 dark:bg-red-900/30',
            'warning', 'yellow' => 'bg-amber-100 dark:bg-amber-900/30',
            'success', 'green' => 'bg-emerald-100 dark:bg-emerald-900/30',
            'info', 'blue' => 'bg-blue-100 dark:bg-blue-900/30',
            'primary' => 'bg-primary-100 dark:bg-primary-900/30',
            'gray', 'secondary' => 'bg-gray-100 dark:bg-gray-700',
            default => 'bg-gray-100 dark:bg-gray-700',
        };
    }

    /**
     * Get color for modal icon text.
     *
     * Foreground companion to {@see getModalIconBgClass()}; same vocabulary and
     * neutral default.
     */
    public static function getModalIconTextClass(string $color): string
    {
        return match ($color) {
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'warning', 'yellow' => 'text-amber-600 dark:text-amber-400',
            'success', 'green' => 'text-emerald-600 dark:text-emerald-400',
            'info', 'blue' => 'text-blue-600 dark:text-blue-400',
            'primary' => 'text-primary-600 dark:text-primary-400',
            'gray', 'secondary' => 'text-gray-600 dark:text-gray-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }
}
