<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

/**
 * Colour for a soft block that carries a state without shouting it — a badge, a
 * choice card, a tinted table row, a diff cell.
 *
 * The family that sits at the `-50`/`-100` end of the scale, where the fill is
 * background and the text still has to be read against it.
 *
 * {@see self::rowTint()} does not repeat {@see self::softTint()}: it composes
 * from it and adds only the hover half. A diff cell and a clickable row share a
 * resting wash and differ in what happens under the pointer, which is one rule
 * and one exception rather than two lists to keep in step.
 */
final class TintPalette
{
    /**
     * Get badge color classes (soft background + text).
     *
     * Canonical palette shared by Badges, BadgeColumn, PollColumn and any soft
     * "pill" surface. Semantic names resolve to a fixed Tailwind hue (success →
     * emerald, info → cyan, blue → primary); every raw Tailwind hue family is
     * also accepted for finer control. Literal class strings are kept verbatim so
     * Tailwind's JIT scanner can see them.
     */
    public static function badge(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'green' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'yellow' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
            'orange' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
            'lime' => 'bg-lime-100 text-lime-700 dark:bg-lime-900/30 dark:text-lime-400',
            'teal' => 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
            'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
            'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
            'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            'fuchsia' => 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-400',
            'pink' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400',
            'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            'slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-400',
            'zinc' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-900/30 dark:text-zinc-400',
            'neutral' => 'bg-neutral-100 text-neutral-700 dark:bg-neutral-900/30 dark:text-neutral-400',
            'stone' => 'bg-stone-100 text-stone-700 dark:bg-stone-900/30 dark:text-stone-400',
            'black' => 'bg-gray-900 text-white dark:bg-white dark:text-gray-900',
            'white' => 'bg-white text-gray-900 border border-gray-200 dark:bg-gray-900 dark:text-white dark:border-gray-700',
            'gray', 'secondary' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /**
     * Get the peer-checked class bundles for a selectable "choice" surface (radio
     * cards / segmented / buttons and the native radio accent).
     *
     * A single canonical owner for the option-choice palette. Returns literal class
     * strings keyed by the sub-surface that consumes them, so the JIT scanner can see
     * every hue and downstream views never interpolate a color into a class name:
     *
     * - `input`     — native radio accent + focus ring (default variant).
     * - `solid`     — filled selected button (`buttons` variant), including its
     *                 own hover step. That pair is not decoration: `peer-checked:`
     *                 compiles to `.x:is(:where(.peer):checked~*)`, which ties with
     *                 a plain `hover:` on specificity, so the button's grey hover
     *                 would otherwise repaint a *selected* button grey and leave
     *                 white text on it. `peer-checked:hover:` outranks both.
     * - `text`      — selected label tint (`segmented` variant).
     * - `card`      — selected card border/ring + card icon tint (`cards` variant).
     * - `indicator` — selected card radio-dot border/fill (`cards` variant).
     *
     * @return array{input:string, solid:string, text:string, card:string, indicator:string}
     */
    public static function choice(string $color): array
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'emerald' => [
                'input' => 'text-emerald-600 focus:ring-emerald-500',
                'solid' => 'peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:hover:border-emerald-700 peer-checked:hover:bg-emerald-700',
                'text' => 'peer-checked:text-emerald-600 dark:peer-checked:text-emerald-400',
                'card' => 'peer-checked:border-emerald-500 peer-checked:ring-emerald-500/40 peer-checked:[&_.wf-card-icon]:text-emerald-600 dark:peer-checked:[&_.wf-card-icon]:text-emerald-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-emerald-600 peer-checked:[&_.wf-card-indicator]:bg-emerald-600',
            ],
            'green' => [
                'input' => 'text-green-600 focus:ring-green-500',
                'solid' => 'peer-checked:border-green-600 peer-checked:bg-green-600 peer-checked:text-white peer-checked:hover:border-green-700 peer-checked:hover:bg-green-700',
                'text' => 'peer-checked:text-green-600 dark:peer-checked:text-green-400',
                'card' => 'peer-checked:border-green-500 peer-checked:ring-green-500/40 peer-checked:[&_.wf-card-icon]:text-green-600 dark:peer-checked:[&_.wf-card-icon]:text-green-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-green-600 peer-checked:[&_.wf-card-indicator]:bg-green-600',
            ],
            'blue' => [
                'input' => 'text-blue-600 focus:ring-blue-500',
                'solid' => 'peer-checked:border-blue-600 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:hover:border-blue-700 peer-checked:hover:bg-blue-700',
                'text' => 'peer-checked:text-blue-600 dark:peer-checked:text-blue-400',
                'card' => 'peer-checked:border-blue-500 peer-checked:ring-blue-500/40 peer-checked:[&_.wf-card-icon]:text-blue-600 dark:peer-checked:[&_.wf-card-icon]:text-blue-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-blue-600 peer-checked:[&_.wf-card-indicator]:bg-blue-600',
            ],
            'red' => [
                'input' => 'text-red-600 focus:ring-red-500',
                'solid' => 'peer-checked:border-red-600 peer-checked:bg-red-600 peer-checked:text-white peer-checked:hover:border-red-700 peer-checked:hover:bg-red-700',
                'text' => 'peer-checked:text-red-600 dark:peer-checked:text-red-400',
                'card' => 'peer-checked:border-red-500 peer-checked:ring-red-500/40 peer-checked:[&_.wf-card-icon]:text-red-600 dark:peer-checked:[&_.wf-card-icon]:text-red-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-red-600 peer-checked:[&_.wf-card-indicator]:bg-red-600',
            ],
            'amber' => [
                'input' => 'text-amber-500 focus:ring-amber-500',
                'solid' => 'peer-checked:border-amber-500 peer-checked:bg-amber-500 peer-checked:text-white peer-checked:hover:border-amber-600 peer-checked:hover:bg-amber-600',
                'text' => 'peer-checked:text-amber-600 dark:peer-checked:text-amber-400',
                'card' => 'peer-checked:border-amber-500 peer-checked:ring-amber-500/40 peer-checked:[&_.wf-card-icon]:text-amber-600 dark:peer-checked:[&_.wf-card-icon]:text-amber-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-amber-500 peer-checked:[&_.wf-card-indicator]:bg-amber-500',
            ],
            'yellow' => [
                'input' => 'text-yellow-500 focus:ring-yellow-500',
                'solid' => 'peer-checked:border-yellow-500 peer-checked:bg-yellow-500 peer-checked:text-white peer-checked:hover:border-yellow-600 peer-checked:hover:bg-yellow-600',
                'text' => 'peer-checked:text-yellow-600 dark:peer-checked:text-yellow-400',
                'card' => 'peer-checked:border-yellow-500 peer-checked:ring-yellow-500/40 peer-checked:[&_.wf-card-icon]:text-yellow-600 dark:peer-checked:[&_.wf-card-icon]:text-yellow-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-yellow-500 peer-checked:[&_.wf-card-indicator]:bg-yellow-500',
            ],
            'cyan' => [
                'input' => 'text-cyan-600 focus:ring-cyan-500',
                'solid' => 'peer-checked:border-cyan-600 peer-checked:bg-cyan-600 peer-checked:text-white peer-checked:hover:border-cyan-700 peer-checked:hover:bg-cyan-700',
                'text' => 'peer-checked:text-cyan-600 dark:peer-checked:text-cyan-400',
                'card' => 'peer-checked:border-cyan-500 peer-checked:ring-cyan-500/40 peer-checked:[&_.wf-card-icon]:text-cyan-600 dark:peer-checked:[&_.wf-card-icon]:text-cyan-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-cyan-600 peer-checked:[&_.wf-card-indicator]:bg-cyan-600',
            ],
            'orange' => [
                'input' => 'text-orange-500 focus:ring-orange-500',
                'solid' => 'peer-checked:border-orange-500 peer-checked:bg-orange-500 peer-checked:text-white peer-checked:hover:border-orange-600 peer-checked:hover:bg-orange-600',
                'text' => 'peer-checked:text-orange-600 dark:peer-checked:text-orange-400',
                'card' => 'peer-checked:border-orange-500 peer-checked:ring-orange-500/40 peer-checked:[&_.wf-card-icon]:text-orange-600 dark:peer-checked:[&_.wf-card-icon]:text-orange-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-orange-500 peer-checked:[&_.wf-card-indicator]:bg-orange-500',
            ],
            'lime' => [
                'input' => 'text-lime-600 focus:ring-lime-500',
                'solid' => 'peer-checked:border-lime-600 peer-checked:bg-lime-600 peer-checked:text-white peer-checked:hover:border-lime-700 peer-checked:hover:bg-lime-700',
                'text' => 'peer-checked:text-lime-600 dark:peer-checked:text-lime-400',
                'card' => 'peer-checked:border-lime-500 peer-checked:ring-lime-500/40 peer-checked:[&_.wf-card-icon]:text-lime-600 dark:peer-checked:[&_.wf-card-icon]:text-lime-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-lime-600 peer-checked:[&_.wf-card-indicator]:bg-lime-600',
            ],
            'teal' => [
                'input' => 'text-teal-600 focus:ring-teal-500',
                'solid' => 'peer-checked:border-teal-600 peer-checked:bg-teal-600 peer-checked:text-white peer-checked:hover:border-teal-700 peer-checked:hover:bg-teal-700',
                'text' => 'peer-checked:text-teal-600 dark:peer-checked:text-teal-400',
                'card' => 'peer-checked:border-teal-500 peer-checked:ring-teal-500/40 peer-checked:[&_.wf-card-icon]:text-teal-600 dark:peer-checked:[&_.wf-card-icon]:text-teal-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-teal-600 peer-checked:[&_.wf-card-indicator]:bg-teal-600',
            ],
            'sky' => [
                'input' => 'text-sky-600 focus:ring-sky-500',
                'solid' => 'peer-checked:border-sky-600 peer-checked:bg-sky-600 peer-checked:text-white peer-checked:hover:border-sky-700 peer-checked:hover:bg-sky-700',
                'text' => 'peer-checked:text-sky-600 dark:peer-checked:text-sky-400',
                'card' => 'peer-checked:border-sky-500 peer-checked:ring-sky-500/40 peer-checked:[&_.wf-card-icon]:text-sky-600 dark:peer-checked:[&_.wf-card-icon]:text-sky-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-sky-600 peer-checked:[&_.wf-card-indicator]:bg-sky-600',
            ],
            'indigo' => [
                'input' => 'text-indigo-600 focus:ring-indigo-500',
                'solid' => 'peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:hover:border-indigo-700 peer-checked:hover:bg-indigo-700',
                'text' => 'peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400',
                'card' => 'peer-checked:border-indigo-500 peer-checked:ring-indigo-500/40 peer-checked:[&_.wf-card-icon]:text-indigo-600 dark:peer-checked:[&_.wf-card-icon]:text-indigo-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-indigo-600 peer-checked:[&_.wf-card-indicator]:bg-indigo-600',
            ],
            'violet' => [
                'input' => 'text-violet-600 focus:ring-violet-500',
                'solid' => 'peer-checked:border-violet-600 peer-checked:bg-violet-600 peer-checked:text-white peer-checked:hover:border-violet-700 peer-checked:hover:bg-violet-700',
                'text' => 'peer-checked:text-violet-600 dark:peer-checked:text-violet-400',
                'card' => 'peer-checked:border-violet-500 peer-checked:ring-violet-500/40 peer-checked:[&_.wf-card-icon]:text-violet-600 dark:peer-checked:[&_.wf-card-icon]:text-violet-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-violet-600 peer-checked:[&_.wf-card-indicator]:bg-violet-600',
            ],
            'purple' => [
                'input' => 'text-purple-600 focus:ring-purple-500',
                'solid' => 'peer-checked:border-purple-600 peer-checked:bg-purple-600 peer-checked:text-white peer-checked:hover:border-purple-700 peer-checked:hover:bg-purple-700',
                'text' => 'peer-checked:text-purple-600 dark:peer-checked:text-purple-400',
                'card' => 'peer-checked:border-purple-500 peer-checked:ring-purple-500/40 peer-checked:[&_.wf-card-icon]:text-purple-600 dark:peer-checked:[&_.wf-card-icon]:text-purple-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-purple-600 peer-checked:[&_.wf-card-indicator]:bg-purple-600',
            ],
            'fuchsia' => [
                'input' => 'text-fuchsia-600 focus:ring-fuchsia-500',
                'solid' => 'peer-checked:border-fuchsia-600 peer-checked:bg-fuchsia-600 peer-checked:text-white peer-checked:hover:border-fuchsia-700 peer-checked:hover:bg-fuchsia-700',
                'text' => 'peer-checked:text-fuchsia-600 dark:peer-checked:text-fuchsia-400',
                'card' => 'peer-checked:border-fuchsia-500 peer-checked:ring-fuchsia-500/40 peer-checked:[&_.wf-card-icon]:text-fuchsia-600 dark:peer-checked:[&_.wf-card-icon]:text-fuchsia-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-fuchsia-600 peer-checked:[&_.wf-card-indicator]:bg-fuchsia-600',
            ],
            'pink' => [
                'input' => 'text-pink-600 focus:ring-pink-500',
                'solid' => 'peer-checked:border-pink-600 peer-checked:bg-pink-600 peer-checked:text-white peer-checked:hover:border-pink-700 peer-checked:hover:bg-pink-700',
                'text' => 'peer-checked:text-pink-600 dark:peer-checked:text-pink-400',
                'card' => 'peer-checked:border-pink-500 peer-checked:ring-pink-500/40 peer-checked:[&_.wf-card-icon]:text-pink-600 dark:peer-checked:[&_.wf-card-icon]:text-pink-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-pink-600 peer-checked:[&_.wf-card-indicator]:bg-pink-600',
            ],
            'rose' => [
                'input' => 'text-rose-600 focus:ring-rose-500',
                'solid' => 'peer-checked:border-rose-600 peer-checked:bg-rose-600 peer-checked:text-white peer-checked:hover:border-rose-700 peer-checked:hover:bg-rose-700',
                'text' => 'peer-checked:text-rose-600 dark:peer-checked:text-rose-400',
                'card' => 'peer-checked:border-rose-500 peer-checked:ring-rose-500/40 peer-checked:[&_.wf-card-icon]:text-rose-600 dark:peer-checked:[&_.wf-card-icon]:text-rose-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-rose-600 peer-checked:[&_.wf-card-indicator]:bg-rose-600',
            ],
            'slate' => [
                'input' => 'text-slate-600 focus:ring-slate-500',
                'solid' => 'peer-checked:border-slate-600 peer-checked:bg-slate-600 peer-checked:text-white peer-checked:hover:border-slate-700 peer-checked:hover:bg-slate-700',
                'text' => 'peer-checked:text-slate-600 dark:peer-checked:text-slate-300',
                'card' => 'peer-checked:border-slate-500 peer-checked:ring-slate-500/40 peer-checked:[&_.wf-card-icon]:text-slate-600 dark:peer-checked:[&_.wf-card-icon]:text-slate-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-slate-600 peer-checked:[&_.wf-card-indicator]:bg-slate-600',
            ],
            'zinc' => [
                'input' => 'text-zinc-600 focus:ring-zinc-500',
                'solid' => 'peer-checked:border-zinc-600 peer-checked:bg-zinc-600 peer-checked:text-white peer-checked:hover:border-zinc-700 peer-checked:hover:bg-zinc-700',
                'text' => 'peer-checked:text-zinc-600 dark:peer-checked:text-zinc-300',
                'card' => 'peer-checked:border-zinc-500 peer-checked:ring-zinc-500/40 peer-checked:[&_.wf-card-icon]:text-zinc-600 dark:peer-checked:[&_.wf-card-icon]:text-zinc-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-zinc-600 peer-checked:[&_.wf-card-indicator]:bg-zinc-600',
            ],
            'neutral' => [
                'input' => 'text-neutral-600 focus:ring-neutral-500',
                'solid' => 'peer-checked:border-neutral-600 peer-checked:bg-neutral-600 peer-checked:text-white peer-checked:hover:border-neutral-700 peer-checked:hover:bg-neutral-700',
                'text' => 'peer-checked:text-neutral-600 dark:peer-checked:text-neutral-300',
                'card' => 'peer-checked:border-neutral-500 peer-checked:ring-neutral-500/40 peer-checked:[&_.wf-card-icon]:text-neutral-600 dark:peer-checked:[&_.wf-card-icon]:text-neutral-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-neutral-600 peer-checked:[&_.wf-card-indicator]:bg-neutral-600',
            ],
            'stone' => [
                'input' => 'text-stone-600 focus:ring-stone-500',
                'solid' => 'peer-checked:border-stone-600 peer-checked:bg-stone-600 peer-checked:text-white peer-checked:hover:border-stone-700 peer-checked:hover:bg-stone-700',
                'text' => 'peer-checked:text-stone-600 dark:peer-checked:text-stone-300',
                'card' => 'peer-checked:border-stone-500 peer-checked:ring-stone-500/40 peer-checked:[&_.wf-card-icon]:text-stone-600 dark:peer-checked:[&_.wf-card-icon]:text-stone-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-stone-600 peer-checked:[&_.wf-card-indicator]:bg-stone-600',
            ],
            'black' => [
                'input' => 'text-gray-900 focus:ring-gray-500',
                'solid' => 'peer-checked:border-gray-900 peer-checked:bg-gray-900 peer-checked:text-white peer-checked:hover:border-gray-800 peer-checked:hover:bg-gray-800',
                'text' => 'peer-checked:text-gray-900 dark:peer-checked:text-white',
                'card' => 'peer-checked:border-gray-900 peer-checked:ring-gray-900/40 peer-checked:[&_.wf-card-icon]:text-gray-900 dark:peer-checked:[&_.wf-card-icon]:text-white',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-gray-900 peer-checked:[&_.wf-card-indicator]:bg-gray-900',
            ],
            'white' => [
                'input' => 'text-gray-400 focus:ring-gray-300',
                'solid' => 'peer-checked:border-gray-300 peer-checked:bg-white peer-checked:text-gray-900 peer-checked:hover:border-gray-400 peer-checked:hover:bg-gray-100',
                'text' => 'peer-checked:text-gray-900 dark:peer-checked:text-white',
                'card' => 'peer-checked:border-gray-300 peer-checked:ring-gray-300/40 peer-checked:[&_.wf-card-icon]:text-gray-900 dark:peer-checked:[&_.wf-card-icon]:text-white',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-gray-400 peer-checked:[&_.wf-card-indicator]:bg-white',
            ],
            'gray', 'secondary' => [
                'input' => 'text-gray-600 focus:ring-gray-500',
                'solid' => 'peer-checked:border-gray-600 peer-checked:bg-gray-600 peer-checked:text-white peer-checked:hover:border-gray-700 peer-checked:hover:bg-gray-700',
                'text' => 'peer-checked:text-gray-600 dark:peer-checked:text-gray-300',
                'card' => 'peer-checked:border-gray-500 peer-checked:ring-gray-500/40 peer-checked:[&_.wf-card-icon]:text-gray-600 dark:peer-checked:[&_.wf-card-icon]:text-gray-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-gray-600 peer-checked:[&_.wf-card-indicator]:bg-gray-600',
            ],
            default => [
                'input' => 'text-primary-600 focus:ring-primary-500',
                'solid' => 'peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white peer-checked:hover:border-primary-700 peer-checked:hover:bg-primary-700',
                'text' => 'peer-checked:text-primary-600 dark:peer-checked:text-primary-400',
                'card' => 'peer-checked:border-primary-500 peer-checked:ring-primary-500/40 peer-checked:[&_.wf-card-icon]:text-primary-600 dark:peer-checked:[&_.wf-card-icon]:text-primary-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-primary-600 peer-checked:[&_.wf-card-indicator]:bg-primary-600',
            ],
        };
    }

    public static function softTint(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'bg-primary-50 dark:bg-primary-900/20',
            'blue' => 'bg-blue-50 dark:bg-blue-900/20',
            'emerald' => 'bg-emerald-50 dark:bg-emerald-900/20',
            'green' => 'bg-green-50 dark:bg-green-900/20',
            'red' => 'bg-red-50 dark:bg-red-900/20',
            'amber' => 'bg-amber-50 dark:bg-amber-900/20',
            'yellow' => 'bg-yellow-50 dark:bg-yellow-900/20',
            'cyan' => 'bg-cyan-50 dark:bg-cyan-900/20',
            'orange' => 'bg-orange-50 dark:bg-orange-900/20',
            'lime' => 'bg-lime-50 dark:bg-lime-900/20',
            'teal' => 'bg-teal-50 dark:bg-teal-900/20',
            'sky' => 'bg-sky-50 dark:bg-sky-900/20',
            'indigo' => 'bg-indigo-50 dark:bg-indigo-900/20',
            'violet' => 'bg-violet-50 dark:bg-violet-900/20',
            'purple' => 'bg-purple-50 dark:bg-purple-900/20',
            'fuchsia' => 'bg-fuchsia-50 dark:bg-fuchsia-900/20',
            'pink' => 'bg-pink-50 dark:bg-pink-900/20',
            'rose' => 'bg-rose-50 dark:bg-rose-900/20',
            'slate' => 'bg-slate-50 dark:bg-slate-900/20',
            'zinc' => 'bg-zinc-50 dark:bg-zinc-900/20',
            'neutral' => 'bg-neutral-50 dark:bg-neutral-900/20',
            'stone' => 'bg-stone-50 dark:bg-stone-900/20',
            'gray' => 'bg-gray-50 dark:bg-gray-900/20',
            'black' => 'bg-gray-100 dark:bg-gray-800',
            'white' => 'bg-white dark:bg-gray-900',
            default => 'bg-gray-50 dark:bg-gray-900/20',
        };
    }

    public static function rowTint(string $color): string
    {
        // The resting fill is not repeated here: it comes from
        // {@see self::softTint()}, so a diff cell and a clickable row
        // cannot drift apart on the one thing they share. Only the hover half,
        // which is this surface's own, lives below.
        $color = SemanticPalette::hue($color);

        $hover = match ($color) {
            'primary' => 'hover:bg-primary-100 dark:hover:bg-primary-900/30',
            'blue' => 'hover:bg-blue-100 dark:hover:bg-blue-900/30',
            'emerald' => 'hover:bg-emerald-100 dark:hover:bg-emerald-900/30',
            'green' => 'hover:bg-green-100 dark:hover:bg-green-900/30',
            'red' => 'hover:bg-red-100 dark:hover:bg-red-900/30',
            'amber' => 'hover:bg-amber-100 dark:hover:bg-amber-900/30',
            'yellow' => 'hover:bg-yellow-100 dark:hover:bg-yellow-900/30',
            'cyan' => 'hover:bg-cyan-100 dark:hover:bg-cyan-900/30',
            'orange' => 'hover:bg-orange-100 dark:hover:bg-orange-900/30',
            'lime' => 'hover:bg-lime-100 dark:hover:bg-lime-900/30',
            'teal' => 'hover:bg-teal-100 dark:hover:bg-teal-900/30',
            'sky' => 'hover:bg-sky-100 dark:hover:bg-sky-900/30',
            'indigo' => 'hover:bg-indigo-100 dark:hover:bg-indigo-900/30',
            'violet' => 'hover:bg-violet-100 dark:hover:bg-violet-900/30',
            'purple' => 'hover:bg-purple-100 dark:hover:bg-purple-900/30',
            'fuchsia' => 'hover:bg-fuchsia-100 dark:hover:bg-fuchsia-900/30',
            'pink' => 'hover:bg-pink-100 dark:hover:bg-pink-900/30',
            'rose' => 'hover:bg-rose-100 dark:hover:bg-rose-900/30',
            'slate' => 'hover:bg-slate-100 dark:hover:bg-slate-900/30',
            'zinc' => 'hover:bg-zinc-100 dark:hover:bg-zinc-900/30',
            'neutral' => 'hover:bg-neutral-100 dark:hover:bg-neutral-900/30',
            'stone' => 'hover:bg-stone-100 dark:hover:bg-stone-900/30',
            default => 'hover:bg-gray-100 dark:hover:bg-gray-900/30',
        };

        return self::softTint($color).' '.$hover;
    }

    /**
     * Hover-only tint for an interactive (record-action) row: no resting
     * background, just a same-hue hover so a clickable row hints at itself. The
     * companion of {@see getRowTintClasses()} for rows that are clickable but not
     * statically colored. `gray`/neutral returns the table's default neutral
     * hover so `recordActionHover('gray')` reads the same as no override. Same hue
     * vocabulary and literal, safelist-friendly classes as the rest of the
     * palette (they mirror the outline-button hovers).
     */
    public static function rowHover(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'primary' => 'hover:bg-primary-50 dark:hover:bg-primary-900/20',
            'blue' => 'hover:bg-blue-50 dark:hover:bg-blue-900/20',
            'emerald' => 'hover:bg-emerald-50 dark:hover:bg-emerald-900/20',
            'green' => 'hover:bg-green-50 dark:hover:bg-green-900/20',
            'red' => 'hover:bg-red-50 dark:hover:bg-red-900/20',
            'amber' => 'hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'yellow' => 'hover:bg-yellow-50 dark:hover:bg-yellow-900/20',
            'cyan' => 'hover:bg-cyan-50 dark:hover:bg-cyan-900/20',
            'orange' => 'hover:bg-orange-50 dark:hover:bg-orange-900/20',
            'lime' => 'hover:bg-lime-50 dark:hover:bg-lime-900/20',
            'teal' => 'hover:bg-teal-50 dark:hover:bg-teal-900/20',
            'sky' => 'hover:bg-sky-50 dark:hover:bg-sky-900/20',
            'indigo' => 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20',
            'violet' => 'hover:bg-violet-50 dark:hover:bg-violet-900/20',
            'purple' => 'hover:bg-purple-50 dark:hover:bg-purple-900/20',
            'fuchsia' => 'hover:bg-fuchsia-50 dark:hover:bg-fuchsia-900/20',
            'pink' => 'hover:bg-pink-50 dark:hover:bg-pink-900/20',
            'rose' => 'hover:bg-rose-50 dark:hover:bg-rose-900/20',
            'slate' => 'hover:bg-slate-50 dark:hover:bg-slate-900/20',
            'zinc' => 'hover:bg-zinc-50 dark:hover:bg-zinc-900/20',
            'neutral' => 'hover:bg-neutral-50 dark:hover:bg-neutral-900/20',
            'stone' => 'hover:bg-stone-50 dark:hover:bg-stone-900/20',
            'black' => 'hover:bg-gray-100 dark:hover:bg-gray-800/80',
            'white' => 'hover:bg-gray-50 dark:hover:bg-gray-800',
            default => 'hover:bg-gray-50 dark:hover:bg-gray-700/30',
        };
    }
}
