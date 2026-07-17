<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a value that would reach SQL is not provably safe.
 *
 * These are the guards that stop a column name, sort direction or operator —
 * any of which can arrive from a URL or a user preference — from being
 * interpolated into a query. Failing loudly is the point: silently dropping an
 * unsafe identifier would turn an injection attempt into a wrong result set.
 */
final class UnsafeSqlException extends InvalidArgumentException implements WireException
{
    public static function emptyIdentifier(): self
    {
        return new self('SQL identifier cannot be empty.');
    }

    public static function malformedIdentifier(string $identifier): self
    {
        return new self(
            "Invalid SQL identifier [{$identifier}]. Only letters, digits, underscores, and dots are allowed."
        );
    }

    public static function reservedIdentifier(string $identifier): self
    {
        return new self(
            "SQL identifier [{$identifier}] is a reserved word and cannot be used as a bare identifier."
        );
    }

    public static function invalidDirection(string $direction): self
    {
        return new self("Invalid sort direction [{$direction}]. Must be 'asc' or 'desc'.");
    }

    public static function invalidOperator(string $operator): self
    {
        return new self("Invalid SQL operator [{$operator}].");
    }
}
