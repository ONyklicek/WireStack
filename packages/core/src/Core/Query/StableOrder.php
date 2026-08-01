<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a paginated query a total order, so a page means something.
 *
 * `LIMIT`/`OFFSET` over an unordered query is undefined: the engine may return
 * the rows in any order it likes, and nothing says the order is the same for
 * the next page. SQLite and MySQL/InnoDB happen to hand back primary-key order,
 * so the problem hides — but PostgreSQL stores rows in a heap, and an `UPDATE`
 * writes a *new* tuple at the end of it rather than in place. Editing a row on
 * page one therefore shifts everything behind it forward by one, and the record
 * that would have led page two is silently skipped: the user never sees it, and
 * nothing anywhere reports an error.
 *
 * Sorting by a column with duplicate values has the same hole for the same
 * reason, on every engine — ties are in whatever order the engine found them.
 *
 * The fix is one extra `ORDER BY` term: the primary key, appended last, so it
 * only ever decides between rows the existing ordering considers equal.
 *
 * It has to be appended after *everything* that orders — including a host's own
 * sort callback, which runs outside the query pipeline. Applied any earlier it
 * becomes the primary sort and silently overrides the ordering it was meant to
 * stabilise.
 */
final class StableOrder
{
    /**
     * Append the primary key as the final ordering term, where that is safe.
     *
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query): void
    {
        $base = $query->getQuery();

        // GROUP BY: a column that is not grouped or aggregated is not a legal
        // ordering term (PostgreSQL rejects it outright, MySQL in ONLY_FULL_GROUP_BY).
        // DISTINCT: PostgreSQL requires every ordering term to appear in the
        // select list. In both, the query is no longer a plain list of records
        // and the key does not identify a row of the result.
        if ($base->groups !== null && $base->groups !== []) {
            return;
        }

        if ($base->distinct !== false) {
            return;
        }

        // A union's ordering applies to the combined result, where the operands'
        // keys are not comparable as one column.
        if ($base->unions !== null && $base->unions !== []) {
            return;
        }

        $key = $query->getModel()->getQualifiedKeyName();

        if ($this->alreadyOrdersBy($base->orders ?? [], $key)) {
            return;
        }

        // Follow the direction already in force, so the tiebreaker reads as a
        // continuation of the sort rather than an argument with it: newest-first
        // stays newest-first among rows sharing a timestamp.
        $query->orderBy($key, $this->trailingDirection($base->orders ?? []));
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     */
    private function alreadyOrdersBy(array $orders, string $key): bool
    {
        $column = last(explode('.', $key));

        foreach ($orders as $order) {
            $ordered = $order['column'] ?? null;

            if (! is_string($ordered)) {
                // A raw ordering expression could name the key in any shape, so
                // it is not worth guessing at — leaving the term off a query
                // that already has it costs a redundant sort, adding one to a
                // query that does not costs correctness.
                continue;
            }

            if ($ordered === $key || last(explode('.', $ordered)) === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $orders
     */
    private function trailingDirection(array $orders): string
    {
        for ($i = count($orders) - 1; $i >= 0; $i--) {
            $direction = $orders[$i]['direction'] ?? null;

            if (is_string($direction)) {
                return strtolower($direction) === 'desc' ? 'desc' : 'asc';
            }
        }

        return 'asc';
    }
}
