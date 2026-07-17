<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

/**
 * Canonical comparison used by state-driven conditioning helpers
 * (`visibleWhen()`, `hiddenWhen()`, `disabledWhen()`, `requiredIf()`, ...).
 *
 * A scalar expectation is a strict equality check; an array expectation is an
 * "is one of" (in-array) check. Centralised here so every surface — fields,
 * layouts, columns, filters, actions — resolves sibling-state conditions the
 * same way rather than re-encoding the branch locally.
 */
final class StateMatcher
{
    /**
     * Whether the current value matches the expected value/set.
     */
    public static function matches(mixed $current, mixed $expected): bool
    {
        if (is_array($expected)) {
            return in_array($current, $expected, true);
        }

        return $current === $expected;
    }

    /**
     * A condition comparing a sibling field's live value against an expectation.
     *
     * `$get` is injected by {@see EvaluatesClosures} only on state-aware
     * components; elsewhere it arrives null and the condition answers
     * `$whenMissing`, so a surface with no state context keeps its default
     * rather than guessing.
     */
    public static function condition(string $field, mixed $value, bool $whenMissing): \Closure
    {
        return static function (?callable $get = null) use ($field, $value, $whenMissing): bool {
            if ($get === null) {
                return $whenMissing;
            }

            return self::matches($get($field), $value);
        };
    }
}
