<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the database driver from a connection or builder.
 */
final class DriverDetector
{
    public const MYSQL = 'mysql';

    public const POSTGRES = 'pgsql';

    public const SQLITE = 'sqlite';

    public const SQLSERVER = 'sqlsrv';

    /**
     * Detect the driver from an Eloquent builder.
     *
     * @param  Builder<Model>  $builder
     */
    public static function fromBuilder(Builder $builder): string
    {
        /** @var Connection $connection */
        $connection = $builder->getQuery()->getConnection();

        return self::fromConnection($connection);
    }

    /**
     * Detect the driver from a database connection.
     */
    public static function fromConnection(Connection $connection): string
    {
        return $connection->getDriverName();
    }

    /**
     * Check if the driver is MySQL (or MariaDB).
     */
    public static function isMysql(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    /**
     * Check if the driver is PostgreSQL.
     */
    public static function isPostgres(string $driver): bool
    {
        return $driver === self::POSTGRES;
    }

    /**
     * Check if the driver is SQLite.
     */
    public static function isSqlite(string $driver): bool
    {
        return $driver === self::SQLITE;
    }
}
