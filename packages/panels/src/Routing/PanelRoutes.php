<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesRoutes;
use NyonCode\WirePanels\Exceptions\TenancyConfigurationException;
use NyonCode\WirePanels\Http\Middleware\IdentifyTenant;

/**
 * The `panel` group: every registered page, in one zone (ADR 0026, 0027, 0041).
 *
 * Placed by the application — `Route::wire('panel', zone: 'admin')` in its route
 * file, `'admin' => ['uses' => 'panel', …]` in `wire-core.routes.groups`, or the
 * older `Route::wireResources()` — and it adds only what is the panel's own:
 *
 * - `zone`: the route-name prefix a zone *is*, so in config it cannot be
 *   forgotten the way a `->name('admin.')` line in a route file can;
 * - `tenant`: `'path'` or `'domain'` puts `{tenant}` into the group and the
 *   middleware that finds it, and registers the zone's bare address beside it
 *   (ADR 0040). Never assumed: without it nothing here knows tenants exist;
 * - `only` / `except`: registered keys; `resource` and `pages`: one class's
 *   pages, all or those named.
 */
final class PanelRoutes implements ProvidesRoutes
{
    public static function key(): string
    {
        return 'panel';
    }

    public function defaults(): array
    {
        return ['middleware' => ['web', 'auth']];
    }

    public function fixesNames(): bool
    {
        // A hand-written zone is a `name('admin.')` group around the call, which
        // is the reason to allow it.
        return false;
    }

    public function register(array $options): array
    {
        $zone = trim((string) ($options['zone'] ?? ''), '.');
        $routes = [];

        $body = function () use ($options, $zone, &$routes): void {
            $routes = $this->withTenant($zone, $options['tenant'] ?? null, fn (): array => $this->pages($options));
        };

        $zone === '' ? $body() : RouteFacade::name($zone.'.')->group($body);

        $keyed = [];

        foreach ($routes as $route) {
            $keyed[(string) ($route->getName() ?? spl_object_id($route))] = $route;
        }

        return $keyed;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, Route>
     */
    private function pages(array $options): array
    {
        if (isset($options['resource'])) {
            return ResourceRoutes::for((string) $options['resource'], array_values((array) ($options['pages'] ?? [])));
        }

        return ResourceRoutes::all(array_values((array) ($options['only'] ?? [])), array_values((array) ($options['except'] ?? [])));
    }

    /**
     * A tenant zone: `{tenant}` after the group's prefix or in front of its
     * domain, the middleware that finds it, and the bare address outside it.
     *
     * @param  callable(): array<int, Route>  $pages
     * @return array<int, Route>
     */
    private function withTenant(string $zone, mixed $mode, callable $pages): array
    {
        if ($mode === null || $mode === false) {
            return $pages();
        }

        $parameter = '{'.IdentifyTenant::PARAMETER.'}';
        $stack = RouteFacade::getGroupStack();
        $group = $stack === [] ? [] : (array) end($stack);
        $prefix = trim((string) ($group['prefix'] ?? ''), '/');
        $domain = $group['domain'] ?? null;

        if ($mode === 'path') {
            $to = trim($prefix.'/'.$parameter, '/');
            $inner = RouteFacade::prefix($parameter);
        } elseif ($mode === 'domain') {
            if (! is_string($domain) || $domain === '') {
                throw TenancyConfigurationException::domainModeWithoutDomain($zone);
            }

            $to = '//'.$parameter.'.'.$domain.($prefix === '' ? '' : '/'.$prefix);
            $inner = RouteFacade::domain($parameter.'.'.$domain);
        } else {
            throw TenancyConfigurationException::unknownMode($zone, is_string($mode) ? $mode : get_debug_type($mode));
        }

        // The bare address, outside the tenant and so outside `wire.tenant`:
        // there is no tenant in it to find.
        $routes = [ResourceRoutes::tenantEntry('', $to)];

        $inner->middleware(IdentifyTenant::ALIAS)->group(function () use ($pages, &$routes): void {
            $routes = [...$routes, ...$pages()];
        });

        return $routes;
    }
}
