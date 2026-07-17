<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a relation manager is misconfigured, or asked to perform an
 * operation the underlying relationship type cannot support.
 *
 * The write helpers are deliberately strict: attaching to a has-many or
 * creating through a belongs-to are not "nearly right", and quietly doing
 * nothing would leave an author believing a record had been saved.
 */
final class RelationManagerException extends RuntimeException implements WireException
{
    public static function missingOwnerRecord(string $manager): self
    {
        return new self($manager.' requires an ownerRecord.');
    }

    public static function missingRelationshipName(string $manager): self
    {
        return new self($manager.' must define a $relationship name.');
    }

    public static function notARelationship(string $manager, string $name, string $owner): self
    {
        return new self("{$manager}: [{$name}] is not an Eloquent relationship on {$owner}.");
    }

    public static function cannotCreateRelated(string $relationship): self
    {
        return new self(
            "The [{$relationship}] relationship does not support creating related records."
        );
    }

    public static function requiresBelongsToMany(string $manager, string $operation, string $relationship): self
    {
        return new self(
            "{$manager}::{$operation}() requires a belongs-to-many relationship, [{$relationship}] given."
        );
    }
}
