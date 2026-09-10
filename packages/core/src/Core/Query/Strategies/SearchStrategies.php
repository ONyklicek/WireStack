<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Support\DriverDetector;

/**
 * Which strategy a connection's text matching goes through.
 *
 * Three lines that were a private method on the query executor, and therefore
 * unreachable from anywhere else. Everything that searches text has the same
 * question — PostgreSQL matches case-insensitively only through `ILIKE`, the
 * others through `LIKE` — and the second caller found out the expensive way:
 * the mention source wrote its own `LIKE` and matched nothing on Postgres, where
 * `LIKE` is case-sensitive and a person typing "cen" is not offered "Ceník".
 *
 * So the answer is owned once, and a caller states which builder it is asking
 * about rather than which engine it thinks it is on.
 */
final class SearchStrategies
{
    /**
     * @param  Builder<Model>  $builder
     */
    public static function for(Builder $builder): SearchStrategy
    {
        $driver = DriverDetector::fromBuilder($builder);

        if (DriverDetector::isPostgres($driver)) {
            return new PostgresSearchStrategy;
        }

        if (DriverDetector::isMysql($driver)) {
            return new MySqlSearchStrategy;
        }

        return new SqliteSearchStrategy;
    }
}
