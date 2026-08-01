<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * Applies one token to one searchable column.
 *
 * Text matching is handed to the driver strategy — LIKE and ILIKE differ, and
 * so does nothing else. Comparisons are plain portable SQL, so they are built
 * here once rather than three times over.
 *
 * Every predicate is added with `orWhere*`: the caller has already opened a
 * group per token, and within a token the columns widen the match.
 */
final class SearchClauseCompiler
{
    /**
     * Returns whether the clause could answer the token at all — a comparison
     * asked of a text column cannot, and the caller decides what to do about it.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, SearchClause $clause, SearchToken $token, SearchStrategy $strategy): bool
    {
        if (! $token->isComparison()) {
            $strategy->apply($query, $clause, $token->pattern);

            return true;
        }

        return match ($clause->valueType) {
            SearchValueType::Numeric => $this->applyNumeric($query, $clause, $token),
            SearchValueType::Date => $this->applyDate($query, $clause, $token),
            SearchValueType::Code => $this->applyCode($query, $clause, $token),
            SearchValueType::Text => false,
        };
    }

    /**
     * A structured code — `8866 01` — compared as text.
     *
     * `8866 01..08` reaches here as the range `01..08` carrying the series
     * `8866`, and both bounds are completed with it, so the whole thing is one
     * `BETWEEN '8866 01' AND '8866 08'`: a single indexable predicate rather
     * than one `LIKE` per number in the range.
     *
     * Comparing text only orders correctly while the numeric tail is padded to
     * a constant width, which is exactly what declaring a column as a code
     * asserts. A range typed across a width boundary — `50..100`, where the
     * stored form must be `050` for `100` to exist at all — is therefore
     * completed to the wider of the two rather than refused, because that is
     * the shape the data is in.
     *
     * @param  Builder<Model>  $query
     */
    private function applyCode(Builder $query, SearchClause $clause, SearchToken $token): bool
    {
        if ($token->operator === SearchOperator::Between) {
            if ($token->upper === null) {
                return false;
            }

            [$lower, $upper] = $this->alignWidths($token->value, $token->upper);

            $lower = $token->qualify($lower);
            $upper = $token->qualify($upper);

            return $this->between(
                $query,
                $clause,
                min($lower, $upper),
                max($lower, $upper),
            );
        }

        $operator = $token->operator->toSql();

        if ($operator === null) {
            return false;
        }

        return $this->compare($query, $clause, $operator, $token->qualify($token->value));
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyNumeric(Builder $query, SearchClause $clause, SearchToken $token): bool
    {
        $value = $this->toNumber($token->value);

        if ($value === null) {
            return false;
        }

        if ($token->operator === SearchOperator::Between) {
            $upper = $token->upper !== null ? $this->toNumber($token->upper) : null;

            if ($upper === null) {
                return false;
            }

            // A range typed the wrong way round is still the range the user meant.
            return $this->between($query, $clause, min($value, $upper), max($value, $upper));
        }

        $operator = $token->operator->toSql();

        if ($operator === null) {
            return false;
        }

        return $this->compare($query, $clause, $operator, $value);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyDate(Builder $query, SearchClause $clause, SearchToken $token): bool
    {
        $range = SearchDateRange::from($token->value);

        if ($range === null) {
            return false;
        }

        if ($token->operator === SearchOperator::Between) {
            $upper = $token->upper !== null ? SearchDateRange::from($token->upper) : null;

            if ($upper === null) {
                return false;
            }

            return $this->between($query, $clause, $range->start, $upper->end);
        }

        // A typed date is a span, so which end of it a comparison means depends
        // on the direction: "after 2026-01-31" starts once that day is over,
        // while "from 2026-01-31" starts as it begins.
        return match ($token->operator) {
            SearchOperator::Equals => $this->between($query, $clause, $range->start, $range->end),
            SearchOperator::GreaterThan => $this->compare($query, $clause, '>', $range->end),
            SearchOperator::GreaterThanOrEqual => $this->compare($query, $clause, '>=', $range->start),
            SearchOperator::LessThan => $this->compare($query, $clause, '<', $range->start),
            SearchOperator::LessThanOrEqual => $this->compare($query, $clause, '<=', $range->end),
            // Between was answered above; Contains never reaches a comparison.
            default => false,
        };
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function compare(Builder $query, SearchClause $clause, string $operator, mixed $value): bool
    {
        if ($clause->sqlExpression !== null) {
            [$operand, $bindings] = $this->operand($value);

            $query->orWhereRaw("{$clause->sqlExpression} {$operator} {$operand}", $bindings);

            return true;
        }

        $query->orWhere($clause->getQualifiedColumn(), $operator, $value);

        return true;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function between(Builder $query, SearchClause $clause, mixed $lower, mixed $upper): bool
    {
        if ($clause->sqlExpression !== null) {
            [$lowerSql, $lowerBindings] = $this->operand($lower);
            [$upperSql, $upperBindings] = $this->operand($upper);

            $query->orWhereRaw(
                "{$clause->sqlExpression} BETWEEN {$lowerSql} AND {$upperSql}",
                [...$lowerBindings, ...$upperBindings],
            );

            return true;
        }

        $query->orWhereBetween($clause->getQualifiedColumn(), [$lower, $upper]);

        return true;
    }

    /**
     * Both bounds of a code range, at the same width.
     *
     * `50..100` can only mean anything if the stored tail is three digits wide
     * — a two-digit series has no hundredth member — so the shorter bound is
     * completed to the wider one's width the way the value itself is stored.
     * Without this the range is compared at two different widths and `8866 050`
     * lands outside its own range, silently returning nothing.
     *
     * Padding is only applied to a bound that is entirely digits; anything else
     * is a code the owner writes some other way and is left exactly as typed.
     *
     * @return array{0: string, 1: string}
     */
    private function alignWidths(string $lower, string $upper): array
    {
        $width = max(strlen($lower), strlen($upper));

        return [
            ctype_digit($lower) ? str_pad($lower, $width, '0', STR_PAD_LEFT) : $lower,
            ctype_digit($upper) ? str_pad($upper, $width, '0', STR_PAD_LEFT) : $upper,
        ];
    }

    /**
     * The right-hand side of a comparison against an SQL expression.
     *
     * A fractional number is written into the SQL rather than bound. PDO sends
     * a PHP float as a *string*, and an engine only converts that back into a
     * number when the other side is a column it can take an affinity from — an
     * expression such as `amount * 2` has none, so SQLite compares
     * `1800 > '500.5'` as text and quietly returns nothing at all. The value has
     * already been proven numeric and finite, so there is nothing to inject.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function operand(mixed $value): array
    {
        if (is_float($value)) {
            return [var_export($value, true), []];
        }

        return ['?', [$value]];
    }

    /**
     * A typed number, as the narrowest type that survives the round trip.
     *
     * An integral value stays an integer so that it binds as one; a value that
     * is not finite (`1e400` parses to INF) cannot be compared against at all.
     */
    private function toNumber(string $raw): int|float|null
    {
        if (! is_numeric($raw)) {
            return null;
        }

        $value = (float) $raw;

        if (! is_finite($value)) {
            return null;
        }

        return $value == (int) $value && abs($value) < (float) PHP_INT_MAX
            ? (int) $value
            : $value;
    }
}
