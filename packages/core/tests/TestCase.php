<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Tests;

use Livewire\LivewireServiceProvider;
use NyonCode\WireCore\WireCoreServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            WireCoreServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', static::testing_database_connection());
    }

    /**
     * Resolve the `testing` database connection from environment variables,
     * defaulting to an in-memory SQLite database. Exporting DB_CONNECTION (with
     * DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD) lets CI run the whole
     * suite against MySQL or PostgreSQL without any code changes.
     *
     * @return array<string, mixed>
     */
    protected static function testing_database_connection(): array
    {
        $driver = env('DB_CONNECTION', 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
            ];
        }

        return array_filter([
            'driver' => $driver,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'),
            'database' => env('DB_DATABASE', 'wire_test'),
            'username' => env('DB_USERNAME', $driver === 'pgsql' ? 'postgres' : 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => $driver === 'pgsql' ? 'utf8' : 'utf8mb4',
            'prefix' => '',
            'search_path' => $driver === 'pgsql' ? 'public' : null,
        ], static fn ($value): bool => $value !== null);
    }
}
