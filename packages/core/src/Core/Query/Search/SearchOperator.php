<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * What a single search token asks of a column.
 *
 * `Contains` is the ordinary substring match every search has always done. The
 * rest are the comparison forms a user can type into the same box (`>100`,
 * `10..20`) and are only ever applied to a column whose value type can answer
 * them — see {@see SearchValueType}.
 */
enum SearchOperator: string
{
    case Contains = 'contains';
    case Equals = '=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Between = 'between';

    /**
     * Whether this operator compares values rather than matching text.
     *
     * A comparison needs a typed column; a `Contains` matches any of them.
     */
    public function isComparison(): bool
    {
        return $this !== self::Contains;
    }

    /**
     * The SQL operator for a single-sided comparison, or null when the operator
     * needs more than one bound value (`Between`) or is not a comparison.
     */
    public function toSql(): ?string
    {
        return match ($this) {
            self::Equals => '=',
            self::GreaterThan => '>',
            self::GreaterThanOrEqual => '>=',
            self::LessThan => '<',
            self::LessThanOrEqual => '<=',
            self::Contains, self::Between => null,
        };
    }
}
