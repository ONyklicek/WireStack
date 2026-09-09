<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

/**
 * Colour for a surface that is telling the reader something — an alert banner,
 * the icon a modal opens with.
 *
 * These two share a rule nothing else has, which is why they are one class:
 * **a colour named directly, that is not a role, is drawn informational rather
 * than decorated.** An alert asked for `purple` comes back the neutral blue on
 * purpose. A role comes back as whatever `wire-core.colors` points it at, in the
 * whole palette, because the other half of the same contract says the roles get
 * their own look.
 */
final class NoticePalette
{
    /**
     * Get soft alert/banner color classes (tinted background + border + text).
     *
     * Canonical source for the "alert" / inline banner surface — a soft tinted
     * block with a matching border and readable foreground. Distinct from the
     * badge pill and the solid button surfaces, so it owns its own resolver.
     * Semantic names map to the hues `wire-core.colors` gives them (success →
     * emerald, warning → amber, danger → red, info → cyan by default);
     * `primary`/`blue` and an unset colour resolve to the neutral informational
     * blue, which is what an unowned colour still falls to. Class strings are kept literal so Tailwind's
     * JIT scanner can see them, which also keeps the mapping a safe allow-list.
     */
    public static function alert(string $color): string
    {
        // Both halves of this surface's contract, which pull in opposite
        // directions once a role is configurable:
        //
        //   "only the semantic roles get their own look"  — so a role must be
        //   honoured whatever hue `wire-core.colors` points it at, including one
        //   this surface would never have curated on its own;
        //
        //   "everything else falls to the informational blue" — so an alert asked
        //   for `purple` is still drawn informational, because an alert carries
        //   meaning rather than decoration.
        //
        // The two are only compatible if the resolver knows *which* was asked
        // for, so it asks before normalizing.
        $wasRole = SemanticPalette::isRole($color);
        $color = SemanticPalette::hue($color);

        if (! $wasRole && ! in_array($color, self::curatedAlertHues(), true)) {
            return self::neutralAlert();
        }

        return match ($color) {
            'emerald' => 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300',
            'green' => 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-300',
            'amber' => 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/20 dark:border-amber-800 dark:text-amber-300',
            'yellow' => 'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-yellow-300',
            'red' => 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300',
            'cyan' => 'bg-cyan-50 border-cyan-200 text-cyan-800 dark:bg-cyan-900/20 dark:border-cyan-800 dark:text-cyan-300',
            'blue' => 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300',
            'primary' => 'bg-primary-50 border-primary-200 text-primary-800 dark:bg-primary-900/20 dark:border-primary-800 dark:text-primary-300',
            'orange' => 'bg-orange-50 border-orange-200 text-orange-800 dark:bg-orange-900/20 dark:border-orange-800 dark:text-orange-300',
            'lime' => 'bg-lime-50 border-lime-200 text-lime-800 dark:bg-lime-900/20 dark:border-lime-800 dark:text-lime-300',
            'teal' => 'bg-teal-50 border-teal-200 text-teal-800 dark:bg-teal-900/20 dark:border-teal-800 dark:text-teal-300',
            'sky' => 'bg-sky-50 border-sky-200 text-sky-800 dark:bg-sky-900/20 dark:border-sky-800 dark:text-sky-300',
            'indigo' => 'bg-indigo-50 border-indigo-200 text-indigo-800 dark:bg-indigo-900/20 dark:border-indigo-800 dark:text-indigo-300',
            'violet' => 'bg-violet-50 border-violet-200 text-violet-800 dark:bg-violet-900/20 dark:border-violet-800 dark:text-violet-300',
            'purple' => 'bg-purple-50 border-purple-200 text-purple-800 dark:bg-purple-900/20 dark:border-purple-800 dark:text-purple-300',
            'fuchsia' => 'bg-fuchsia-50 border-fuchsia-200 text-fuchsia-800 dark:bg-fuchsia-900/20 dark:border-fuchsia-800 dark:text-fuchsia-300',
            'pink' => 'bg-pink-50 border-pink-200 text-pink-800 dark:bg-pink-900/20 dark:border-pink-800 dark:text-pink-300',
            'rose' => 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-300',
            'slate' => 'bg-slate-50 border-slate-200 text-slate-800 dark:bg-slate-900/20 dark:border-slate-800 dark:text-slate-300',
            'zinc' => 'bg-zinc-50 border-zinc-200 text-zinc-800 dark:bg-zinc-900/20 dark:border-zinc-800 dark:text-zinc-300',
            'neutral' => 'bg-neutral-50 border-neutral-200 text-neutral-800 dark:bg-neutral-900/20 dark:border-neutral-800 dark:text-neutral-300',
            'stone' => 'bg-stone-50 border-stone-200 text-stone-800 dark:bg-stone-900/20 dark:border-stone-800 dark:text-stone-300',
            'gray' => 'bg-gray-50 border-gray-200 text-gray-800 dark:bg-gray-900/20 dark:border-gray-800 dark:text-gray-300',
            'black' => 'bg-gray-900 border-gray-700 text-white dark:bg-white dark:border-gray-300 dark:text-gray-900',
            'white' => 'bg-white border-gray-200 text-gray-900 dark:bg-gray-900 dark:border-gray-700 dark:text-white',
            default => self::neutralAlert(),
        };
    }

    /**
     * Get color for modal icon background.
     *
     * Canonical source for the rounded icon "chip" behind modal / confirmation
     * dialog icons. Semantic names resolve through `wire-core.colors` like
     * everywhere else; `primary` and `gray` are supported for neutral modals,
     * and the default arm is neutral gray so a modal without an explicit icon
     * color does not look like a warning.
     */
    public static function iconBg(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'red' => 'bg-red-100 dark:bg-red-900/30',
            'amber' => 'bg-amber-100 dark:bg-amber-900/30',
            'yellow' => 'bg-yellow-100 dark:bg-yellow-900/30',
            'emerald' => 'bg-emerald-100 dark:bg-emerald-900/30',
            'green' => 'bg-green-100 dark:bg-green-900/30',
            'blue' => 'bg-blue-100 dark:bg-blue-900/30',
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
            'black' => 'bg-gray-900 dark:bg-white',
            'white' => 'bg-white border border-gray-200 dark:bg-gray-900 dark:border-gray-700',
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
    public static function iconText(string $color): string
    {
        $color = SemanticPalette::hue($color);

        return match ($color) {
            'red' => 'text-red-600 dark:text-red-400',
            'amber' => 'text-amber-600 dark:text-amber-400',
            'yellow' => 'text-yellow-600 dark:text-yellow-400',
            'emerald' => 'text-emerald-600 dark:text-emerald-400',
            'green' => 'text-green-600 dark:text-green-400',
            'blue' => 'text-blue-600 dark:text-blue-400',
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
            'black' => 'text-gray-900 dark:text-white',
            'white' => 'text-white dark:text-gray-900',
            'gray', 'secondary' => 'text-gray-600 dark:text-gray-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    /**
     * What an alert draws when it is refusing to decorate.
     *
     * A method rather than a constant, and not by preference: several callers
     * reach the resolvers as `HasColor::…`, which makes `self::` inside them
     * resolve to the *trait* — and a trait constant cannot be read that way
     * ("Cannot access trait constant … directly"). A private static method has
     * no such rule, and the failure it avoids only shows up on the call sites
     * that happen to use that spelling.
     */
    private static function neutralAlert(): string
    {
        return 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-300';
    }

    /**
     * The hues an alert draws for a caller who named one directly.
     *
     * Deliberately short: these are the shipped role hues plus their obvious
     * cousins, so `->color('green')` on an alert still reads as an alert. Every
     * other hue is decoration and gets {@see self::neutralAlert()} — unless it
     * arrived as a role, which the resolver honours whatever it points at.
     *
     * @return array<int, string>
     */
    private static function curatedAlertHues(): array
    {
        return ['emerald', 'green', 'amber', 'yellow', 'red', 'cyan', 'black', 'white'];
    }
}
