<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\PostgresSearchStrategy;
use NyonCode\WireCore\Core\Support\DriverDetector;

/**
 * The pattern-match predicate every search strategy OR-es into the query.
 *
 * Only two things about it are the engine's: the operator, and whether the
 * operand needs casting to text first. Everything else was written out three
 * times — where the operand comes from, that a raw expression is spliced as it
 * is while a column is wrapped by the grammar, and that the predicate always
 * declares its escape character. Two of those three copies were byte-identical,
 * which is what a rule with no owner looks like just before it drifts.
 *
 * The operand rule is the load-bearing one: a clause's `sqlExpression` is SQL
 * and goes in raw, a column is an identifier and goes through the grammar. Wrap
 * an expression and the query asks for a column literally named
 * `CONCAT(first_name, ' ', last_name)`; splice a column and the search box is an
 * injection point.
 */
final class LikePredicate
{
    /**
     * OR a prepared pattern against the clause's operand, into the group the
     * caller has opened.
     *
     * @param  Builder<Model>  $builder
     * @param  string  $pattern  a LIKE pattern from {@see LikePattern}, not a raw term
     * @param  string  $operator  the engine's pattern operator, e.g. `LIKE` or `ILIKE`
     * @param  bool  $castToText  cast the operand first, for an engine with no implicit cast
     */
    public static function orWhereMatches(
        Builder $builder,
        SearchClause $clause,
        string $pattern,
        string $operator,
        bool $castToText = false,
    ): void {
        $operand = $clause->sqlExpression
            ?? $builder->getQuery()->getGrammar()->wrap($clause->getQualifiedColumn());

        if ($castToText) {
            $operand = "CAST({$operand} AS TEXT)";
        }

        $builder->orWhereRaw("{$operand} {$operator} ?".LikePattern::escapeClause(), [$pattern]);
    }

    /**
     * Constrain a column to a prepared pattern — the filter's twin of
     * {@see orWhereMatches()}: AND-ed (or with `$boolean`) rather than OR-ed
     * into a search group, optionally negated, and matched the way the search
     * box matches on the same engine — case-insensitively, which on PostgreSQL
     * means `ILIKE` over the column cast to text (see
     * {@see PostgresSearchStrategy}).
     *
     * @param  Builder<Model>  $builder
     * @param  string  $column  an identifier; wrapped by the grammar, never spliced
     * @param  string  $pattern  a LIKE pattern from {@see LikePattern}, not a raw term
     */
    public static function whereMatches(
        Builder $builder,
        string $column,
        string $pattern,
        bool $not = false,
        string $boolean = 'and',
    ): void {
        $postgres = DriverDetector::isPostgres(DriverDetector::fromBuilder($builder));
        $operand = $builder->getQuery()->getGrammar()->wrap($column);

        if ($postgres) {
            $operand = "CAST({$operand} AS TEXT)";
        }

        $operator = ($not ? 'NOT ' : '').($postgres ? 'ILIKE' : 'LIKE');

        $builder->whereRaw("{$operand} {$operator} ?".LikePattern::escapeClause(), [$pattern], $boolean);
    }
}
