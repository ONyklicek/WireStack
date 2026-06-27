<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Support;

use InvalidArgumentException;

/**
 * Validates SQL identifiers and prevents injection edge cases.
 *
 * All user-supplied column names, table names, and aliases pass through
 * this validator before being used in raw SQL expressions.
 */
final class SqlSafety
{
    /**
     * Pattern for valid SQL identifiers: letters, digits, underscores.
     * Allows dotted references (table.column) and backtick-quoted identifiers.
     */
    private const IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/';

    /**
     * SQL keywords that cannot be used as bare identifiers.
     *
     * @var array<int, string>
     */
    private const RESERVED_WORDS = [
        'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE',
        'TRUNCATE', 'EXEC', 'EXECUTE', 'UNION', 'INTO', 'FROM', 'WHERE',
        'TABLE', 'DATABASE', 'GRANT', 'REVOKE',
    ];

    /**
     * Validate a SQL identifier (column name, table name, alias).
     *
     * @throws InvalidArgumentException if the identifier is not safe
     */
    public static function assertValidIdentifier(string $identifier): void
    {
        if ($identifier === '') {
            throw new InvalidArgumentException('SQL identifier cannot be empty.');
        }

        if (! preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw new InvalidArgumentException(
                "Invalid SQL identifier [{$identifier}]. Only letters, digits, underscores, and dots are allowed."
            );
        }

        if (in_array(strtoupper($identifier), self::RESERVED_WORDS, true)) {
            throw new InvalidArgumentException(
                "SQL identifier [{$identifier}] is a reserved word and cannot be used as a bare identifier."
            );
        }
    }

    /**
     * Check if an identifier is valid without throwing.
     */
    public static function isValidIdentifier(string $identifier): bool
    {
        if ($identifier === '') {
            return false;
        }

        return (bool) preg_match(self::IDENTIFIER_PATTERN, $identifier);
    }

    /**
     * Validate a sort direction.
     *
     * @throws InvalidArgumentException if direction is not asc/desc
     */
    public static function assertValidDirection(string $direction): void
    {
        if (! in_array(strtolower($direction), ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(
                "Invalid sort direction [{$direction}]. Must be 'asc' or 'desc'."
            );
        }
    }

    /**
     * Normalise a sort direction to a safe `asc`/`desc` keyword.
     *
     * Unlike {@see assertValidDirection()} this never throws — any untrusted or
     * unexpected value collapses to the safe `asc` default. Use this when a
     * direction is interpolated into raw SQL (orderByRaw) and a hard failure is
     * undesirable.
     */
    public static function normalizeDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Normalise a NULLS position to a safe `FIRST`/`LAST` keyword, or null.
     *
     * Accepts both the bare keyword ("LAST") and the full "NULLS LAST" form and
     * always returns the bare keyword (callers prepend "NULLS"). Any value
     * outside the allow-list collapses to null (the database default ordering).
     */
    public static function normalizeNullsPosition(?string $nullsPosition): ?string
    {
        $normalised = preg_replace('/^NULLS\s+/', '', strtoupper(trim((string) $nullsPosition)));

        return match ($normalised) {
            'FIRST' => 'FIRST',
            'LAST' => 'LAST',
            default => null,
        };
    }

    /**
     * Validate an SQL operator.
     *
     * @throws InvalidArgumentException if operator is not allowed
     */
    public static function assertValidOperator(string $operator): void
    {
        $allowed = [
            '=', '!=', '<>', '>', '<', '>=', '<=',
            'LIKE', 'NOT LIKE', 'ILIKE',
            'IN', 'NOT IN',
            'BETWEEN', 'NOT BETWEEN',
            'IS NULL', 'IS NOT NULL',
        ];

        if (! in_array(strtoupper($operator), $allowed, true)) {
            throw new InvalidArgumentException(
                "Invalid SQL operator [{$operator}]."
            );
        }
    }

    /**
     * Validate a qualified column reference (alias.column or just column).
     *
     * @throws InvalidArgumentException if invalid
     */
    public static function assertValidQualifiedColumn(string $column): void
    {
        // Allow raw SQL expressions (they bypass identifier validation)
        if (str_contains($column, '(') || str_contains($column, ' ')) {
            return;
        }

        self::assertValidIdentifier($column);
    }
}
