<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Enums;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Filters\Filter;

/**
 * The comparisons a user can pick for a filter — one vocabulary for every
 * filter that offers a choice ({@see Filter::operators()}).
 *
 * Before this a filter had exactly one comparison, chosen by the developer
 * (`TextFilter::operator('starts_with')`): the user could narrow a column by
 * "contains" and nothing else, and "everything that is *not* from gmail" or
 * "rows with no email at all" could not be asked. The query planner underneath
 * already spoke `NOT IN`, `IS NULL` and `!=`; nothing on the screen did.
 *
 * Each case owns three things, so no filter re-encodes them:
 *
 *   - its label, as the operator picker and the indicator chip print it;
 *   - whether it needs a value ({@see requiresValue()}) — "is empty" does not,
 *     and a filter set to it is active with the value box empty;
 *   - the constraint itself, as a planner clause where the planner can say it
 *     ({@see toPlannerClause()}) and as a query callback where it cannot
 *     ({@see constrain()}): "does not contain" must keep NULLs, which one
 *     `NOT LIKE` clause drops.
 *
 * Negative operators ({@see isNegative()}) say "no related record matches"
 * across a relation — `whereDoesntHave`, not `whereHas(… NOT …)`, which would
 * keep a parent with one gmail address and one other.
 */
enum FilterOperator: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case In = 'in';
    case NotIn = 'not_in';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'gte';
    case LessThan = 'lt';
    case LessThanOrEqual = 'lte';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';

    /**
     * The operators a text filter offers when it is told to offer them all.
     *
     * @return list<self>
     */
    public static function forText(): array
    {
        return [self::Contains, self::NotContains, self::Equals, self::NotEquals, self::StartsWith, self::EndsWith, self::IsEmpty, self::IsNotEmpty];
    }

    /**
     * The operators a select filter offers when it is told to offer them all.
     *
     * @return list<self>
     */
    public static function forSelect(): array
    {
        return [self::In, self::NotIn, self::IsEmpty, self::IsNotEmpty];
    }

    /**
     * The operators a number filter offers when it is told to offer them all.
     *
     * @return list<self>
     */
    public static function forNumber(): array
    {
        return [self::Equals, self::NotEquals, self::GreaterThan, self::GreaterThanOrEqual, self::LessThan, self::LessThanOrEqual, self::IsEmpty, self::IsNotEmpty];
    }

    /**
     * Resolve a stored or configured value: the enum, its value, or one of the
     * operator spellings the text filter has always accepted (`like`, `=`, `>`…).
     */
    public static function resolve(self|string|null $operator): ?self
    {
        if ($operator instanceof self || $operator === null) {
            return $operator;
        }

        return self::tryFrom($operator) ?? match ($operator) {
            'like' => self::Contains,
            '=' => self::Equals,
            '!=', '<>' => self::NotEquals,
            '>' => self::GreaterThan,
            '>=' => self::GreaterThanOrEqual,
            '<' => self::LessThan,
            '<=' => self::LessThanOrEqual,
            default => null,
        };
    }

    public function label(): string
    {
        return Trans::get('wire-table::messages.operator_'.$this->value);
    }

    /** Whether the user has to type or pick a value for the filter to mean anything. */
    public function requiresValue(): bool
    {
        return ! in_array($this, [self::IsEmpty, self::IsNotEmpty], true);
    }

    /** Whether it excludes: across a relation this is "no related record matches". */
    public function isNegative(): bool
    {
        return in_array($this, [self::NotContains, self::NotEquals, self::NotIn], true);
    }

    /** The positive twin a negative operator is the complement of. */
    public function positive(): self
    {
        return match ($this) {
            self::NotContains => self::Contains,
            self::NotEquals => self::Equals,
            self::NotIn => self::In,
            default => $this,
        };
    }

    /**
     * The single planner clause this comparison is, or null when one clause
     * cannot say it and the filter must constrain the query itself.
     *
     * @return array{0: string, 1: mixed}|null [operator, value]
     */
    public function toPlannerClause(mixed $value): ?array
    {
        return match ($this) {
            self::IsNotEmpty => ['IS NOT NULL', null],
            self::Contains => is_scalar($value) ? ['LIKE', '%'.$value.'%'] : null,
            self::StartsWith => is_scalar($value) ? ['LIKE', $value.'%'] : null,
            self::EndsWith => is_scalar($value) ? ['LIKE', '%'.$value] : null,
            self::Equals => is_scalar($value) ? ['=', $value] : null,
            self::GreaterThan => is_scalar($value) ? ['>', $value] : null,
            self::GreaterThanOrEqual => is_scalar($value) ? ['>=', $value] : null,
            self::LessThan => is_scalar($value) ? ['<', $value] : null,
            self::LessThanOrEqual => is_scalar($value) ? ['<=', $value] : null,
            self::In => ['IN', array_values((array) $value)],
            // NOT LIKE / != / NOT IN drop NULL rows, and "is empty" is NULL or ''
            // — neither is one clause, so they constrain the query themselves.
            default => null,
        };
    }

    /**
     * Constrain a query on one column. The full semantics, including the cases
     * {@see toPlannerClause()} declines; a relation filter calls it inside its
     * `whereHas` / `whereDoesntHave`.
     *
     * `$blankIsEmpty` makes "is empty" also match `''` — right for a text
     * column, and wrong for a number or a date, where PostgreSQL refuses to
     * compare the column with a string at all.
     *
     * @param  Builder<Model>  $query
     */
    public function constrain(Builder $query, string $column, mixed $value, bool $blankIsEmpty = false): void
    {
        match ($this) {
            self::Contains => $query->where($column, 'like', '%'.$value.'%'),
            self::NotContains => $query->where(static fn (Builder $q) => $q->whereNull($column)->orWhere($column, 'not like', '%'.$value.'%')),
            self::StartsWith => $query->where($column, 'like', $value.'%'),
            self::EndsWith => $query->where($column, 'like', '%'.$value),
            self::Equals => $query->where($column, $value),
            self::NotEquals => $query->where(static fn (Builder $q) => $q->whereNull($column)->orWhere($column, '!=', $value)),
            self::In => $query->whereIn($column, array_values((array) $value)),
            self::NotIn => $query->where(static fn (Builder $q) => $q->whereNull($column)->orWhereNotIn($column, array_values((array) $value))),
            self::GreaterThan => $query->where($column, '>', $value),
            self::GreaterThanOrEqual => $query->where($column, '>=', $value),
            self::LessThan => $query->where($column, '<', $value),
            self::LessThanOrEqual => $query->where($column, '<=', $value),
            self::IsEmpty => $blankIsEmpty
                ? $query->where(static fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''))
                : $query->whereNull($column),
            self::IsNotEmpty => $blankIsEmpty
                ? $query->whereNotNull($column)->where($column, '!=', '')
                : $query->whereNotNull($column),
        };
    }
}
