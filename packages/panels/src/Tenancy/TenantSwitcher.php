<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Contracts\HasTenants;
use NyonCode\WirePanels\Http\Middleware\IdentifyTenant;

/**
 * What the company switcher in the top bar offers (ADR 0040 §7).
 *
 * Nothing outside a tenant, and nothing for someone with one company — a
 * switcher with a single option is a label pretending to be a control.
 * Otherwise every company this person belongs to, each linked to **the same
 * page** in that company, so switching keeps you where you were.
 *
 * Except on a record's page: record 7 of company A is not a record of
 * company B, and switching there would land on a 404. There the link is the
 * same resource's list, or the zone's own address when even that cannot be
 * built — which is where a nested resource ends up.
 */
final class TenantSwitcher
{
    /**
     * @return array{current: string, tenants: array<int, array{label: string, url: string, current: bool}>}|null
     */
    public function forRequest(Request $request): ?array
    {
        $current = app(CurrentTenant::class)->get();
        $user = $request->user();
        $route = $request->route();

        if ($current === null || ! $user instanceof HasTenants) {
            return null;
        }

        $tenants = collect($user->getTenants())->values();

        if ($tenants->count() < 2) {
            return null;
        }

        $name = (string) $route->getName();
        $parameters = $route->parameters();

        return [
            'current' => $this->label($current),
            'tenants' => $tenants->map(fn (Model $tenant): array => [
                'label' => $this->label($tenant),
                'url' => $this->urlIn($tenant, $name, $parameters),
                'current' => app(CurrentTenant::class)->is($tenant),
            ])->all(),
        ];
    }

    /** What a company is called: `wire-core.tenancy.label`, else its route key. */
    public function label(Model $tenant): string
    {
        $label = $tenant->getAttribute((string) config('wire-core.tenancy.label', 'name'));

        return is_scalar($label) && (string) $label !== '' ? (string) $label : (string) $tenant->getRouteKey();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function urlIn(Model $tenant, string $name, array $parameters): string
    {
        $key = [IdentifyTenant::PARAMETER => $tenant->getRouteKey()];
        $zone = Zone::prefix(Zone::of($name));
        $resource = Zone::keyOf($name);

        $candidates = isset($parameters['record']) || isset($parameters['parent'])
            ? [
                [$zone.'wire.'.$resource.'.index', array_diff_key($parameters, ['record' => true])],
                [$zone.'wire.home', []],
            ]
            : [[$name, $parameters]];

        foreach ($candidates as [$route, $routeParameters]) {
            if ($route === '' || ! Route::has($route)) {
                continue;
            }

            try {
                return route($route, [...$routeParameters, ...$key]);
            } catch (UrlGenerationException) {
                // A list that needs a parent this company does not have — try
                // the next, coarser place.
                continue;
            }
        }

        return route($zone.'wire.tenants');
    }
}
