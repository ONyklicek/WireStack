<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Trait HasColor
 *
 * Centralized Tailwind CSS color class management.
 * Used across Actions, Badges, Notifications, and Form components.
 *
 * Every owner-facing surface accepts the **complete Tailwind color palette**:
 * the semantic aliases (`primary`/`blue`, `success`/`green`/`emerald`,
 * `danger`/`red`, `warning`/`yellow`/`amber`, `info`/`cyan`, `gray`/`secondary`)
 * plus every raw Tailwind hue family — `slate`, `zinc`, `neutral`, `stone`,
 * `orange`, `lime`, `teal`, `sky`, `indigo`, `violet`, `purple`, `fuchsia`,
 * `pink`, `rose`. So `->color('fuchsia')` renders the same on a solid button,
 * an outlined button, a link, a badge, a modal submit button and a choice card,
 * not just the ones that used to special-case a curated subset.
 *
 * Class strings are kept literal (never interpolated from the owner-supplied
 * color) so Tailwind's JIT scanner sees every hue and the match arms double as a
 * safe allow-list against arbitrary class injection.
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
            'green',
            'emerald' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600',
            'danger',
            'red' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
            'warning',
            'yellow',
            'amber' => 'bg-amber-500 text-white hover:bg-amber-600 focus:ring-amber-500 dark:bg-amber-400 dark:hover:bg-amber-500',
            'info',
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
            'green',
            'emerald' => 'border border-emerald-600 text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:border-emerald-400 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'danger',
            'red' => 'border border-red-600 text-red-600 hover:bg-red-50 focus:ring-red-500 dark:border-red-400 dark:text-red-400 dark:hover:bg-red-900/20',
            'warning',
            'yellow',
            'amber' => 'border border-amber-600 text-amber-600 hover:bg-amber-50 focus:ring-amber-500 dark:border-amber-400 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'info',
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
            'warning', 'yellow', 'amber' => 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20',
            'success',
            'green',
            'emerald' => 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20',
            'primary',
            'blue' => 'text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20',
            'info', 'cyan' => 'text-cyan-600 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-900/20',
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
            'green',
            'emerald' => 'text-emerald-600 hover:bg-emerald-50 focus:ring-emerald-500 dark:text-emerald-400 dark:hover:bg-emerald-900/20',
            'warning', 'yellow', 'amber' => 'text-amber-600 hover:bg-amber-50 focus:ring-amber-500 dark:text-amber-400 dark:hover:bg-amber-900/20',
            'info', 'cyan' => 'text-cyan-600 hover:bg-cyan-50 focus:ring-cyan-500 dark:text-cyan-400 dark:hover:bg-cyan-900/20',
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
            default => 'text-gray-500 hover:bg-gray-100 focus:ring-gray-500 dark:text-gray-400 dark:hover:bg-gray-700',
        };
    }

    /**
     * Get badge color classes (soft background + text).
     *
     * Canonical palette shared by Badges, BadgeColumn, PollColumn and any soft
     * "pill" surface. Semantic names resolve to a fixed Tailwind hue (success →
     * emerald, info → cyan, blue → primary); every raw Tailwind hue family is
     * also accepted for finer control. Literal class strings are kept verbatim so
     * Tailwind's JIT scanner can see them.
     */
    public static function getBadgeColorClasses(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
            'success', 'green', 'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'danger', 'red' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            'warning', 'yellow', 'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'info', 'cyan' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400',
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
     * - `solid`     — filled selected button (`buttons` variant).
     * - `text`      — selected label tint (`segmented` variant).
     * - `card`      — selected card border/ring + card icon tint (`cards` variant).
     * - `indicator` — selected card radio-dot border/fill (`cards` variant).
     *
     * @return array{input:string, solid:string, text:string, card:string, indicator:string}
     */
    public static function getChoiceColorClasses(string $color): array
    {
        return match ($color) {
            'success', 'green', 'emerald' => [
                'input' => 'text-emerald-600 focus:ring-emerald-500',
                'solid' => 'peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white',
                'text' => 'peer-checked:text-emerald-600 dark:peer-checked:text-emerald-400',
                'card' => 'peer-checked:border-emerald-500 peer-checked:ring-emerald-500/40 peer-checked:[&_.wf-card-icon]:text-emerald-600 dark:peer-checked:[&_.wf-card-icon]:text-emerald-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-emerald-600 peer-checked:[&_.wf-card-indicator]:bg-emerald-600',
            ],
            'danger', 'red' => [
                'input' => 'text-red-600 focus:ring-red-500',
                'solid' => 'peer-checked:border-red-600 peer-checked:bg-red-600 peer-checked:text-white',
                'text' => 'peer-checked:text-red-600 dark:peer-checked:text-red-400',
                'card' => 'peer-checked:border-red-500 peer-checked:ring-red-500/40 peer-checked:[&_.wf-card-icon]:text-red-600 dark:peer-checked:[&_.wf-card-icon]:text-red-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-red-600 peer-checked:[&_.wf-card-indicator]:bg-red-600',
            ],
            'warning', 'yellow', 'amber' => [
                'input' => 'text-amber-500 focus:ring-amber-500',
                'solid' => 'peer-checked:border-amber-500 peer-checked:bg-amber-500 peer-checked:text-white',
                'text' => 'peer-checked:text-amber-600 dark:peer-checked:text-amber-400',
                'card' => 'peer-checked:border-amber-500 peer-checked:ring-amber-500/40 peer-checked:[&_.wf-card-icon]:text-amber-600 dark:peer-checked:[&_.wf-card-icon]:text-amber-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-amber-500 peer-checked:[&_.wf-card-indicator]:bg-amber-500',
            ],
            'info', 'cyan' => [
                'input' => 'text-cyan-600 focus:ring-cyan-500',
                'solid' => 'peer-checked:border-cyan-600 peer-checked:bg-cyan-600 peer-checked:text-white',
                'text' => 'peer-checked:text-cyan-600 dark:peer-checked:text-cyan-400',
                'card' => 'peer-checked:border-cyan-500 peer-checked:ring-cyan-500/40 peer-checked:[&_.wf-card-icon]:text-cyan-600 dark:peer-checked:[&_.wf-card-icon]:text-cyan-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-cyan-600 peer-checked:[&_.wf-card-indicator]:bg-cyan-600',
            ],
            'orange' => [
                'input' => 'text-orange-500 focus:ring-orange-500',
                'solid' => 'peer-checked:border-orange-500 peer-checked:bg-orange-500 peer-checked:text-white',
                'text' => 'peer-checked:text-orange-600 dark:peer-checked:text-orange-400',
                'card' => 'peer-checked:border-orange-500 peer-checked:ring-orange-500/40 peer-checked:[&_.wf-card-icon]:text-orange-600 dark:peer-checked:[&_.wf-card-icon]:text-orange-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-orange-500 peer-checked:[&_.wf-card-indicator]:bg-orange-500',
            ],
            'lime' => [
                'input' => 'text-lime-600 focus:ring-lime-500',
                'solid' => 'peer-checked:border-lime-600 peer-checked:bg-lime-600 peer-checked:text-white',
                'text' => 'peer-checked:text-lime-600 dark:peer-checked:text-lime-400',
                'card' => 'peer-checked:border-lime-500 peer-checked:ring-lime-500/40 peer-checked:[&_.wf-card-icon]:text-lime-600 dark:peer-checked:[&_.wf-card-icon]:text-lime-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-lime-600 peer-checked:[&_.wf-card-indicator]:bg-lime-600',
            ],
            'teal' => [
                'input' => 'text-teal-600 focus:ring-teal-500',
                'solid' => 'peer-checked:border-teal-600 peer-checked:bg-teal-600 peer-checked:text-white',
                'text' => 'peer-checked:text-teal-600 dark:peer-checked:text-teal-400',
                'card' => 'peer-checked:border-teal-500 peer-checked:ring-teal-500/40 peer-checked:[&_.wf-card-icon]:text-teal-600 dark:peer-checked:[&_.wf-card-icon]:text-teal-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-teal-600 peer-checked:[&_.wf-card-indicator]:bg-teal-600',
            ],
            'sky' => [
                'input' => 'text-sky-600 focus:ring-sky-500',
                'solid' => 'peer-checked:border-sky-600 peer-checked:bg-sky-600 peer-checked:text-white',
                'text' => 'peer-checked:text-sky-600 dark:peer-checked:text-sky-400',
                'card' => 'peer-checked:border-sky-500 peer-checked:ring-sky-500/40 peer-checked:[&_.wf-card-icon]:text-sky-600 dark:peer-checked:[&_.wf-card-icon]:text-sky-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-sky-600 peer-checked:[&_.wf-card-indicator]:bg-sky-600',
            ],
            'indigo' => [
                'input' => 'text-indigo-600 focus:ring-indigo-500',
                'solid' => 'peer-checked:border-indigo-600 peer-checked:bg-indigo-600 peer-checked:text-white',
                'text' => 'peer-checked:text-indigo-600 dark:peer-checked:text-indigo-400',
                'card' => 'peer-checked:border-indigo-500 peer-checked:ring-indigo-500/40 peer-checked:[&_.wf-card-icon]:text-indigo-600 dark:peer-checked:[&_.wf-card-icon]:text-indigo-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-indigo-600 peer-checked:[&_.wf-card-indicator]:bg-indigo-600',
            ],
            'violet' => [
                'input' => 'text-violet-600 focus:ring-violet-500',
                'solid' => 'peer-checked:border-violet-600 peer-checked:bg-violet-600 peer-checked:text-white',
                'text' => 'peer-checked:text-violet-600 dark:peer-checked:text-violet-400',
                'card' => 'peer-checked:border-violet-500 peer-checked:ring-violet-500/40 peer-checked:[&_.wf-card-icon]:text-violet-600 dark:peer-checked:[&_.wf-card-icon]:text-violet-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-violet-600 peer-checked:[&_.wf-card-indicator]:bg-violet-600',
            ],
            'purple' => [
                'input' => 'text-purple-600 focus:ring-purple-500',
                'solid' => 'peer-checked:border-purple-600 peer-checked:bg-purple-600 peer-checked:text-white',
                'text' => 'peer-checked:text-purple-600 dark:peer-checked:text-purple-400',
                'card' => 'peer-checked:border-purple-500 peer-checked:ring-purple-500/40 peer-checked:[&_.wf-card-icon]:text-purple-600 dark:peer-checked:[&_.wf-card-icon]:text-purple-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-purple-600 peer-checked:[&_.wf-card-indicator]:bg-purple-600',
            ],
            'fuchsia' => [
                'input' => 'text-fuchsia-600 focus:ring-fuchsia-500',
                'solid' => 'peer-checked:border-fuchsia-600 peer-checked:bg-fuchsia-600 peer-checked:text-white',
                'text' => 'peer-checked:text-fuchsia-600 dark:peer-checked:text-fuchsia-400',
                'card' => 'peer-checked:border-fuchsia-500 peer-checked:ring-fuchsia-500/40 peer-checked:[&_.wf-card-icon]:text-fuchsia-600 dark:peer-checked:[&_.wf-card-icon]:text-fuchsia-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-fuchsia-600 peer-checked:[&_.wf-card-indicator]:bg-fuchsia-600',
            ],
            'pink' => [
                'input' => 'text-pink-600 focus:ring-pink-500',
                'solid' => 'peer-checked:border-pink-600 peer-checked:bg-pink-600 peer-checked:text-white',
                'text' => 'peer-checked:text-pink-600 dark:peer-checked:text-pink-400',
                'card' => 'peer-checked:border-pink-500 peer-checked:ring-pink-500/40 peer-checked:[&_.wf-card-icon]:text-pink-600 dark:peer-checked:[&_.wf-card-icon]:text-pink-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-pink-600 peer-checked:[&_.wf-card-indicator]:bg-pink-600',
            ],
            'rose' => [
                'input' => 'text-rose-600 focus:ring-rose-500',
                'solid' => 'peer-checked:border-rose-600 peer-checked:bg-rose-600 peer-checked:text-white',
                'text' => 'peer-checked:text-rose-600 dark:peer-checked:text-rose-400',
                'card' => 'peer-checked:border-rose-500 peer-checked:ring-rose-500/40 peer-checked:[&_.wf-card-icon]:text-rose-600 dark:peer-checked:[&_.wf-card-icon]:text-rose-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-rose-600 peer-checked:[&_.wf-card-indicator]:bg-rose-600',
            ],
            'slate' => [
                'input' => 'text-slate-600 focus:ring-slate-500',
                'solid' => 'peer-checked:border-slate-600 peer-checked:bg-slate-600 peer-checked:text-white',
                'text' => 'peer-checked:text-slate-600 dark:peer-checked:text-slate-300',
                'card' => 'peer-checked:border-slate-500 peer-checked:ring-slate-500/40 peer-checked:[&_.wf-card-icon]:text-slate-600 dark:peer-checked:[&_.wf-card-icon]:text-slate-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-slate-600 peer-checked:[&_.wf-card-indicator]:bg-slate-600',
            ],
            'zinc' => [
                'input' => 'text-zinc-600 focus:ring-zinc-500',
                'solid' => 'peer-checked:border-zinc-600 peer-checked:bg-zinc-600 peer-checked:text-white',
                'text' => 'peer-checked:text-zinc-600 dark:peer-checked:text-zinc-300',
                'card' => 'peer-checked:border-zinc-500 peer-checked:ring-zinc-500/40 peer-checked:[&_.wf-card-icon]:text-zinc-600 dark:peer-checked:[&_.wf-card-icon]:text-zinc-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-zinc-600 peer-checked:[&_.wf-card-indicator]:bg-zinc-600',
            ],
            'neutral' => [
                'input' => 'text-neutral-600 focus:ring-neutral-500',
                'solid' => 'peer-checked:border-neutral-600 peer-checked:bg-neutral-600 peer-checked:text-white',
                'text' => 'peer-checked:text-neutral-600 dark:peer-checked:text-neutral-300',
                'card' => 'peer-checked:border-neutral-500 peer-checked:ring-neutral-500/40 peer-checked:[&_.wf-card-icon]:text-neutral-600 dark:peer-checked:[&_.wf-card-icon]:text-neutral-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-neutral-600 peer-checked:[&_.wf-card-indicator]:bg-neutral-600',
            ],
            'stone' => [
                'input' => 'text-stone-600 focus:ring-stone-500',
                'solid' => 'peer-checked:border-stone-600 peer-checked:bg-stone-600 peer-checked:text-white',
                'text' => 'peer-checked:text-stone-600 dark:peer-checked:text-stone-300',
                'card' => 'peer-checked:border-stone-500 peer-checked:ring-stone-500/40 peer-checked:[&_.wf-card-icon]:text-stone-600 dark:peer-checked:[&_.wf-card-icon]:text-stone-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-stone-600 peer-checked:[&_.wf-card-indicator]:bg-stone-600',
            ],
            'gray', 'secondary' => [
                'input' => 'text-gray-600 focus:ring-gray-500',
                'solid' => 'peer-checked:border-gray-600 peer-checked:bg-gray-600 peer-checked:text-white',
                'text' => 'peer-checked:text-gray-600 dark:peer-checked:text-gray-300',
                'card' => 'peer-checked:border-gray-500 peer-checked:ring-gray-500/40 peer-checked:[&_.wf-card-icon]:text-gray-600 dark:peer-checked:[&_.wf-card-icon]:text-gray-300',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-gray-600 peer-checked:[&_.wf-card-indicator]:bg-gray-600',
            ],
            default => [
                'input' => 'text-primary-600 focus:ring-primary-500',
                'solid' => 'peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-checked:text-white',
                'text' => 'peer-checked:text-primary-600 dark:peer-checked:text-primary-400',
                'card' => 'peer-checked:border-primary-500 peer-checked:ring-primary-500/40 peer-checked:[&_.wf-card-icon]:text-primary-600 dark:peer-checked:[&_.wf-card-icon]:text-primary-400',
                'indicator' => 'peer-checked:[&_.wf-card-indicator]:border-primary-600 peer-checked:[&_.wf-card-indicator]:bg-primary-600',
            ],
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
    public static function getLinkColorClasses(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline',
            'success', 'green', 'emerald' => 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 hover:underline',
            'danger', 'red' => 'text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 hover:underline',
            'warning', 'yellow', 'amber' => 'text-amber-600 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 hover:underline',
            'info', 'cyan' => 'text-cyan-600 hover:text-cyan-800 dark:text-cyan-400 dark:hover:text-cyan-300 hover:underline',
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
    public static function getSoftBgClass(string $color): string
    {
        return match ($color) {
            'primary', 'blue' => 'bg-primary-200 dark:bg-primary-900',
            'success', 'green', 'emerald' => 'bg-emerald-200 dark:bg-emerald-900',
            'danger', 'red' => 'bg-red-200 dark:bg-red-900',
            'warning', 'yellow', 'amber' => 'bg-amber-200 dark:bg-amber-900',
            'info', 'cyan' => 'bg-cyan-200 dark:bg-cyan-900',
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
     * `primary` alias and every raw hue are accepted too. Class strings are kept
     * literal so Tailwind's JIT scanner can see them, which is also what makes the
     * mapping a safe allow-list (no arbitrary class injection from owner-supplied
     * color names). Foreground companion: {@see getFillTextClasses()}.
     *
     * NOTE: every hue branch here must also be listed literally in
     * `resources/views/widgets/bar-chart/safelist.blade.php` (guarded by
     * BarChartSafelistTest) so a consuming app's Tailwind build generates them.
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
            'gray', 'secondary' => 'text-slate-600 dark:text-slate-400',
            default => 'text-primary-600 dark:text-primary-400',
        };
    }

    /**
     * Get solid color classes for a modal submit button.
     *
     * Canonical source for the primary confirm/submit button at the bottom of an
     * action modal (both the slide-over and centered-dialog layouts), so the two
     * footers stay in sync instead of each re-encoding the hue map. Pairs with a
     * `text-white` base. Semantic names map to fixed hues; an unset/unknown color
     * falls back to the brand primary. Class strings are kept literal so
     * Tailwind's JIT scanner can see them (safe allow-list).
     */
    public static function getModalSubmitButtonClasses(string $color): string
    {
        return match ($color) {
            'danger', 'red' => 'bg-red-600 hover:bg-red-700 active:bg-red-800 focus:ring-red-500',
            'success', 'green', 'emerald' => 'bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 focus:ring-emerald-500',
            'warning', 'yellow', 'amber' => 'bg-amber-500 hover:bg-amber-600 active:bg-amber-700 focus:ring-amber-500',
            'info', 'cyan' => 'bg-cyan-600 hover:bg-cyan-700 active:bg-cyan-800 focus:ring-cyan-500',
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
            default => 'bg-primary-600 hover:bg-primary-700 active:bg-primary-800 focus:ring-primary-500',
        };
    }

    /**
     * Get soft alert/banner color classes (tinted background + border + text).
     *
     * Canonical source for the "alert" / inline banner surface — a soft tinted
     * block with a matching border and readable foreground. Distinct from the
     * badge pill and the solid button surfaces, so it owns its own resolver.
     * Semantic names map to fixed hues (success → emerald, warning → amber,
     * danger → red); `info`/`primary`/`blue` and an unset color resolve to the
     * neutral informational blue. Class strings are kept literal so Tailwind's
     * JIT scanner can see them, which also keeps the mapping a safe allow-list.
     */
    public static function getAlertColorClasses(string $color): string
    {
        return match ($color) {
            'success', 'green', 'emerald' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300',
            'warning', 'yellow', 'amber' => 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300',
            'danger', 'red' => 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
            default => 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300',
        };
    }

    /**
     * Get color for modal icon background.
     *
     * Canonical source for the rounded icon "chip" behind modal / confirmation
     * dialog icons. Semantic names map to fixed hues; `primary` and `gray` are
     * supported for neutral modals, `info`/`blue` resolves to the neutral
     * informational blue, and the default arm is neutral gray so a modal without
     * an explicit icon color does not look like a warning.
     */
    public static function getModalIconBgClass(string $color): string
    {
        return match ($color) {
            'danger', 'red' => 'bg-red-100 dark:bg-red-900/30',
            'warning', 'yellow', 'amber' => 'bg-amber-100 dark:bg-amber-900/30',
            'success', 'green', 'emerald' => 'bg-emerald-100 dark:bg-emerald-900/30',
            'info', 'blue' => 'bg-blue-100 dark:bg-blue-900/30',
            'cyan' => 'bg-cyan-100 dark:bg-cyan-900/30',
            'primary' => 'bg-primary-100 dark:bg-primary-900/30',
            'orange' => 'bg-orange-100 dark:bg-orange-900/30',
            'lime' => 'bg-lime-100 dark:bg-lime-900/30',
            'teal' => 'bg-teal-100 dark:bg-teal-900/30',
            'sky' => 'bg-sky-100 dark:bg-sky-900/30',
            'indigo' => 'bg-indigo-100 dark:bg-indigo-900/30',
            'violet' => 'bg-violet-100 dark:bg-violet-900/30',
            'purple' => 'bg-purple-100 dark:bg-purple-900/30',
            'fuchsia' => 'bg-fuchsia-100 dark:bg-fuchsia-900/30',
            'pink' => 'bg-pink-100 dark:bg-pink-900/30',
            'rose' => 'bg-rose-100 dark:bg-rose-900/30',
            'slate' => 'bg-slate-100 dark:bg-slate-900/30',
            'zinc' => 'bg-zinc-100 dark:bg-zinc-900/30',
            'neutral' => 'bg-neutral-100 dark:bg-neutral-900/30',
            'stone' => 'bg-stone-100 dark:bg-stone-900/30',
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
            'warning', 'yellow', 'amber' => 'text-amber-600 dark:text-amber-400',
            'success', 'green', 'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'info', 'blue' => 'text-blue-600 dark:text-blue-400',
            'cyan' => 'text-cyan-600 dark:text-cyan-400',
            'primary' => 'text-primary-600 dark:text-primary-400',
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
            'gray', 'secondary' => 'text-gray-600 dark:text-gray-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }
}
