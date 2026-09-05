<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Support;

use NyonCode\WireCore\Core\Support\SqlSafety;
use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * The one place a field's numeric configuration is checked.
 *
 * Roughly twenty fluent setters across the package take a count, a length, a
 * size or a step, and until now every one of them stored whatever it was given.
 * That is not a small gap: the values go on to become HTML attributes and
 * Laravel validation rules, so a wrong one does not fail — it renders. A rating
 * with no stars, a slider whose thumb will not move, `min:10|max:2` on a field
 * nobody can ever fill in correctly. All of them look like a styling problem,
 * and all of them are found by an end user rather than by the developer who
 * wrote the line.
 *
 * A pure-helper `Support` class rather than a trait, following
 * {@see SqlSafety}: the rules are the same for
 * every field, they carry no state, and a trait holding them would be business
 * logic in a trait (AI_CODING_STANDARD.md). The setters call these; the messages
 * live on {@see FormConfigurationException}, which owns the vocabulary.
 *
 * `null` always passes. Every one of these setters accepts null as "no
 * constraint", and removing a limit is not a mistake.
 */
final class FieldBounds
{
    /**
     * A count, length or step that is meaningless below 1.
     *
     * @throws FormConfigurationException
     */
    public static function assertPositive(string $component, string $method, int|float|null $value): void
    {
        if ($value === null) {
            return;
        }

        if ($value <= 0) {
            throw FormConfigurationException::boundMustBePositive($component, $method, $value);
        }
    }

    /**
     * A bound where zero still says something, but a negative number does not.
     *
     * @throws FormConfigurationException
     */
    public static function assertNotNegative(string $component, string $method, int|float|null $value): void
    {
        if ($value === null) {
            return;
        }

        if ($value < 0) {
            throw FormConfigurationException::boundCannotBeNegative($component, $method, $value);
        }
    }

    /**
     * A min/max pair that has to stay in order.
     *
     * Called from both setters with the counterpart's current value, so the pair
     * is checked whichever one is written second and the author's ordering does
     * not decide whether they get told. Either side being null means there is no
     * pair yet.
     *
     * @throws FormConfigurationException
     */
    public static function assertOrdered(
        string $component,
        string $lowerMethod,
        int|float|null $lower,
        string $upperMethod,
        int|float|null $upper,
    ): void {
        if ($lower === null || $upper === null) {
            return;
        }

        if ($lower > $upper) {
            throw FormConfigurationException::invertedBounds(
                $component,
                $lowerMethod,
                $lower,
                $upperMethod,
                $upper,
            );
        }
    }
}
