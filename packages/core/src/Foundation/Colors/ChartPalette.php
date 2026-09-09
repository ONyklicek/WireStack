<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

/**
 * Colour for a shape that *is* the value — a bar, a fill, a live dot, a
 * progress bar, the number beside them.
 *
 * The palette with its own idea of a fallback, and the reason it is not folded
 * into {@see TextPalette}: an unrecognised series still has to be visible, so
 * {@see self::fillText()} falls back to `primary` where a label falls back to
 * grey. The two look identical for every named hue and are not the same rule.
 *
 * {@see self::accentBg()} owns the `-500` step, which no other surface does:
 * {@see self::solidBg()} is the fill behind white text and sits a step darker
 * for most hues, and a badge tint sits far lighter.
 */
final class ChartPalette
{
    public static function solidBg(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'bg-primary-600',
            'blue' => 'bg-blue-600',
            'emerald' => 'bg-emerald-600',
            'green' => 'bg-green-600',
            'red' => 'bg-red-600',
            'amber' => 'bg-amber-500',
            'yellow' => 'bg-yellow-500',
            'cyan' => 'bg-cyan-500',
            'orange' => 'bg-orange-500',
            'lime' => 'bg-lime-600',
            'teal' => 'bg-teal-600',
            'sky' => 'bg-sky-500',
            'indigo' => 'bg-indigo-600',
            'violet' => 'bg-violet-600',
            'purple' => 'bg-purple-600',
            'fuchsia' => 'bg-fuchsia-600',
            'pink' => 'bg-pink-600',
            'rose' => 'bg-rose-600',
            'slate' => 'bg-slate-600',
            'zinc' => 'bg-zinc-600',
            'neutral' => 'bg-neutral-600',
            'stone' => 'bg-stone-600',
            'black' => 'bg-gray-900 dark:bg-white',
            'white' => 'bg-white dark:bg-gray-900',
            'gray', 'secondary' => 'bg-gray-600',
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
    public static function softBg(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'bg-primary-200 dark:bg-primary-900',
            'blue' => 'bg-blue-200 dark:bg-blue-900',
            'emerald' => 'bg-emerald-200 dark:bg-emerald-900',
            'green' => 'bg-green-200 dark:bg-green-900',
            'red' => 'bg-red-200 dark:bg-red-900',
            'amber' => 'bg-amber-200 dark:bg-amber-900',
            'yellow' => 'bg-yellow-200 dark:bg-yellow-900',
            'cyan' => 'bg-cyan-200 dark:bg-cyan-900',
            'orange' => 'bg-orange-200 dark:bg-orange-900',
            'lime' => 'bg-lime-200 dark:bg-lime-900',
            'teal' => 'bg-teal-200 dark:bg-teal-900',
            'sky' => 'bg-sky-200 dark:bg-sky-900',
            'indigo' => 'bg-indigo-200 dark:bg-indigo-900',
            'violet' => 'bg-violet-200 dark:bg-violet-900',
            'purple' => 'bg-purple-200 dark:bg-purple-900',
            'fuchsia' => 'bg-fuchsia-200 dark:bg-fuchsia-900',
            'pink' => 'bg-pink-200 dark:bg-pink-900',
            'rose' => 'bg-rose-200 dark:bg-rose-900',
            'slate' => 'bg-slate-200 dark:bg-slate-900',
            'zinc' => 'bg-zinc-200 dark:bg-zinc-900',
            'neutral' => 'bg-neutral-200 dark:bg-neutral-900',
            'stone' => 'bg-stone-200 dark:bg-stone-900',
            'black' => 'bg-gray-800 dark:bg-gray-200',
            'white' => 'bg-gray-100 dark:bg-gray-800',
            'gray', 'secondary' => 'bg-gray-200 dark:bg-gray-700',
            default => 'bg-gray-200 dark:bg-gray-700',
        };
    }

    /**
     * The bright accent — a live dot, a progress bar, a filled star.
     *
     * The `-500` step, which no other resolver owns: {@see self::solidBg()}
     * is the fill behind white text and sits a step darker for most hues, and a
     * badge tint sits far lighter. Three surfaces wanted this and each had
     * written its own literal.
     */
    public static function accentBg(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'bg-primary-500',
            'blue' => 'bg-blue-500',
            'emerald' => 'bg-emerald-500',
            'green' => 'bg-green-500',
            'red' => 'bg-red-500',
            'amber' => 'bg-amber-500',
            'yellow' => 'bg-yellow-500',
            'cyan' => 'bg-cyan-500',
            'orange' => 'bg-orange-500',
            'lime' => 'bg-lime-500',
            'teal' => 'bg-teal-500',
            'sky' => 'bg-sky-500',
            'indigo' => 'bg-indigo-500',
            'violet' => 'bg-violet-500',
            'purple' => 'bg-purple-500',
            'fuchsia' => 'bg-fuchsia-500',
            'pink' => 'bg-pink-500',
            'rose' => 'bg-rose-500',
            'slate' => 'bg-slate-500',
            'zinc' => 'bg-zinc-500',
            'neutral' => 'bg-neutral-500',
            'stone' => 'bg-stone-500',
            'gray' => 'bg-gray-500',
            'black' => 'bg-gray-900 dark:bg-white',
            'white' => 'bg-white dark:bg-gray-900',
            default => 'bg-gray-500',
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
     * `primary` alias and every raw hue are accepted too. Class strings are kept
     * literal so Tailwind's JIT scanner can see them, which is also what makes the
     * mapping a safe allow-list (no arbitrary class injection from owner-supplied
     * color names). Foreground companion: {@see getFillTextClasses()}.
     *
     * NOTE: every hue branch here must also be listed literally in
     * `resources/views/widgets/bar-chart/safelist.blade.php` (guarded by
     * BarChartSafelistTest) so a consuming app's Tailwind build generates them.
     */
    public static function gradientFill(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'from-primary-500 to-primary-600',
            'blue' => 'from-blue-500 to-blue-600',
            'green' => 'from-green-500 to-green-600',
            'emerald' => 'from-emerald-500 to-emerald-600',
            'red' => 'from-red-500 to-red-600',
            'amber' => 'from-amber-500 to-amber-600',
            'yellow' => 'from-yellow-500 to-yellow-600',
            'cyan' => 'from-cyan-500 to-cyan-600',
            'orange' => 'from-orange-500 to-orange-600',
            'lime' => 'from-lime-500 to-lime-600',
            'teal' => 'from-teal-500 to-teal-600',
            'sky' => 'from-sky-500 to-sky-600',
            'indigo' => 'from-indigo-500 to-indigo-600',
            'violet' => 'from-violet-500 to-violet-600',
            'purple' => 'from-purple-500 to-purple-600',
            'fuchsia' => 'from-fuchsia-500 to-fuchsia-600',
            'pink' => 'from-pink-500 to-pink-600',
            'rose' => 'from-rose-500 to-rose-600',
            'slate' => 'from-slate-500 to-slate-600',
            'zinc' => 'from-zinc-500 to-zinc-600',
            'neutral' => 'from-neutral-500 to-neutral-600',
            'stone' => 'from-stone-500 to-stone-600',
            'black' => 'from-gray-700 to-gray-900',
            'white' => 'from-gray-100 to-gray-300',
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
    public static function fillText(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'text-primary-600 dark:text-primary-400',
            'blue' => 'text-blue-600 dark:text-blue-400',
            'green' => 'text-green-600 dark:text-green-400',
            'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'red' => 'text-red-600 dark:text-red-400',
            'amber' => 'text-amber-600 dark:text-amber-400',
            'yellow' => 'text-yellow-600 dark:text-yellow-400',
            'cyan' => 'text-cyan-600 dark:text-cyan-400',
            'orange' => 'text-orange-600 dark:text-orange-400',
            'lime' => 'text-lime-600 dark:text-lime-400',
            'teal' => 'text-teal-600 dark:text-teal-400',
            'sky' => 'text-sky-600 dark:text-sky-400',
            'indigo' => 'text-indigo-600 dark:text-indigo-400',
            'violet' => 'text-violet-600 dark:text-violet-400',
            'purple' => 'text-purple-600 dark:text-purple-400',
            'fuchsia' => 'text-fuchsia-600 dark:text-fuchsia-400',
            'pink' => 'text-pink-600 dark:text-pink-400',
            'rose' => 'text-rose-600 dark:text-rose-400',
            'slate' => 'text-slate-600 dark:text-slate-400',
            'zinc' => 'text-zinc-600 dark:text-zinc-400',
            'neutral' => 'text-neutral-600 dark:text-neutral-400',
            'stone' => 'text-stone-600 dark:text-stone-400',
            'black' => 'text-gray-900 dark:text-white',
            'white' => 'text-white dark:text-gray-900',
            'gray', 'secondary' => 'text-slate-600 dark:text-slate-400',
            default => 'text-primary-600 dark:text-primary-400',
        };
    }
}
