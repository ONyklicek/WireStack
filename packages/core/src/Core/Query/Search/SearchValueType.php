<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * The kind of value a searchable column holds.
 *
 * Only this decides whether a comparison token (`>100`, `2026-01-01..`) may be
 * applied to a column: asking `amount > 100` of a `varchar` name column would
 * either error or compare lexically, so a column that cannot answer a
 * comparison is simply skipped for it.
 *
 * Resolution order is explicit-first: a component that declares its own type
 * ({@see Contracts\HasSearchValueType}) wins, then the model's cast, then the
 * registered database type; anything unrecognised stays `Text`.
 */
enum SearchValueType: string
{
    case Text = 'text';
    case Numeric = 'numeric';
    case Date = 'date';

    /**
     * A structured code with a fixed-width numeric tail — `8866 01`, `8866 02`.
     *
     * Compared as text, which is only correct because the tail is padded to a
     * constant width: `01 … 08` sorts alphabetically in the same order it sorts
     * numerically, while `9 … 10` would not. Never inferred — the owner has to
     * declare it, because only they know the values are padded.
     */
    case Code = 'code';

    /**
     * Resolve from an Eloquent cast (`decimal:2`, `datetime`, `integer`, …).
     */
    public static function fromCast(?string $cast): ?self
    {
        if ($cast === null) {
            return null;
        }

        // A parametrised cast carries its arguments after a colon: `decimal:2`.
        $base = strtolower(explode(':', $cast, 2)[0]);

        return match ($base) {
            'int', 'integer', 'real', 'float', 'double', 'decimal' => self::Numeric,
            'date', 'datetime', 'timestamp', 'immutable_date', 'immutable_datetime',
            'custom_datetime', 'immutable_custom_datetime' => self::Date,
            default => null,
        };
    }

    /**
     * Resolve from a database column type as reported by the schema.
     */
    public static function fromDatabaseType(?string $dbType): ?self
    {
        if ($dbType === null) {
            return null;
        }

        $type = strtolower($dbType);

        return match (true) {
            str_contains($type, 'int') => self::Numeric,
            str_contains($type, 'decimal'),
            str_contains($type, 'numeric'),
            str_contains($type, 'float'),
            str_contains($type, 'double'),
            str_contains($type, 'real') => self::Numeric,
            str_contains($type, 'date'),
            str_contains($type, 'time') => self::Date,
            default => null,
        };
    }

    /**
     * Whether a comparison token can be applied to a column of this type.
     */
    public function supportsComparison(): bool
    {
        return $this !== self::Text;
    }

    /**
     * Whether a column of this type is compared as text rather than as a value.
     */
    public function comparesAsText(): bool
    {
        return $this === self::Code;
    }
}
