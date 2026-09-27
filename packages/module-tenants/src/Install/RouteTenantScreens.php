<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Install;

use NyonCode\WireCore\Foundation\Routing\WireRoutes;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\RoutesFile;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleTenants\Routing\TenantRoutes;

/**
 * The `tenants` route group in routes/web.php — registering a company and
 * accepting an invitation, the two screens outside any company.
 *
 * Written the way the panel's group is written: into the application's own
 * route file, once, and never where the file or `wire-core.routes.groups`
 * already places the group (ADR 0041). **Only in an application that uses
 * tenancy**: with `wire-core.tenancy.enabled` off there are no companies to
 * register, and nothing here is offered. Nothing is asked: `tenants/` is the
 * prefix the reserved slugs keep free, and a signed-in person is the only one
 * either screen is for.
 */
final readonly class RouteTenantScreens implements SetupStep
{
    private RoutesFile $routes;

    public function __construct(?RoutesFile $routes = null)
    {
        $this->routes = $routes ?? RoutesFile::forApplication();
    }

    public function label(): string
    {
        return 'Company routes';
    }

    public function state(): SetupState
    {
        if (! $this->tenancy() || ! $this->routes->exists()) {
            return SetupState::Blocked;
        }

        return $this->placed() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if (! $this->tenancy()) {
            return 'tenancy is off (WIRE_TENANCY) — no company screens to route';
        }

        if (! $this->routes->exists()) {
            return 'no routes/web.php to add them to';
        }

        return $this->placed()
            ? 'the tenants route group is already placed'
            : "add Route::wire('tenants') to routes/web.php, under /tenants";
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        $group = <<<'PHP'


        // Added by php artisan wire:install. Registering a company and accepting
        // an invitation to one happen outside any company, so outside the tenant
        // zone — see docs/modules/tenants.md.
        Route::middleware(['web', 'auth'])->prefix('tenants')->group(function () {
            Route::wire('tenants');
        });

        PHP;

        if (! $this->routes->append($group)) {
            $console->warn('Could not write routes/web.php — add this yourself:'.$group);

            return SetupOutcome::Failed;
        }

        return SetupOutcome::Applied;
    }

    private function tenancy(): bool
    {
        return (bool) config('wire-core.tenancy.enabled', false);
    }

    private function placed(): bool
    {
        return $this->routes->wires(TenantRoutes::key()) || app(WireRoutes::class)->configured(TenantRoutes::key()) !== [];
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-tenants';
    }

    public function sort(): int
    {
        // Beside the panel's own routes (200), before the first administrator.
        return 210;
    }
}
