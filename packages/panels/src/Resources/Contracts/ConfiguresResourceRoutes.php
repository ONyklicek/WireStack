<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Resources\Contracts;

/**
 * A resource that shapes its own routes.
 *
 * Separate from {@see ProvidesResourcePages} and optional, because naming pages
 * and configuring how they are reached are different decisions with different
 * lifetimes: most resources name pages and want the defaults, and the ones that
 * differ usually differ in one of these three.
 *
 * Everything here is *additional* to the route group the application registered
 * the resources inside. A `Route::domain(…)->middleware(…)->prefix(…)->group()`
 * still applies to every route, exactly as it does for any Laravel route — this
 * is for what belongs to one resource rather than to the whole group.
 */
interface ConfiguresResourceRoutes
{
    /**
     * Middleware every page of this resource carries.
     *
     * Use `'can:ability'` for authorization: Laravel's own middleware answers it
     * through Gate, which is what `HasAuthorization` uses everywhere else, so
     * spatie/laravel-permission and permission-extended keep working unchanged.
     *
     * @return array<int, string>
     */
    public static function routeMiddleware(): array;

    /**
     * A domain for this resource's pages, or null to stay on the group's.
     *
     * The tenant-per-domain case: `'{tenant}.example.com'`. The parameter
     * reaches the application's `TenantResolver` like any other route
     * parameter — tenancy stays where it is, this only says where the URL is.
     */
    public static function routeDomain(): ?string;

    /**
     * The URI segment these pages live under, or null for the resource key.
     *
     * A resource keyed `invoices` routes at `invoices/…` unless it says
     * otherwise, so the menu key and the URL agree by default.
     */
    public static function routePrefix(): ?string;
}
