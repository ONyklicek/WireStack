<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a model cannot be registered as mentionable.
 *
 * The registry is filled once at boot, so this can never reach a request — and
 * that is exactly why it throws rather than shrugging. A silently skipped
 * registration costs every mention of that model its fresh name, and the symptom
 * (documents quietly showing the label they were written with) has nothing
 * anywhere connecting it back to a typo in a provider.
 */
final class MentionRegistrationException extends InvalidArgumentException implements WireException
{
    /** A type that is neither a model class nor anything the morph map knows. */
    public static function unknownType(string $type): self
    {
        return new self(
            "[{$type}] is neither a model class nor a registered morph alias, so it cannot be "
            .'mentioned. Register the model class itself, or an alias declared in '
            .'Relation::morphMap().'
        );
    }
}
