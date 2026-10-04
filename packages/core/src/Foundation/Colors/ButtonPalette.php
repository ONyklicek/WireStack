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
            'emerald' => 'border border-emerald-700 text-emerald-700 hover:bg-emerald-50 focus:ring-emerald-500 dark:border-emerald-400 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'green' => 'border border-green-700 text-green-700 hover:bg-green-50 focus:ring-green-500 dark:border-green-400 dark:text-green-400 dark:hover:bg-green-900/20',
            'red' => 'border border-red-700 text-red-700 hover:bg-red-50 focus:ring-red-500 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-900/20',
            'amber' => 'border border-amber-700 text-amber-700 hover:bg-amber-50 focus:ring-amber-500 dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'yellow' => 'border border-yellow-700 text-yellow-700 hover:bg-yellow-50 focus:ring-yellow-500 dark:border-yellow-400 dark:text-yellow-400 dark:hover:bg-yellow-900/20',
            'cyan' => 'border border-cyan-700 text-cyan-700 hover:bg-cyan-50 focus:ring-cyan-500 dark:border-cyan-400 dark:text-cyan-400 dark:hover:bg-cyan-900/20',
            'orange' => 'border border-orange-700 text-orange-700 hover:bg-orange-50 focus:ring-orange-500 dark:border-orange-400 dark:text-orange-400 dark:hover:bg-orange-900/20',
            'lime' => 'border border-lime-700 text-lime-700 hover:bg-lime-50 focus:ring-lime-500 dark:border-lime-400 dark:text-lime-400 dark:hover:bg-lime-900/20',
            'teal' => 'border border-teal-700 text-teal-700 hover:bg-teal-50 focus:ring-teal-500 dark:border-teal-400 dark:text-teal-400 dark:hover:bg-teal-900/20',
            'sky' => 'border border-sky-700 text-sky-700 hover:bg-sky-50 focus:ring-sky-500 dark:border-sky-400 dark:text-sky-400 dark:hover:bg-sky-900/20',
            'indigo' => 'border border-indigo-600 text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:border-indigo-400 dark:text-indigo-400 dark:hover:bg-indigo-900/20',
            'violet' => 'border border-violet-600 text-violet-600 hover:bg-violet-50 focus:ring-violet-500 dark:border-violet-400 dark:text-violet-400 dark:hover:bg-violet-900/20',
            'purple' => 'border border-purple-600 text-purple-600 hover:bg-purple-50 focus:ring-purple-500 dark:border-purple-400 dark:text-purple-400 dark:hover:bg-purple-900/20',
            'fuchsia' => 'border border-fuchsia-700 text-fuchsia-700 hover:bg-fuchsia-50 focus:ring-fuchsia-500 dark:border-fuchsia-400 dark:text-fuchsia-400 dark:hover:bg-fuchsia-900/20',
            'pink' => 'border border-pink-700 text-pink-700 hover:bg-pink-50 focus:ring-pink-500 dark:border-pink-400 dark:text-pink-400 dark:hover:bg-pink-900/20',
            'rose' => 'border border-rose-700 text-rose-700 hover:bg-rose-50 focus:ring-rose-500 dark:border-rose-400 dark:text-rose-400 dark:hover:bg-rose-900/20',
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
            'emerald' => 'bg-emerald-700 hover:bg-emerald-800 active:bg-emerald-900 focus:ring-emerald-500 dark:bg-emerald-400 dark:text-gray-950 dark:hover:bg-emerald-300 dark:active:bg-emerald-200',
            'green' => 'bg-green-700 hover:bg-green-800 active:bg-green-900 focus:ring-green-500 dark:bg-green-400 dark:text-gray-950 dark:hover:bg-green-300 dark:active:bg-green-200',
            'amber' => 'bg-amber-400 !text-gray-950 hover:bg-amber-500 active:bg-amber-600 focus:ring-amber-500 dark:hover:bg-amber-300 dark:active:bg-amber-200',
            'yellow' => 'bg-yellow-400 !text-gray-950 hover:bg-yellow-500 active:bg-yellow-600 focus:ring-yellow-500 dark:hover:bg-yellow-300 dark:active:bg-yellow-200',
            'cyan' => 'bg-cyan-700 hover:bg-cyan-800 active:bg-cyan-900 focus:ring-cyan-500 dark:bg-cyan-400 dark:text-gray-950 dark:hover:bg-cyan-300 dark:active:bg-cyan-200',
            'orange' => 'bg-orange-400 !text-gray-950 hover:bg-orange-500 active:bg-orange-600 focus:ring-orange-500 dark:hover:bg-orange-300 dark:active:bg-orange-200',
            'lime' => 'bg-lime-400 !text-gray-950 hover:bg-lime-500 active:bg-lime-600 focus:ring-lime-500 dark:hover:bg-lime-300 dark:active:bg-lime-200',
            'teal' => 'bg-teal-700 hover:bg-teal-800 active:bg-teal-900 focus:ring-teal-500 dark:bg-teal-400 dark:text-gray-950 dark:hover:bg-teal-300 dark:active:bg-teal-200',
            'sky' => 'bg-sky-700 hover:bg-sky-800 active:bg-sky-900 focus:ring-sky-500 dark:bg-sky-400 dark:text-gray-950 dark:hover:bg-sky-300 dark:active:bg-sky-200',
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
            'primary' => 'bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500',
            'blue' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
            'emerald' => 'bg-emerald-700 text-white hover:bg-emerald-800 focus:ring-emerald-500 dark:bg-emerald-400 dark:text-gray-950 dark:hover:bg-emerald-300',
            'green' => 'bg-green-700 text-white hover:bg-green-800 focus:ring-green-500 dark:bg-green-400 dark:text-gray-950 dark:hover:bg-green-300',
            'red' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
            'amber' => 'bg-amber-400 text-gray-950 hover:bg-amber-500 focus:ring-amber-500 dark:hover:bg-amber-300',
            'yellow' => 'bg-yellow-400 text-gray-950 hover:bg-yellow-500 focus:ring-yellow-500 dark:hover:bg-yellow-300',
            'cyan' => 'bg-cyan-700 text-white hover:bg-cyan-800 focus:ring-cyan-500 dark:bg-cyan-400 dark:text-gray-950 dark:hover:bg-cyan-300',
            'orange' => 'bg-orange-400 text-gray-950 hover:bg-orange-500 focus:ring-orange-500 dark:hover:bg-orange-300',
            'lime' => 'bg-lime-400 text-gray-950 hover:bg-lime-500 focus:ring-lime-500 dark:hover:bg-lime-300',
            'teal' => 'bg-teal-700 text-white hover:bg-teal-800 focus:ring-teal-500 dark:bg-teal-400 dark:text-gray-950 dark:hover:bg-teal-300',
            'sky' => 'bg-sky-700 text-white hover:bg-sky-800 focus:ring-sky-500 dark:bg-sky-400 dark:text-gray-950 dark:hover:bg-sky-300',
            'indigo' => 'bg-indigo-600 text-white hover:bg-indigo-700 focus:ring-indigo-500',
            'violet' => 'bg-violet-600 text-white hover:bg-violet-700 focus:ring-violet-500',
            'purple' => 'bg-purple-600 text-white hover:bg-purple-700 focus:ring-purple-500',
            'fuchsia' => 'bg-fuchsia-600 text-white hover:bg-fuchsia-700 focus:ring-fuchsia-500',
            'pink' => 'bg-pink-600 text-white hover:bg-pink-700 focus:ring-pink-500',
            'rose' => 'bg-rose-600 text-white hover:bg-rose-700 focus:ring-rose-500',
            'slate' => 'bg-slate-600 text-white hover:bg-slate-700 focus:ring-slate-500',
            'zinc' => 'bg-zinc-600 text-white hover:bg-zinc-700 focus:ring-zinc-500',
            'neutral' => 'bg-neutral-600 text-white hover:bg-neutral-700 focus:ring-neutral-500',
            'stone' => 'bg-stone-600 text-white hover:bg-stone-700 focus:ring-stone-500',
            'black' => 'bg-gray-900 text-white hover:bg-black focus:ring-gray-500 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100',
            'white' => 'bg-white text-gray-900 border border-gray-200 hover:bg-gray-50 focus:ring-gray-300 dark:bg-gray-900 dark:text-white dark:border-gray-700 dark:hover:bg-gray-800',
            'gray',
            'secondary' => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
            default => 'bg-gray-100 text-gray-600 hover:bg-gray-200 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600',
        };
    }

    /**
     * Get soft (tinted pill) color classes: a light ground, darker ink, and a
     * ground that deepens on hover.
     *
     * The weight between `ghost()` (no ground until hovered) and `solid()` — an
     * action that sits on a white row and has to be seen without shouting, the
     * way a notification's actions do. Roles resolve through `wire-core.colors`
     * like every other variant; anything unrecognised is the neutral gray pill.
     */
    public static function soft(string $color): string
    {
        $color = SemanticPalette::hue($color);
        $cacheKey = "soft_$color";

        return self::$cache[$cacheKey] ??= match ($color) {
            'primary' => 'bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300 dark:hover:bg-primary-900/50',
            'blue' => 'bg-blue-50 text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50',
            'emerald' => 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50',
            'green' => 'bg-green-50 text-green-700 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50',
            'red' => 'bg-red-50 text-red-700 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300 dark:hover:bg-red-900/50',
            'amber' => 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300 dark:hover:bg-amber-900/50',
            'yellow' => 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-300 dark:hover:bg-yellow-900/50',
            'cyan' => 'bg-cyan-50 text-cyan-700 hover:bg-cyan-100 dark:bg-cyan-900/30 dark:text-cyan-300 dark:hover:bg-cyan-900/50',
            'orange' => 'bg-orange-50 text-orange-700 hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-300 dark:hover:bg-orange-900/50',
            'lime' => 'bg-lime-50 text-lime-700 hover:bg-lime-100 dark:bg-lime-900/30 dark:text-lime-300 dark:hover:bg-lime-900/50',
            'teal' => 'bg-teal-50 text-teal-700 hover:bg-teal-100 dark:bg-teal-900/30 dark:text-teal-300 dark:hover:bg-teal-900/50',
            'sky' => 'bg-sky-50 text-sky-700 hover:bg-sky-100 dark:bg-sky-900/30 dark:text-sky-300 dark:hover:bg-sky-900/50',
            'indigo' => 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50',
            'violet' => 'bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-300 dark:hover:bg-violet-900/50',
            'purple' => 'bg-purple-50 text-purple-700 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-300 dark:hover:bg-purple-900/50',
            'fuchsia' => 'bg-fuchsia-50 text-fuchsia-700 hover:bg-fuchsia-100 dark:bg-fuchsia-900/30 dark:text-fuchsia-300 dark:hover:bg-fuchsia-900/50',
            'pink' => 'bg-pink-50 text-pink-700 hover:bg-pink-100 dark:bg-pink-900/30 dark:text-pink-300 dark:hover:bg-pink-900/50',
            'rose' => 'bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-900/30 dark:text-rose-300 dark:hover:bg-rose-900/50',
            'slate' => 'bg-slate-50 text-slate-700 hover:bg-slate-100 dark:bg-slate-900/30 dark:text-slate-300 dark:hover:bg-slate-900/50',
            'zinc' => 'bg-zinc-50 text-zinc-700 hover:bg-zinc-100 dark:bg-zinc-900/30 dark:text-zinc-300 dark:hover:bg-zinc-900/50',
            'neutral' => 'bg-neutral-50 text-neutral-700 hover:bg-neutral-100 dark:bg-neutral-900/30 dark:text-neutral-300 dark:hover:bg-neutral-900/50',
            'stone' => 'bg-stone-50 text-stone-700 hover:bg-stone-100 dark:bg-stone-900/30 dark:text-stone-300 dark:hover:bg-stone-900/50',
            'black' => 'bg-gray-900 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-200',
            'white' => 'bg-white text-gray-900 hover:bg-gray-100 dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800',
            default => 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
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
            'red' => 'text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20',
            'amber' => 'text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'yellow' => 'text-yellow-700 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-900/20',
            'emerald' => 'text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20',
            'green' => 'text-green-700 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20',
            'primary' => 'text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20',
            'blue' => 'text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20',
            'cyan' => 'text-cyan-700 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/20',
            'orange' => 'text-orange-700 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/20',
            'lime' => 'text-lime-700 dark:text-lime-400 hover:bg-lime-50 dark:hover:bg-lime-900/20',
            'teal' => 'text-teal-700 dark:text-teal-400 hover:bg-teal-50 dark:hover:bg-teal-900/20',
            'sky' => 'text-sky-700 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20',
            'indigo' => 'text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
            'violet' => 'text-violet-600 dark:text-violet-400 hover:bg-violet-50 dark:hover:bg-violet-900/20',
            'purple' => 'text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/20',
            'fuchsia' => 'text-fuchsia-700 dark:text-fuchsia-400 hover:bg-fuchsia-50 dark:hover:bg-fuchsia-900/20',
            'pink' => 'text-pink-700 dark:text-pink-400 hover:bg-pink-50 dark:hover:bg-pink-900/20',
            'rose' => 'text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20',
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
            'red' => 'text-red-700 hover:bg-red-50 focus:ring-red-500 dark:text-red-400 dark:hover:bg-red-900/20',
            'emerald' => 'text-emerald-700 hover:bg-emerald-50 focus:ring-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'green' => 'text-green-700 hover:bg-green-50 focus:ring-green-500 dark:text-green-400 dark:hover:bg-green-900/20',
            'amber' => 'text-amber-700 hover:bg-amber-50 focus:ring-amber-500 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'yellow' => 'text-yellow-700 hover:bg-yellow-50 focus:ring-yellow-500 dark:text-yellow-400 dark:hover:bg-yellow-900/20',
            'cyan' => 'text-cyan-700 hover:bg-cyan-50 focus:ring-cyan-500 dark:text-cyan-400 dark:hover:bg-cyan-900/20',
            'orange' => 'text-orange-700 hover:bg-orange-50 focus:ring-orange-500 dark:text-orange-400 dark:hover:bg-orange-900/20',
            'lime' => 'text-lime-700 hover:bg-lime-50 focus:ring-lime-500 dark:text-lime-400 dark:hover:bg-lime-900/20',
            'teal' => 'text-teal-700 hover:bg-teal-50 focus:ring-teal-500 dark:text-teal-400 dark:hover:bg-teal-900/20',
            'sky' => 'text-sky-700 hover:bg-sky-50 focus:ring-sky-500 dark:text-sky-400 dark:hover:bg-sky-900/20',
            'indigo' => 'text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:text-indigo-400 dark:hover:bg-indigo-900/20',
            'violet' => 'text-violet-600 hover:bg-violet-50 focus:ring-violet-500 dark:text-violet-400 dark:hover:bg-violet-900/20',
            'purple' => 'text-purple-600 hover:bg-purple-50 focus:ring-purple-500 dark:text-purple-400 dark:hover:bg-purple-900/20',
            'fuchsia' => 'text-fuchsia-700 hover:bg-fuchsia-50 focus:ring-fuchsia-500 dark:text-fuchsia-400 dark:hover:bg-fuchsia-900/20',
            'pink' => 'text-pink-700 hover:bg-pink-50 focus:ring-pink-500 dark:text-pink-400 dark:hover:bg-pink-900/20',
            'rose' => 'text-rose-700 hover:bg-rose-50 focus:ring-rose-500 dark:text-rose-400 dark:hover:bg-rose-900/20',
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
            'red' => 'text-red-700 dark:text-red-400 hover:bg-red-50 focus:ring-red-500 dark:hover:bg-red-900/20',

            'primary' => "$rest hover:text-primary-600 hover:bg-primary-50 focus:ring-primary-500 dark:hover:text-primary-400 dark:hover:bg-primary-900/20",
            'blue' => "$rest hover:text-blue-600 hover:bg-blue-50 focus:ring-blue-500 dark:hover:text-blue-400 dark:hover:bg-blue-900/20",
            'emerald' => "$rest hover:text-emerald-700 hover:bg-emerald-50 focus:ring-emerald-500 dark:hover:text-emerald-400 dark:hover:bg-emerald-900/20",
            'green' => "$rest hover:text-green-700 hover:bg-green-50 focus:ring-green-500 dark:hover:text-green-400 dark:hover:bg-green-900/20",
            'amber' => "$rest hover:text-amber-700 hover:bg-amber-50 focus:ring-amber-500 dark:hover:text-amber-400 dark:hover:bg-amber-900/20",
            'yellow' => "$rest hover:text-yellow-700 hover:bg-yellow-50 focus:ring-yellow-500 dark:hover:text-yellow-400 dark:hover:bg-yellow-900/20",
            'cyan' => "$rest hover:text-cyan-700 hover:bg-cyan-50 focus:ring-cyan-500 dark:hover:text-cyan-400 dark:hover:bg-cyan-900/20",
            'orange' => "$rest hover:text-orange-700 hover:bg-orange-50 focus:ring-orange-500 dark:hover:text-orange-400 dark:hover:bg-orange-900/20",
            'lime' => "$rest hover:text-lime-700 hover:bg-lime-50 focus:ring-lime-500 dark:hover:text-lime-400 dark:hover:bg-lime-900/20",
            'teal' => "$rest hover:text-teal-700 hover:bg-teal-50 focus:ring-teal-500 dark:hover:text-teal-400 dark:hover:bg-teal-900/20",
            'sky' => "$rest hover:text-sky-700 hover:bg-sky-50 focus:ring-sky-500 dark:hover:text-sky-400 dark:hover:bg-sky-900/20",
            'indigo' => "$rest hover:text-indigo-600 hover:bg-indigo-50 focus:ring-indigo-500 dark:hover:text-indigo-400 dark:hover:bg-indigo-900/20",
            'violet' => "$rest hover:text-violet-600 hover:bg-violet-50 focus:ring-violet-500 dark:hover:text-violet-400 dark:hover:bg-violet-900/20",
            'purple' => "$rest hover:text-purple-600 hover:bg-purple-50 focus:ring-purple-500 dark:hover:text-purple-400 dark:hover:bg-purple-900/20",
            'fuchsia' => "$rest hover:text-fuchsia-700 hover:bg-fuchsia-50 focus:ring-fuchsia-500 dark:hover:text-fuchsia-400 dark:hover:bg-fuchsia-900/20",
            'pink' => "$rest hover:text-pink-700 hover:bg-pink-50 focus:ring-pink-500 dark:hover:text-pink-400 dark:hover:bg-pink-900/20",
            'rose' => "$rest hover:text-rose-700 hover:bg-rose-50 focus:ring-rose-500 dark:hover:text-rose-400 dark:hover:bg-rose-900/20",
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
