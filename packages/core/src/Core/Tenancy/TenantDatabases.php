<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Builder;
use NyonCode\WireCore\Core\Tenancy\Isolation\DatabaseIsolation;
use NyonCode\WireCore\Exceptions\TenancyException;

/**
 * Where a tenant's own database is, and how one comes into being (ADR 0040 §2).
 *
 * Configured under `wire-core.tenancy.database`:
 *
 *   'connection' => 'tenant',               // the connection tenant-owned models use
 *   'name' => 'tenant_{key}',               // the database name — `{key}` is the tenant's key,
 *                                           // `{slug}` its route key; a file path for SQLite
 *   'admin_connection' => null,             // who runs CREATE DATABASE — the default connection
 *
 * The name is a template in config rather than a closure, so `config:cache`
 * still works. Creating and dropping go through the admin connection's schema
 * builder, which is Laravel's own per driver: a statement on MySQL and
 * PostgreSQL, a file on SQLite.
 */
final readonly class TenantDatabases
{
    public function __construct(private DatabaseManager $db) {}

    /** The connection tenant-owned models are read through. */
    public function connection(): string
    {
        $connection = config('wire-core.tenancy.database.connection', 'tenant');

        if (! is_string($connection) || ! is_array(config("database.connections.{$connection}"))) {
            throw TenancyException::noTenantConnection(is_string($connection) ? $connection : get_debug_type($connection));
        }

        return $connection;
    }

    /** The name of this tenant's database. */
    public function nameFor(Model $tenant): string
    {
        $template = (string) config('wire-core.tenancy.database.name', 'tenant_{key}');

        return strtr($template, [
            '{key}' => (string) $tenant->getKey(),
            '{slug}' => (string) $tenant->getRouteKey(),
        ]);
    }

    public function create(Model $tenant): void
    {
        $this->admin()->createDatabase($this->nameFor($tenant));
    }

    public function drop(Model $tenant): void
    {
        $this->admin()->dropDatabaseIfExists($this->nameFor($tenant));
    }

    /** @see DatabaseIsolation — pointing the tenant connection at a database, or at none. */
    public function point(?string $database): void
    {
        $connection = $this->connection();

        config()->set("database.connections.{$connection}.database", $database);
        $this->db->purge($connection);
    }

    private function admin(): Builder
    {
        $admin = config('wire-core.tenancy.database.admin_connection');

        return $this->db->connection(is_string($admin) && $admin !== '' ? $admin : null)->getSchemaBuilder();
    }
}
