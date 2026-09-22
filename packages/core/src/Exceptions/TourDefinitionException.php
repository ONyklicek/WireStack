<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a tour or one of its steps could never work as written.
 *
 * Every one of these is a definition-time mistake with a silent runtime: a tour
 * with no id cannot be acknowledged, and a step anchored to a name that is not a
 * hook resolves to a selector matching nothing — which the tour would then
 * *skip*, exactly as it skips a step whose element is genuinely absent from the
 * page. The skip is a feature for the second case and a trap for the first, and
 * the only place the two can be told apart is here, where the name is known.
 *
 * `InvalidArgumentException` because these are arguments, checked as they
 * arrive, before anything is registered or rendered.
 */
final class TourDefinitionException extends InvalidArgumentException implements WireException
{
    public static function emptyTourId(): self
    {
        return new self('A tour needs an id: it is what a user\'s acknowledgement is stored against.');
    }

    public static function malformedStepAnchor(string $anchor): self
    {
        return new self(
            "Tour step anchor [{$anchor}] is not an element-hook name. Hook names are kebab-case "
            .'(letters, digits and single hyphens, starting with a letter), and a step can only point at a name that exists.'
        );
    }

    public static function malformedStepAttribute(string $attribute): self
    {
        return new self(
            "Tour step attribute [{$attribute}] is not usable in a selector. Narrowing attributes are "
            .'kebab-case, and are matched with a `data-` prefix.'
        );
    }

    public static function stepless(string $tourId): self
    {
        return new self(
            "Tour [{$tourId}] has no steps. A tour with nothing to show would be acknowledged the "
            .'instant it started, marking content the user never saw as seen.'
        );
    }
}
