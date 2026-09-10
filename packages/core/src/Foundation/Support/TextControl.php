<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Colors\ButtonPalette;

/**
 * Canonical owner of the chrome on a bordered text control.
 *
 * An `<input>`, a `<textarea>` and a `<select>` are one surface wearing three
 * tags: the same border, the same focus ring, the same dark-mode pair, the same
 * red when the value is rejected. Fourteen views across five packages had been
 * spelling that vocabulary out, and the copies had already drifted — the signed-out
 * field carried `mt-1` and put `text-sm` in a different group from the one
 * `wire-forms` puts it in, which is what two hand-kept copies of a class list do
 * on the first change to either.
 *
 * The groups are separate constants because they are separate decisions — a
 * control can be invalid without being hovered — and they are concatenated in a
 * const expression so the whole string is still literal source text. That word is
 * load-bearing: Tailwind's scanner reads source, so a class list assembled by
 * interpolation is never generated and the control renders unstyled. This is the
 * same trap {@see GapScale} exists to close for `gap-{$step}`.
 *
 * Living in `wire-core` is not a preference. `wire-module-auth` requires
 * `wire-core` and deliberately not `wire-forms` — its screens sit on the other
 * side of the sign-in door from the panel's forms — so a vocabulary both of them
 * read has exactly one layer it can live in. Consumers already point Tailwind at
 * this package's `src` directory — that is step one of the documented setup,
 * because the colour resolvers live here too.
 *
 * @see ButtonPalette the same idea for a button's hue
 */
final class TextControl
{
    /** Box, border and elevation — the part that makes it read as a control. */
    private const BASE = 'block w-full rounded-md border-gray-300 shadow-sm';

    /** The focus ring, in the accent an application sets once. */
    private const FOCUS = 'focus:border-primary-500 focus:ring-primary-500';

    /**
     * Placeholder text, light and dark.
     *
     * In the base rather than beside it: every one of these tags accepts a
     * `placeholder`, and the views that omitted this group omitted it by
     * oversight rather than by decision — a textarea with grey placeholder text
     * is what the rest of the field layer already does.
     */
    private const PLACEHOLDER = 'placeholder:text-gray-400 dark:placeholder:text-gray-500';

    /** Hover feedback, and the transition that keeps it from snapping. */
    private const HOVER = 'hover:border-gray-400 dark:hover:border-gray-500 transition-colors duration-150';

    /** The dark-mode pair, and the type scale the field layer settled on. */
    private const DARK = 'dark:bg-gray-800 dark:border-gray-600 dark:text-white text-sm';

    /**
     * Every always-on group, in the order `wire-forms` already established.
     *
     * Order is not significance — none of these utilities conflict, so CSS does
     * not care — but keeping it fixed is what lets a migrated view be checked
     * against its old output byte for byte instead of by eye.
     */
    private const CHROME = self::BASE.' '.self::FOCUS.' '.self::PLACEHOLDER.' '.self::HOVER.' '.self::DARK;

    /** Red border and red ring, for a value the server rejected. */
    private const REJECTED = 'border-red-500 focus:border-red-500 focus:ring-red-500';

    /**
     * The classes every bordered text control carries, whatever its state.
     *
     * Returned as one string so a caller can drop it into `@class([...])` as a
     * single unconditional entry, beside its own conditional ones.
     */
    public static function base(): string
    {
        return self::CHROME;
    }

    /**
     * The classes that mark a control whose value was rejected.
     *
     * Kept apart from {@see base()} rather than folded into a boolean argument:
     * the caller already has the condition — `$errors->has(...)` — and `@class`
     * already knows how to apply a string under one, so a flag here would be a
     * second way to say what Blade says better.
     */
    public static function rejected(): string
    {
        return self::REJECTED;
    }
}
