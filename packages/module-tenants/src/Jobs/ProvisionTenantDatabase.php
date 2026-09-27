<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;

/** A new company's own database, created and migrated (ADR 0040 §2). */
final class ProvisionTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public Model $tenant) {}

    public function handle(TenantDatabases $databases): void
    {
        $databases->create($this->tenant);

        Artisan::call('wire:tenants:migrate', ['--tenant' => [(string) $this->tenant->getKey()]]);
    }
}
