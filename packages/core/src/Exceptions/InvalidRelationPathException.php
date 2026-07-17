<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a dot-notation relation path cannot be parsed.
 *
 * Relation paths are author-written (`author.company.name`,
 * `posts->count()`), so this is a mistake in the component definition, surfaced
 * at parse time rather than as an empty cell at render time.
 */
final class InvalidRelationPathException extends InvalidArgumentException implements WireException
{
    public static function empty(): self
    {
        return new self('RelationPath cannot be empty.');
    }

    public static function noSegments(): self
    {
        return new self('RelationPath must have at least one segment.');
    }

    public static function malformedAggregate(string $path): self
    {
        return new self("Invalid aggregate syntax: {$path}");
    }
}
