<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants;

use Livewire\Livewire;
use NyonCode\LaravelPackageToolkit\Commands\InstallCommand;
use NyonCode\LaravelPackageToolkit\Packager;
use NyonCode\LaravelPackageToolkit\PackageServiceProvider;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Routing\RouteGroups;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireModuleTenants\Install\RouteTenantScreens;
use NyonCode\WireModuleTenants\Livewire\RegisterTenant;
use NyonCode\WireModuleTenants\Pages\CompanyProfile;
use NyonCode\WireModuleTenants\Pages\ListMembers;
use NyonCode\WireModuleTenants\Routing\TenantRoutes;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * Companies as a ready-made area (ADR 0040 §8): registering one, its profile,
 * and its members.
 *
 * What it assumes of the application is what the tenant zone already needs:
 * a user model implementing `HasTenants` (`InteractsWithTenants` reads this
 * module's `tenant_user` pivot), and a tenant zone routing the module's two
 * resources. It fills the two settings a tenant zone reads when the
 * application has not: the tenant model, and the page for somebody in no
 * company.
 */
class WireModuleTenantsServiceProvider extends PackageServiceProvider
{
    /**
     * @throws \Exception
     */
    public function configure(Packager $packager): void
    {
        $packager
            ->name('WireModuleTenants')
            ->hasShortName('wire-module-tenants')
            ->registeredPackage(function (): void {
                // The two screens outside any company, as a route group the
                // application places — `Route::wire('tenants')` or a config
                // entry (ADR 0041) — never registered from here.
                RouteGroups::instance()->register(TenantRoutes::class);
                SetupRegistry::instance()->register(RouteTenantScreens::class);

                $this->app->resolving(PluginManager::class, function (PluginManager $manager): void {
                    if (! $manager->has('tenants')) {
                        $manager->register(new TenantsModule);
                    }
                });

                $this->app->booted(function (): void {
                    // Defaults, never overrides: an application that named its
                    // own tenant model or its own no-company page keeps it.
                    if (config('wire-core.tenancy.model') === null) {
                        config()->set('wire-core.tenancy.model', config('wire-module-tenants.model'));
                    }

                    if (config('wire-panels.routes.tenant_entry.view') === null) {
                        config()->set('wire-panels.routes.tenant_entry.view', 'wire-module-tenants::no-tenant');
                    }
                });
            })
            ->bootedPackage(function (): void {
                // Outside the App\Livewire namespace, so the update round trip
                // needs the names registered.
                Livewire::component('wire-module-tenants.register-tenant', RegisterTenant::class);
                Livewire::component('wire-module-tenants.company-profile', CompanyProfile::class);
                Livewire::component('wire-module-tenants.list-members', ListMembers::class);
            })
            ->hasConfig()
            ->hasViews()
            ->hasMigrations()
            ->hasTranslations()
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfig()
                    ->publishMigrations()
                    ->afterInstallation(function (InstallCommand $installer): void {
                        $installer->comment('  ✅ Companies are registered as the `tenants` module');
                        $installer->comment('  • Run: php artisan migrate');
                        $installer->comment('  ↩︎  Give the user model HasTenants + InteractsWithTenants (wire-panels)');
                        $installer->comment("  ↩︎  Route a tenant zone — 'tenant' => 'path' on the panel's entry in wire-core.routes.groups, or Route::wire('panel', tenant: 'path')");
                    });
            })
            ->hasAbout();
    }

    /**
     * @return array<string, string>
     */
    public function aboutData(): array
    {
        return [
            'Tenant model' => Membership::tenantModel(),
            'Registration' => var_export(config('wire-module-tenants.registration'), true),
            'Isolation' => (string) config('wire-core.tenancy.isolation', 'column'),
        ];
    }
}
