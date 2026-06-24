<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasSqlDebug
 *
 * Safe SQL debug utility. Extracts duplicated toSql logic from Table and WithTable.
 * Uses Str::replaceFirst instead of preg_replace to avoid issues when binding
 * values contain '?' characters.
 */
trait HasSqlDebug
{
    /**
     * Get SQL string with bindings replaced safely.
     *
     * Uses str_replace with limit=1 (via strpos+substr_replace) instead of
     * preg_replace('/\?/') which fails when a binding value itself contains '?'.
     */
    /**
     * @param  array<int, mixed>  $bindings
     */
    protected static function interpolateSql(string $sql, array $bindings): string
    {
        // Track where the next placeholder search should start so a '?' that
        // appears inside an already-substituted value is never re-matched.
        $offset = 0;

        foreach ($bindings as $binding) {
            $pos = strpos($sql, '?', $offset);
            if ($pos === false) {
                break;
            }

            $value = match (true) {
                is_null($binding) => 'NULL',
                is_bool($binding) => $binding ? '1' : '0',
                is_numeric($binding) => (string) $binding,
                default => "'".addslashes((string) $binding)."'",
            };

            $sql = substr_replace($sql, $value, $pos, 1);
            $offset = $pos + strlen($value);
        }

        return $sql;
    }

    /**
     * Get interpolated SQL from a Builder instance.
     */
    /**
     * @param  Builder<Model>  $query
     */
    protected static function builderToSql(Builder $query): string
    {
        return static::interpolateSql($query->toSql(), $query->getBindings());
    }
}
