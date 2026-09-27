<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants;

use NyonCode\WireCore\Core\Modules\Module;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireModuleTenants\Resources\CompanyResource;
use NyonCode\WireModuleTenants\Resources\MemberResource;

/**
 * The company's own screens — its profile and its members — as one area of the
 * tenant zone (ADR 0040 §8). Routed wherever the application routes its
 * tenant zone; outside a company both entries stay out of the menu.
 */
class TenantsModule extends Module
{
    public function getId(): string
    {
        return 'tenants';
    }

    public function resources(): array
    {
        return [CompanyResource::class, MemberResource::class];
    }

    public function navigation(): ?NavigationGroup
    {
        // A closure for the label, for the reason SettingsModule gives: this
        // runs before the module's translations are registered.
        return NavigationGroup::make((string) config('wire-module-tenants.navigation.group', 'company'))
            ->icon('outline:building-office-2')
            ->sort((int) config('wire-module-tenants.navigation.sort', 80))
            ->label(fn (): string => __('wire-module-tenants::messages.company'));
    }
}
