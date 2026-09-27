<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WirePanels\Contracts\HasTenants;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * `wire.tenant` — the tenant in the URL, found, checked and entered.
 *
 * Four steps, in this order (ADR 0040 §4):
 *
 * 1. **Find** it by the tenant model's route key — a slug when the model says
 *    so — without its global scopes: the tenant is not a tenant-owned row.
 * 2. **Authorize** through {@see HasTenants::canAccessTenant()}. A stranger and
 *    an unknown slug get the same **404**: a 403 would tell whoever typed
 *    `/app/globex/` that globex exists.
 * 3. **Enter** it, so every tenant-owned query follows ({@see CurrentTenant}).
 * 4. **Carry it**: `URL::defaults()` fills `{tenant}` into every URL built
 *    after this — `urlFor()`, `X::url()`, the menu, search results, redirects —
 *    and the parameter is taken off the route so no page's `mount()` sees it.
 *
 * A Livewire round trip is not a request to this route, so the service
 * provider registers this as persistent middleware: Livewire rebuilds a request
 * from the page's original path and runs it again, `{tenant}` and all.
 */
final class IdentifyTenant
{
    public const ALIAS = 'wire.tenant';

    public const PARAMETER = 'tenant';

    public function handle(Request $request, Closure $next): Response
    {
        // Route middleware, so there is always a route: the question is only
        // whether it carries the parameter this reads.
        $route = $request->route();

        if (! $route->hasParameter(self::PARAMETER)) {
            throw TenancyConfigurationException::noParameter($route->getName() ?? $route->uri());
        }

        $user = $request->user();

        if ($user !== null && ! $user instanceof HasTenants) {
            throw TenancyConfigurationException::userCannotHaveTenants($user::class);
        }

        $tenant = $this->find($route->parameter(self::PARAMETER));

        abort_if($user === null || $tenant === null || ! $user->canAccessTenant($tenant), 404);

        app(CurrentTenant::class)->enter($tenant);

        URL::defaults([self::PARAMETER => $tenant->getRouteKey()]);
        $route->forgetParameter(self::PARAMETER);

        return $next($request);
    }

    private function find(mixed $value): ?Model
    {
        $model = config('wire-core.tenancy.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw TenancyConfigurationException::noTenantModel();
        }

        // Bound already, by an explicit route binding of the application's.
        if ($value instanceof $model) {
            return $value;
        }

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $instance = new $model;

        return $model::query()->withoutGlobalScopes()->where($instance->getRouteKeyName(), $value)->first();
    }
}
