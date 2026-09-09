<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Colour for text that is only text — a value, a label, a link.
 *
 * One of the five surface palettes {@see HasColor}
 * split into. The trait stayed the door every consumer already knows and
 * delegates here, so nothing moved for a caller; what moved is where a rule
 * about *this surface* is found and changed.
 *
 * Kept apart from the fill palette on purpose: {@see ChartPalette::fillText()}
 * looks identical for most hues and is not the same rule — a chart's accent
 * falls back to `primary` where a label falls back to grey, because an
 * unrecognised series still has to be visible.
 */
final class TextPalette
{
    /**
     * Get plain text color classes (foreground only, no background).
     *
     * Canonical source for text-tinted cells, icons and inline states. Same
     * palette vocabulary as {@see getBadgeColorClasses()}. Replaces the various
     * ad-hoc `text-green-500` / `text-primary-600` maps that lived in table
     * columns, so a single hue is used everywhere (e.g. success is always
     * emerald, never green).
     */
    public static function text(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'text-primary-600 dark:text-primary-400',
            'blue' => 'text-blue-600 dark:text-blue-400',
            'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'green' => 'text-green-600 dark:text-green-400',
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
            'gray', 'secondary' => 'text-gray-600 dark:text-gray-400',
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
    public static function link(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline',
            'blue' => 'text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline',
            'emerald' => 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 hover:underline',
            'green' => 'text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300 hover:underline',
            'red' => 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 hover:underline',
            'amber' => 'text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 hover:underline',
            'yellow' => 'text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300 hover:underline',
            'cyan' => 'text-cyan-600 hover:text-cyan-800 dark:text-cyan-400 dark:hover:text-cyan-300 hover:underline',
            'orange' => 'text-orange-600 hover:text-orange-800 dark:text-orange-400 dark:hover:text-orange-300 hover:underline',
            'lime' => 'text-lime-600 hover:text-lime-800 dark:text-lime-400 dark:hover:text-lime-300 hover:underline',
            'teal' => 'text-teal-600 hover:text-teal-800 dark:text-teal-400 dark:hover:text-teal-300 hover:underline',
            'sky' => 'text-sky-600 hover:text-sky-800 dark:text-sky-400 dark:hover:text-sky-300 hover:underline',
            'indigo' => 'text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 hover:underline',
            'violet' => 'text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300 hover:underline',
            'purple' => 'text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-300 hover:underline',
            'fuchsia' => 'text-fuchsia-600 hover:text-fuchsia-800 dark:text-fuchsia-400 dark:hover:text-fuchsia-300 hover:underline',
            'pink' => 'text-pink-600 hover:text-pink-800 dark:text-pink-400 dark:hover:text-pink-300 hover:underline',
            'rose' => 'text-rose-600 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300 hover:underline',
            'slate' => 'text-slate-600 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-300 hover:underline',
            'zinc' => 'text-zinc-600 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-300 hover:underline',
            'neutral' => 'text-neutral-600 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-300 hover:underline',
            'stone' => 'text-stone-600 hover:text-stone-800 dark:text-stone-400 dark:hover:text-stone-300 hover:underline',
            'black' => 'text-gray-900 hover:text-black dark:text-white dark:hover:text-gray-200 hover:underline',
            'white' => 'text-white hover:text-gray-200 dark:text-gray-900 dark:hover:text-black hover:underline',
            'gray', 'secondary' => 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 hover:underline',
            default => 'text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-300 hover:underline',
        };
    }
}
