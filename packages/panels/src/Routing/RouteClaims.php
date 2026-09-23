<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollectionInterface;
use Illuminate\Routing\Router;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;

/**
 * The paths this package took, and the promise that it took them from nobody.
 *
 * Laravel keys its route collection by method and URI, so registering a route
 * at a path something already answers does not shadow it — it **replaces** it,
 * name and all, and says nothing. Every route this package registers can land
 * on such a path, and in either order: this one second, over a route of the
 * application's, or first, under one the application adds further down its
 * route file. Both are settled here, the same way whichever came first:
 *
 * - **A page** has to be where its menu entry points, so neither side may take
 *   its path: refused when it would replace a route, and again once every
 *   route is loaded if a later route replaced it.
 * - **The entry** at a group's root (`wire.home`) is a convenience, so it
 *   yields: not registered over a route that is there, and a route registered
 *   over it later is the application claiming its root — which is allowed.
 *
 * Remembered per route collection, because that is what a claim is about:
 * `route:cache` installs a new collection over the one these were registered
 * in, and a claim on the old one says nothing about the new.
 */
final class RouteClaims
{
    private const PAGE = 'page';

    private const ENTRY = 'entry';

    /**
     * Keyed the way Laravel keys the collection — domain, then URI.
     *
     * @var array<string, array{route: Route, kind: string, key: string, page: string, in: RouteCollectionInterface}>
     */
    private array $claims = [];

    public function __construct(private readonly Router $router) {}

    /**
     * Register a page, refusing to take a path another route holds.
     *
     * The route is registered first and the path read back off it, because only
     * the router knows the whole URI and domain once the application's groups
     * are applied; what it held before is taken from a snapshot of the
     * collection made just before.
     *
     * @param  Closure(): Route  $register
     */
    public function page(string $key, string $page, Closure $register): Route
    {
        $before = $this->router->getRoutes()->get('GET');
        $route = $register();
        $path = $this->pathOf($route);
        $held = $before[$path] ?? null;

        if ($held !== null && ! $this->mayReplace($held, $route)) {
            throw ResourceRoutingException::pathTaken($key, $page, $route->uri(), $held->getName());
        }

        $this->claim($path, $route, self::PAGE, $key, $page);

        return $route;
    }

    /**
     * Register the entry at a group's root, unless something already answers.
     *
     * @param  Closure(): Route  $register
     */
    public function entry(string $uri, ?string $domain, Closure $register): ?Route
    {
        if (isset($this->router->getRoutes()->get('GET')[$domain.$uri])) {
            return null;
        }

        $route = $register();
        $this->claim($this->pathOf($route), $route, self::ENTRY, '', '');

        return $route;
    }

    /**
     * Refuse a page some later route replaced.
     *
     * Run once every route is loaded — see WirePanelsServiceProvider — since
     * the route that does it is registered after this package is done. A claim
     * on a collection the router no longer holds is skipped rather than failed:
     * the routes were swapped wholesale, and nothing here was taken.
     */
    public function verify(): void
    {
        $routes = $this->router->getRoutes();
        $current = $routes->get('GET');

        foreach ($this->claims as $path => $claim) {
            if ($claim['kind'] !== self::PAGE || $claim['in'] !== $routes) {
                continue;
            }

            $holder = $current[$path] ?? null;

            if ($holder !== $claim['route']) {
                throw ResourceRoutingException::pathTakenLater($claim['key'], $claim['page'], $claim['route']->uri(), $holder?->getName());
            }
        }
    }

    /**
     * Whether a route of this package's own may stand where `$held` stood.
     *
     * Two cases, both this package's: the entry yields to a page — a landing
     * page routed by a later call in the same group — and a page may be routed
     * twice over itself, which changes nothing a menu can see. Anything else is
     * somebody's route, and replacing it is the silent failure this class is for.
     */
    private function mayReplace(Route $held, Route $incoming): bool
    {
        $claim = $this->claims[$this->pathOf($held)] ?? null;

        if ($claim === null || $claim['route'] !== $held) {
            return false;
        }

        return $claim['kind'] === self::ENTRY || $held->getName() === $incoming->getName();
    }

    private function claim(string $path, Route $route, string $kind, string $key, string $page): void
    {
        $this->claims[$path] = ['route' => $route, 'kind' => $kind, 'key' => $key, 'page' => $page, 'in' => $this->router->getRoutes()];
    }

    private function pathOf(Route $route): string
    {
        return $route->getDomain().$route->uri();
    }
}
