<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\Contracts\RequiresTenant;
use NyonCode\WireModuleTenants\Pages\CompanyProfile;
use NyonCode\WireModuleTenants\Support\Membership;

/**
 * The company being worked in, as one screen of the tenant zone: its name, its
 * slug, and — for an owner — deleting it. No model of its own: the record is
 * always the current tenant.
 */
final class CompanyResource implements DescribesResource, ProvidesNavigation, ProvidesPages, RequiresTenant
{
    use DescribesRecords;

    public static function key(): string
    {
        return 'company';
    }

    public static function modelClass(): ?string
    {
        return null;
    }

    public static function label(): string
    {
        return __('wire-module-tenants::messages.company');
    }

    public static function pluralLabel(): string
    {
        return self::label();
    }

    public static function pages(): array
    {
        return ['index' => CompanyProfile::class];
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make(fn (): string => __('wire-module-tenants::messages.company'))
            ->icon('outline:building-office-2')
            ->group((string) config('wire-module-tenants.navigation.group', 'company'))
            ->sort(10)
            ->visible(static fn (): bool => Membership::current() !== null);
    }
}
