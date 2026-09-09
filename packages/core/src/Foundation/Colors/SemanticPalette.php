<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Colors;

use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Which literal hue a semantic role renders as.
 *
 * `success`, `danger`, `warning` and `info` used to be hard-wired: every one of
 * {@see HasColor}'s nineteen resolvers
 * carried the role as a label sharing an arm with its hue — `'success', 'emerald' => …`
 * — so an application that wanted a teal success had no way to ask for one short
 * of publishing views. This is the one place that mapping now lives, and the
 * resolvers normalize through it before they match.
 *
 * **Roles resolve to a class name, not to a CSS variable.** That is deliberate
 * and it is the opposite of the choice a radius or a density setting would make.
 * A token needs the consuming application to define it or the class silently
 * never compiles, and it needs Tailwind 4 to be read at all; a role resolved to
 * a hue lands in a `match` arm whose class strings are literal, already scanned,
 * and already work on Tailwind 3. The cost that rules this approach out
 * elsewhere — that every emission site must call a resolver — is not paid here,
 * because colour already has exactly one owner.
 *
 * `primary` is deliberately absent. It is re-pointed in the application's own
 * `@theme` block (`--color-primary-*`), which is a different and older
 * mechanism, and giving it a second one would mean two answers to one question.
 *
 * **Every resolver normalizes, including the alert.** `info` used to render as
 * the alert banner's blue on three surfaces and cyan on sixteen — one role
 * wearing two colours, which reads as two meanings rather than one in two
 * tones. It is one hue now. The alert keeps the other half of its contract:
 * a caller naming a decorative hue directly still gets the neutral
 * informational blue, because an alert carries meaning rather than decoration,
 * and {@see self::isRole()} is how it tells the two apart.
 */
final class SemanticPalette
{
    /**
     * The role vocabulary, and what each renders as when nothing says otherwise.
     *
     * These four are exactly the roles that were hard-wired. `gray`/`secondary`
     * is not among them: it is the neutral the chrome is built from rather than
     * a role with a meaning, and re-pointing it would re-skin every border and
     * every muted label rather than the thing that means "this went wrong".
     *
     * @var array<string, string>
     */
    public const ROLES = [
        'success' => 'emerald',
        'danger' => 'red',
        'warning' => 'amber',
        'info' => 'cyan',
    ];

    /**
     * Resolve a colour token to the hue its classes are built from.
     *
     * Anything that is not a role passes through untouched, so this is safe to
     * call on every colour a resolver receives — which is what makes it one
     * line at the top of each resolver rather than a branch inside every arm.
     */
    public static function hue(string $color): string
    {
        if (! array_key_exists($color, self::ROLES)) {
            return $color;
        }

        $configured = config("wire-core.colors.$color");

        if (! is_string($configured) || $configured === '') {
            return self::ROLES[$color];
        }

        // An unknown hue would fall through every arm to the grey default, which
        // is how a typo becomes a colourless button nobody can explain. Ask the
        // vocabulary owner instead, and keep the shipped default when the answer
        // is no. A role may not name another role: that is either a cycle or a
        // second name for the same thing, and neither is worth supporting.
        if (array_key_exists($configured, self::ROLES) || Color::tryResolve($configured) === null) {
            return self::ROLES[$color];
        }

        return $configured;
    }

    /** Whether this token names a role rather than a literal hue. */
    public static function isRole(string $color): bool
    {
        return array_key_exists($color, self::ROLES);
    }
}
