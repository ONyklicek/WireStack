<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

/**
 * Colour for something you click — solid, outlined, ghost, icon-only, quiet, and
 * the submit button a modal ends with.
 *
 * Six variants of one surface rather than six surfaces: each is the same
 * decision about the same element, made at a different weight, and a hue that
 * reads well solid has to read well outlined too. Keeping them together is what
 * makes that checkable.
 *
 * The trait's instance resolvers still exist and still read `$this->getColor()`;
 * they delegate here, so a component keeps asking the way it always has.
 */
final class ButtonPalette
{
    /**
     * Resolved class strings, keyed by variant and hue.
     *
     * Per-palette rather than one shared map, which is the point of the split:
     * the key no longer has to carry a prefix to keep two surfaces from
     * colliding, and a palette nobody touched holds nothing.
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    /** Canonical outlined (bordered) vocabulary, callable from anywhere. */
    public static function outlined(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return self::$cache["outlined_$color"] ??= match ($color) {
            'primary' => 'border border-primary-600 text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-900/20',
            'blue' => 'border border-blue-600 text-blue-600 hover:bg-blue-50 focus:ring-blue-500 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-900/20',
            'emerald' => 'border border-emerald-600 text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:border-emerald-400 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'green' => 'border border-green-600 text-green-600 hover:bg-green-50 focus:ring-green-500 dark:border-green-400 dark:text-green-400 dark:hover:bg-green-900/20',
            'red' => 'border border-red-600 text-red-600 hover:bg-red-50 focus:ring-red-500 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-900/20',
            'amber' => 'border border-amber-600 text-amber-600 hover:bg-amber-50 focus:ring-amber-500 dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'yellow' => 'border border-yellow-600 text-yellow-600 hover:bg-yellow-50 focus:ring-yellow-500 dark:border-yellow-400 dark:text-yellow-400 dark:hover:bg-yellow-900/20',
            'cyan' => 'border border-cyan-600 text-cyan-600 hover:bg-cyan-50 focus:ring-cyan-500 dark:border-cyan-400 dark:text-cyan-400 dark:hover:bg-cyan-900/20',
            'orange' => 'border border-orange-600 text-orange-600 hover:bg-orange-50 focus:ring-orange-500 dark:border-orange-400 dark:text-orange-400 dark:hover:bg-orange-900/20',
            'lime' => 'border border-lime-600 text-lime-600 hover:bg-lime-50 focus:ring-lime-500 dark:border-lime-400 dark:text-lime-400 dark:hover:bg-lime-900/20',
            'teal' => 'border border-teal-600 text-teal-600 hover:bg-teal-50 focus:ring-teal-500 dark:border-teal-400 dark:text-teal-400 dark:hover:bg-teal-900/20',
            'sky' => 'border border-sky-600 text-sky-600 hover:bg-sky-50 focus:ring-sky-500 dark:border-sky-400 dark:text-sky-400 dark:hover:bg-sky-900/20',
            'indigo' => 'border border-indigo-600 text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:border-indigo-400 dark:text-indigo-400 dark:hover:bg-indigo-900/20',
            'violet' => 'border border-violet-600 text-violet-600 hover:bg-violet-50 focus:ring-violet-500 dark:border-violet-400 dark:text-violet-400 dark:hover:bg-violet-900/20',
            'purple' => 'border border-purple-600 text-purple-600 hover:bg-purple-50 focus:ring-purple-500 dark:border-purple-400 dark:text-purple-400 dark:hover:bg-purple-900/20',
            'fuchsia' => 'border border-fuchsia-600 text-fuchsia-600 hover:bg-fuchsia-50 focus:ring-fuchsia-500 dark:border-fuchsia-400 dark:text-fuchsia-400 dark:hover:bg-fuchsia-900/20',
            'pink' => 'border border-pink-600 text-pink-600 hover:bg-pink-50 focus:ring-pink-500 dark:border-pink-400 dark:text-pink-400 dark:hover:bg-pink-900/20',
            'rose' => 'border border-rose-600 text-rose-600 hover:bg-rose-50 focus:ring-rose-500 dark:border-rose-400 dark:text-rose-400 dark:hover:bg-rose-900/20',
            'slate' => 'border border-slate-600 text-slate-600 hover:bg-slate-50 focus:ring-slate-500 dark:border-slate-400 dark:text-slate-400 dark:hover:bg-slate-900/20',
            'zinc' => 'border border-zinc-600 text-zinc-600 hover:bg-zinc-50 focus:ring-zinc-500 dark:border-zinc-400 dark:text-zinc-400 dark:hover:bg-zinc-900/20',
            'neutral' => 'border border-neutral-600 text-neutral-600 hover:bg-neutral-50 focus:ring-neutral-500 dark:border-neutral-400 dark:text-neutral-400 dark:hover:bg-neutral-900/20',
            'stone' => 'border border-stone-600 text-stone-600 hover:bg-stone-50 focus:ring-stone-500 dark:border-stone-400 dark:text-stone-400 dark:hover:bg-stone-900/20',
            'black' => 'border border-gray-900 text-gray-900 hover:bg-gray-100 focus:ring-gray-500 dark:border-white dark:text-white dark:hover:bg-white/10',
            'white' => 'border border-white text-white hover:bg-white/10 focus:ring-gray-300 dark:border-gray-900 dark:text-gray-900 dark:hover:bg-gray-900/10',
            'gray',
            'secondary' => 'border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
            default => 'border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-gray-500',
        };
    }

    public static function modalSubmit(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'red' => 'bg-red-600 hover:bg-red-700 active:bg-red-800 focus:ring-red-500',
            'blue' => 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800 focus:ring-blue-500',
            'emerald' => 'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 focus:ring-emerald-500',
            'green' => 'bg-green-600 hover:bg-green-700 active:bg-green-800 focus:ring-green-500',
            'amber' => 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-500',
            'yellow' => 'bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 focus:ring-yellow-500',
            'cyan' => 'bg-cyan-600 hover:bg-cyan-700 active:bg-cyan-800 focus:ring-cyan-500',
            'orange' => 'bg-orange-500 hover:bg-orange-600 active:bg-orange-700 focus:ring-orange-500',
            'lime' => 'bg-lime-600 hover:bg-lime-700 active:bg-lime-800 focus:ring-lime-500',
            'teal' => 'bg-teal-600 hover:bg-teal-700 active:bg-teal-800 focus:ring-teal-500',
            'sky' => 'bg-sky-600 hover:bg-sky-700 active:bg-sky-800 focus:ring-sky-500',
            'indigo' => 'bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 focus:ring-indigo-500',
            'violet' => 'bg-violet-600 hover:bg-violet-700 active:bg-violet-800 focus:ring-violet-500',
            'purple' => 'bg-purple-600 hover:bg-purple-700 active:bg-purple-800 focus:ring-purple-500',
            'fuchsia' => 'bg-fuchsia-600 hover:bg-fuchsia-700 active:bg-fuchsia-800 focus:ring-fuchsia-500',
            'pink' => 'bg-pink-600 hover:bg-pink-700 active:bg-pink-800 focus:ring-pink-500',
            'rose' => 'bg-rose-600 hover:bg-rose-700 active:bg-rose-800 focus:ring-rose-500',
            'slate' => 'bg-slate-600 hover:bg-slate-700 active:bg-slate-800 focus:ring-slate-500',
            'zinc' => 'bg-zinc-600 hover:bg-zinc-700 active:bg-zinc-800 focus:ring-zinc-500',
            'neutral' => 'bg-neutral-600 hover:bg-neutral-700 active:bg-neutral-800 focus:ring-neutral-500',
            'stone' => 'bg-stone-600 hover:bg-stone-700 active:bg-stone-800 focus:ring-stone-500',
            'black' => 'bg-gray-900 hover:bg-black active:bg-black focus:ring-gray-500',
            'white' => 'bg-white !text-gray-900 border border-gray-200 hover:bg-gray-100 active:bg-gray-200 focus:ring-gray-300',
            default => 'bg-primary-600 hover:bg-primary-700 active:bg-primary-800 focus:ring-primary-500',
        };
    }

    /**
     * Get solid (filled) button color classes.
     */
    public static function solid(string $color): string
    {
        $color = SemanticPalette::hue($color);
        $cacheKey = "solid_$color";

        return self::$cache[$cacheKey] ??= match ($color) {
            'primary' => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500 dark:bg-primary-500 dark:hover:bg-primary-600',
            'blue' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600',
            'emerald' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600',
            'green' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600',
            'red' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
            'amber' => 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-500 dark:bg-amber-400 dark:hover:bg-amber-500',
            'yellow' => 'bg-yellow-500 text-white hover:bg-yellow-600 focus:ring-yellow-500 dark:bg-yellow-400 dark:hover:bg-yellow-500',
            'cyan' => 'bg-cyan-600 text-white hover:bg-cyan-700 focus:ring-cyan-500 dark:bg-cyan-500 dark:hover:bg-cyan-600',
            'orange' => 'bg-orange-500 text-white hover:bg-orange-600 focus:ring-orange-500 dark:bg-orange-400 dark:hover:bg-orange-500',
            'lime' => 'bg-lime-600 text-white hover:bg-lime-700 focus:ring-lime-500 dark:bg-lime-500 dark:hover:bg-lime-600',
            'teal' => 'bg-teal-600 text-white hover:bg-teal-700 focus:ring-teal-500 dark:bg-teal-500 dark:hover:bg-teal-600',
            'sky' => 'bg-sky-600 text-white hover:bg-sky-700 focus:ring-sky-500 dark:bg-sky-500 dark:hover:bg-sky-600',
            'indigo' => 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500 dark:bg-indigo-500 dark:hover:bg-indigo-600',
            'violet' => 'bg-violet-600 text-white hover:bg-violet-700 focus:ring-violet-500 dark:bg-violet-500 dark:hover:bg-violet-600',
            'purple' => 'bg-purple-600 text-white hover:bg-purple-700 focus:ring-purple-500 dark:bg-purple-500 dark:hover:bg-purple-600',
            'fuchsia' => 'bg-fuchsia-600 text-white hover:bg-fuchsia-700 focus:ring-fuchsia-500 dark:bg-fuchsia-500 dark:hover:bg-fuchsia-600',
            'pink' => 'bg-pink-600 text-white hover:bg-pink-700 focus:ring-pink-500 dark:bg-pink-500 dark:hover:bg-pink-600',
            'rose' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500 dark:bg-rose-500 dark:hover:bg-rose-600',
            'slate' => 'bg-slate-600 text-white hover:bg-slate-700 focus:ring-slate-500 dark:bg-slate-500 dark:hover:bg-slate-600',
            'zinc' => 'bg-zinc-600 text-white hover:bg-zinc-700 focus:ring-zinc-500 dark:bg-zinc-500 dark:hover:bg-zinc-600',
            'neutral' => 'bg-neutral-600 text-white hover:bg-neutral-700 focus:ring-neutral-500 dark:bg-neutral-500 dark:hover:bg-neutral-600',
            'stone' => 'bg-stone-600 text-white hover:bg-stone-700 focus:ring-stone-500 dark:bg-stone-500 dark:hover:bg-stone-600',
            'black' => 'bg-gray-900 text-white hover:bg-black focus:ring-gray-500 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100',
            'white' => 'bg-white text-gray-900 border border-gray-200 hover:bg-gray-50 focus:ring-gray-300 dark:bg-gray-900 dark:text-white dark:border-gray-700 dark:hover:bg-gray-800',
            'gray',
            'secondary' => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
            default => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
        };
    }

    /**
     * Get ghost/link-style color classes (for dropdown items).
     */
    public static function ghost(string $color): string
    {
        $color = SemanticPalette::hue($color);
        $cacheKey = "ghost_$color";

        return self::$cache[$cacheKey] ??= match ($color) {
            'red' => 'text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            'amber' => 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'yellow' => 'text-yellow-600 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-900/20',
            'emerald' => 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20',
            'green' => 'text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20',
            'primary' => 'text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20',
            'blue' => 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20',
            'cyan' => 'text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/20',
            'orange' => 'text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20',
            'lime' => 'text-lime-600 dark:text-lime-400 hover:bg-lime-50 dark:hover:bg-lime-900/20',
            'teal' => 'text-teal-600 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-900/20',
            'sky' => 'text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20',
            'indigo' => 'text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
            'violet' => 'text-violet-600 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20',
            'purple' => 'text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20',
            'fuchsia' => 'text-fuchsia-600 dark:text-fuchsia-400 hover:bg-fuchsia-50 dark:hover:bg-fuchsia-900/20',
            'pink' => 'text-pink-600 dark:text-pink-400 hover:bg-pink-50 dark:hover:bg-pink-900/20',
            'rose' => 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20',
            'slate' => 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-900/20',
            'zinc' => 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-900/20',
            'neutral' => 'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-900/20',
            'stone' => 'text-stone-600 dark:text-stone-400 hover:bg-stone-50 dark:hover:bg-stone-900/20',
            'black' => 'text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-white/10',
            'white' => 'text-white dark:text-gray-900 hover:bg-white/10 dark:hover:bg-gray-100',
            default => 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700',
        };
    }

    /**
     * Get icon-only button color classes.
     */
    public static function iconButton(string $color): string
    {
        $color = SemanticPalette::hue($color);
        $cacheKey = "icon_$color";

        return self::$cache[$cacheKey] ??= match ($color) {
            'primary' => 'text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:text-primary-400 dark:hover:bg-primary-900/20',
            'blue' => 'text-blue-600 hover:bg-blue-50 focus:ring-blue-500 dark:text-blue-400 dark:hover:bg-blue-900/20',
            'red' => 'text-red-600 hover:bg-red-50 focus:ring-red-500 dark:text-red-400 dark:hover:bg-red-900/20',
            'emerald' => 'text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'green' => 'text-green-600 hover:bg-green-50 focus:ring-green-500 dark:text-green-400 dark:hover:bg-green-900/20',
            'amber' => 'text-amber-600 hover:bg-amber-50 focus:ring-amber-500 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'yellow' => 'text-yellow-600 hover:bg-yellow-50 focus:ring-yellow-500 dark:text-yellow-400 dark:hover:bg-yellow-900/20',
            'cyan' => 'text-cyan-600 hover:bg-cyan-50 focus:ring-cyan-500 dark:text-cyan-400 dark:hover:bg-cyan-900/20',
            'orange' => 'text-orange-600 hover:bg-orange-50 focus:ring-orange-500 dark:text-orange-400 dark:hover:bg-orange-900/20',
            'lime' => 'text-lime-600 hover:bg-lime-50 focus:ring-lime-500 dark:text-lime-400 dark:hover:bg-lime-900/20',
            'teal' => 'text-teal-600 hover:bg-teal-50 focus:ring-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20',
            'sky' => 'text-sky-600 hover:bg-sky-50 focus:ring-sky-500 dark:text-sky-400 dark:hover:bg-sky-900/20',
            'indigo' => 'text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-900/20',
            'violet' => 'text-violet-600 hover:bg-violet-50 focus:ring-violet-500 dark:text-violet-400 dark:hover:bg-violet-900/20',
            'purple' => 'text-purple-600 hover:bg-purple-50 focus:ring-purple-500 dark:text-purple-400 dark:hover:bg-purple-900/20',
            'fuchsia' => 'text-fuchsia-600 hover:bg-fuchsia-50 focus:ring-fuchsia-500 dark:text-fuchsia-400 dark:hover:bg-fuchsia-900/20',
            'pink' => 'text-pink-600 hover:bg-pink-50 focus:ring-pink-500 dark:text-pink-400 dark:hover:bg-pink-900/20',
            'rose' => 'text-rose-600 hover:bg-rose-50 focus:ring-rose-500 dark:text-rose-400 dark:hover:bg-rose-900/20',
            'slate' => 'text-slate-600 hover:bg-slate-50 focus:ring-slate-500 dark:text-slate-400 dark:hover:bg-slate-900/20',
            'zinc' => 'text-zinc-600 hover:bg-zinc-50 focus:ring-zinc-500 dark:text-zinc-400 dark:hover:bg-zinc-900/20',
            'neutral' => 'text-neutral-600 hover:bg-neutral-50 focus:ring-neutral-500 dark:text-neutral-400 dark:hover:bg-neutral-900/20',
            'stone' => 'text-stone-600 hover:bg-stone-50 focus:ring-stone-500 dark:text-stone-400 dark:hover:bg-stone-900/20',
            'black' => 'text-gray-900 hover:bg-gray-100 focus:ring-gray-500 dark:text-white dark:hover:bg-white/10',
            'white' => 'text-white hover:bg-white/10 focus:ring-gray-300 dark:text-gray-900 dark:hover:bg-gray-100',
            default => 'text-gray-500 hover:bg-gray-100 focus:ring-gray-500 dark:text-gray-400 dark:hover:bg-gray-700',
        };
    }

    /**
     * Get "quiet" button color classes (neutral at rest, color on intent).
     *
     * The resting state is deliberately achromatic — `text-gray-600 dark:text-gray-300`,
     * a transparent background — so a row full of these buttons stops competing with
     * the data. The owner-supplied hue only appears on `hover:`/`focus:` (a tinted
     * background plus, for non-neutral hues, a colored label). Every arm sets an
     * explicit `focus:ring-{hue}-500` because the shared button base always applies
     * `focus:ring-2`; without a ring color the keyboard focus indicator would be
     * invisible (WCAG 2.4.7).
     *
     * Exception: `danger`/`red` keep their red label **at rest**. Touch devices have
     * no hover, so a destructive action must read as dangerous without interaction —
     * matching how GitHub/Linear keep "Delete" red in a quiet menu.
     *
     * Literal class strings (JIT-safe allow-list), same convention as the sibling
     * resolvers above.
     */
    public static function quiet(string $color): string
    {
        $color = SemanticPalette::hue($color);
        $cacheKey = "quiet_$color";

        // Neutral resting label shared by every non-destructive hue.
        $rest = 'text-gray-600 dark:text-gray-300';

        return self::$cache[$cacheKey] ??= match ($color) {
            // Destructive stays legible at rest (no reliance on hover / touch).
            'red' => 'text-red-600 dark:text-red-400 hover:bg-red-50 focus:ring-red-500 dark:hover:bg-red-900/20',

            'primary' => "$rest hover:text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:hover:text-primary-400 dark:hover:bg-primary-900/20",
            'blue' => "$rest hover:text-blue-600 hover:bg-blue-50 focus:ring-blue-500 dark:hover:text-blue-400 dark:hover:bg-blue-900/20",
            'emerald' => "$rest hover:text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20",
            'green' => "$rest hover:text-green-600 hover:bg-green-50 focus:ring-green-500 dark:hover:text-green-400 dark:hover:bg-green-900/20",
            'amber' => "$rest hover:text-amber-600 hover:bg-amber-50 focus:ring-amber-500 dark:hover:text-amber-400 dark:hover:bg-amber-900/20",
            'yellow' => "$rest hover:text-yellow-600 hover:bg-yellow-50 focus:ring-yellow-500 dark:hover:text-yellow-400 dark:hover:bg-yellow-900/20",
            'cyan' => "$rest hover:text-cyan-600 hover:bg-cyan-50 focus:ring-cyan-500 dark:hover:text-cyan-400 dark:hover:bg-cyan-900/20",
            'orange' => "$rest hover:text-orange-600 hover:bg-orange-50 focus:ring-orange-500 dark:hover:text-orange-400 dark:hover:bg-orange-900/20",
            'lime' => "$rest hover:text-lime-600 hover:bg-lime-50 focus:ring-lime-500 dark:hover:text-lime-400 dark:hover:bg-lime-900/20",
            'teal' => "$rest hover:text-teal-600 hover:bg-teal-50 focus:ring-teal-500 dark:hover:text-teal-400 dark:hover:bg-teal-900/20",
            'sky' => "$rest hover:text-sky-600 hover:bg-sky-50 focus:ring-sky-500 dark:hover:text-sky-400 dark:hover:bg-sky-900/20",
            'indigo' => "$rest hover:text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:hover:text-indigo-400 dark:hover:bg-indigo-900/20",
            'violet' => "$rest hover:text-violet-600 hover:bg-violet-50 focus:ring-violet-500 dark:hover:text-violet-400 dark:hover:bg-violet-900/20",
            'purple' => "$rest hover:text-purple-600 hover:bg-purple-50 focus:ring-purple-500 dark:hover:text-purple-400 dark:hover:bg-purple-900/20",
            'fuchsia' => "$rest hover:text-fuchsia-600 hover:bg-fuchsia-50 focus:ring-fuchsia-500 dark:hover:text-fuchsia-400 dark:hover:bg-fuchsia-900/20",
            'pink' => "$rest hover:text-pink-600 hover:bg-pink-50 focus:ring-pink-500 dark:hover:text-pink-400 dark:hover:bg-pink-900/20",
            'rose' => "$rest hover:text-rose-600 hover:bg-rose-50 focus:ring-rose-500 dark:hover:text-rose-400 dark:hover:bg-rose-900/20",
            'slate' => "$rest hover:text-slate-700 hover:bg-slate-100 focus:ring-slate-500 dark:hover:text-slate-200 dark:hover:bg-slate-800/60",
            'zinc' => "$rest hover:text-zinc-700 hover:bg-zinc-100 focus:ring-zinc-500 dark:hover:text-zinc-200 dark:hover:bg-zinc-800/60",
            'neutral' => "$rest hover:text-neutral-700 hover:bg-neutral-100 focus:ring-neutral-500 dark:hover:text-neutral-200 dark:hover:bg-neutral-800/60",
            'stone' => "$rest hover:text-stone-700 hover:bg-stone-100 focus:ring-stone-500 dark:hover:text-stone-200 dark:hover:bg-stone-800/60",
            'black' => "$rest hover:text-gray-900 hover:bg-gray-100 focus:ring-gray-500 dark:hover:text-white dark:hover:bg-white/10",
            'white' => "$rest hover:text-gray-900 hover:bg-gray-100 focus:ring-gray-300 dark:hover:text-white dark:hover:bg-white/10",
            'gray', 'secondary' => "$rest hover:text-gray-900 hover:bg-gray-100 focus:ring-gray-500 dark:hover:text-white dark:hover:bg-gray-700",
            default => "$rest hover:text-gray-900 hover:bg-gray-100 focus:ring-gray-500 dark:hover:text-white dark:hover:bg-gray-700",
        };
    }
}
