<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Enums\Alignment;

/**
 * Canonical owner of the spacing between a layout's children.
 *
 * One rule, stated once: a step on Tailwind's 0–12 gap scale becomes a **literal**
 * `gap-*` utility. That word is the whole point — Tailwind's scanner reads source
 * text, so a class built by interpolation (`"gap-$step"`) is never generated, and
 * the element silently renders with no gap at all. This is the same trap the
 * {@see Alignment} enum exists to close for `text-{$align}`.
 *
 * Two vocabularies reach the same scale, because the two callers grew apart
 * before this class existed:
 *
 * - **Numeric** — `Foundation\Schema\Flex::gap(4)`, a step on Tailwind's scale.
 * - **Named** — `WireTable\Columns\SplitColumn::gap('sm')`, the design-system
 *   words the rest of the fluent API uses (`size()`, `textSize()`).
 *
 * Both are accepted everywhere. A caller keeps its own default, because the two
 * differ and always did: `Flex` sits at 4, a split cell at 3.
 */
final class GapScale
{
    /** Tailwind's own default for a `gap` without a step. */
    public const DEFAULT_STEP = 4;

    /** Design-system words mapped onto the numeric scale. */
    private const NAMED = [
        'none' => 0,
        'xs' => 1,
        'sm' => 2,
        'md' => 4,
        'lg' => 6,
        'xl' => 8,
    ];

    /**
     * The literal gap utility for a step, a numeric string, or a named size.
     *
     * Anything unrecognised falls back to `$default` rather than reaching the
     * DOM as a class that does not exist.
     */
    public static function classFor(int|string $gap, int $default = self::DEFAULT_STEP): string
    {
        return self::literal(self::step($gap, $default));
    }

    /** Resolve any accepted spelling to a step on the 0–12 scale. */
    public static function step(int|string $gap, int $default = self::DEFAULT_STEP): int
    {
        if (is_int($gap)) {
            return self::clamp($gap);
        }

        $token = strtolower(trim($gap));

        if (isset(self::NAMED[$token])) {
            return self::NAMED[$token];
        }

        if ($token !== '' && ctype_digit($token)) {
            return self::clamp((int) $token);
        }

        return self::clamp($default);
    }

    private static function clamp(int $step): int
    {
        return max(0, min($step, 12));
    }

    /**
     * Every arm is written out so Tailwind can scan it. Do not shorten this to
     * an interpolation — that is the defect this class was extracted to fix.
     */
    private static function literal(int $step): string
    {
        return match ($step) {
            0 => 'gap-0',
            1 => 'gap-1',
            2 => 'gap-2',
            3 => 'gap-3',
            4 => 'gap-4',
            5 => 'gap-5',
            6 => 'gap-6',
            7 => 'gap-7',
            8 => 'gap-8',
            9 => 'gap-9',
            10 => 'gap-10',
            11 => 'gap-11',
            default => 'gap-12',
        };
    }
}
