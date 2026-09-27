<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\Console\Concerns\ResolvesTenants;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Runs the tenant migrations against each tenant's own database (ADR 0040 §2).
 *
 *   php artisan wire:tenants:migrate                   every tenant
 *   php artisan wire:tenants:migrate --tenant=acme     one, by route key or key
 *   php artisan wire:tenants:migrate --fresh --seed    drop, migrate and seed
 *
 * Inside `runAs()`, so the tenant connection points at that tenant's database
 * for exactly the length of its migration. The migrations are
 * `wire-core.tenancy.database.migrations`; the application's own stay where
 * they are and run with `migrate` as always.
 */
#[AsCommand(name: 'wire:tenants:migrate')]
final class MigrateTenantsCommand extends Command
{
    use ResolvesTenants;

    protected $signature = 'wire:tenants:migrate
        {--tenant=* : A tenant by route key or key — every tenant when none is given}
        {--fresh : Drop every table first}
        {--seed : Seed afterwards}
        {--seeder= : The seeder class to run}';

    protected $description = 'Run the tenant migrations in each tenant\'s database';

    public function handle(Tenancy $tenancy, TenantDatabases $databases): int
    {
        $tenants = $this->tenants((array) $this->option('tenant'));

        if ($tenants->isEmpty()) {
            $this->components->warn('No tenant to migrate.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $tenancy->runAs($tenant, fn (Model $tenant): int => $this->migrate($databases, $tenant));
        }

        return self::SUCCESS;
    }

    private function migrate(TenantDatabases $databases, Model $tenant): int
    {
        $path = (string) config('wire-core.tenancy.database.migrations', 'database/migrations/tenant');
        $options = array_filter([
            '--database' => $databases->connection(),
            '--path' => $path,
            '--realpath' => str_starts_with($path, '/'),
            '--force' => true,
            '--seed' => (bool) $this->option('seed'),
            '--seeder' => $this->option('seeder'),
        ], fn (mixed $value): bool => $value !== null && $value !== false);

        $this->components->task(
            "{$databases->nameFor($tenant)}",
            fn (): bool => $this->callSilently($this->option('fresh') ? 'migrate:fresh' : 'migrate', $options) === self::SUCCESS,
        );

        return self::SUCCESS;
    }
}
