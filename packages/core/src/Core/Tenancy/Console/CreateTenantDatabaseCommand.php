<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Console;

use Illuminate\Console\Command;
use NyonCode\WireCore\Core\Tenancy\Console\Concerns\ResolvesTenants;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Creates each named tenant's database and migrates it (ADR 0040 §2).
 *
 *   php artisan wire:tenants:create acme globex
 *   php artisan wire:tenants:create --all --no-migrate
 *
 * Through the admin connection's schema builder — a CREATE DATABASE on MySQL
 * and PostgreSQL, an empty file on SQLite — and then `wire:tenants:migrate`
 * for the same tenants. Registering a company does the same, in a job.
 */
#[AsCommand(name: 'wire:tenants:create')]
final class CreateTenantDatabaseCommand extends Command
{
    use ResolvesTenants;

    protected $signature = 'wire:tenants:create
        {tenant?* : Tenants by route key or key}
        {--all : Every tenant}
        {--no-migrate : Create the databases only}';

    protected $description = 'Create a tenant\'s own database and migrate it';

    public function handle(TenantDatabases $databases): int
    {
        $named = (array) $this->argument('tenant');

        if ($named === [] && ! $this->option('all')) {
            $this->components->error('Name a tenant, or pass --all.');

            return self::FAILURE;
        }

        $tenants = $this->tenants($named);

        foreach ($tenants as $tenant) {
            $this->components->task($databases->nameFor($tenant), fn () => $databases->create($tenant));
        }

        if (! $this->option('no-migrate') && $tenants->isNotEmpty()) {
            $this->call('wire:tenants:migrate', ['--tenant' => $tenants->map->getKey()->map(fn ($key): string => (string) $key)->all()]);
        }

        return self::SUCCESS;
    }
}
