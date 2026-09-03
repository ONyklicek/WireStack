<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

/**
 * How a query plan names a column in SQL.
 *
 * Three clauses ask the same question — a sort, a filter and a search all carry
 * a column, an optional table alias from a join, and an optional raw expression
 * that replaces both — and each of them answered it with its own copy of the
 * same three lines. The answer is one rule, so it has one owner: a raw
 * expression wins outright, an alias qualifies the column, and a bare column
 * stands for itself.
 *
 * The result is spliced into raw SQL by callers ({@see Pipes\ApplySorting},
 * {@see Strategies\PostgresSearchStrategy}), so it never invents a quote or a
 * separator of its own — the grammar wraps it, and `Support\SqlSafety` is what
 * says the parts were safe to get here.
 */
final class ColumnReference
{
    /**
     * The column reference a clause's parts resolve to.
     *
     * @param  string  $column  the column name, alone or already dotted
     * @param  string|null  $tableAlias  the alias this clause reaches through, if it joined
     * @param  string|null  $sqlExpression  a raw expression standing in for the column
     */
    public static function qualify(string $column, ?string $tableAlias = null, ?string $sqlExpression = null): string
    {
        if ($sqlExpression !== null) {
            return $sqlExpression;
        }

        if ($tableAlias !== null) {
            return "{$tableAlias}.{$column}";
        }

        return $column;
    }
}
