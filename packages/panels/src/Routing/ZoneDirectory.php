<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Contracts\HasPreferredZone;

/**
 * The zones this application routes, and which of them a person lands in.
 *
 * **Read off the routes, not off the config.** A zone is a route-name prefix
 * (ADR 0027): `routes.zones` in config makes one, and so does a hand-written
 * `Route::name('obchod.')->group(…)` around `Route::wireResources()`. Both end up
 * as `{zone}.wire.*` names, so the router is the one list that knows every zone
 * however it was declared.
 *
 * **A zone's address is its shortest route.** That is `{zone}.wire.home` — the
 * panel's entry at the group's root — or, where a landing page owns the root,
 * that page. Either way it is the URL a person types to reach the zone, and the
 * route whose `can:` middleware says whether they may ({@see RouteAccess}).
 *
 * **Landing is decided in one order**: the person's own preference
 * ({@see HasPreferredZone}), then `wire-panels.routes.zone_entry.primary`, then
 * the only zone they can reach. Each step counts only when the person may enter
 * the zone it names, so a stale preference or a primary zone somebody lacks
 * the permission for falls through instead of ending in a 403.
 */
final readonly class ZoneDirectory
{
    public function __construct(
        private Router $router,
        private RouteAccess $access,
        private Repository $config,
    ) {}

    /**
     * Every routed zone and the route that is its address, in the order the
     * zones were registered.
     *
     * @return array<string, Route>
     */
    public function all(): array
    {
        $zones = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $zone = Zone::of($route->getName());

            if ($zone === null || ! in_array('GET', $route->methods(), true) || $this->takesParameters($route)) {
                continue;
            }

            $zone = rtrim($zone, '.');

            if (! isset($zones[$zone]) || strlen($route->uri()) < strlen($zones[$zone]->uri())) {
                $zones[$zone] = $route;
            }
        }

        return $zones;
    }

    /**
     * The zones this person may enter, each with its URL.
     *
     * @return array<string, string>
     */
    public function reachableBy(?Authenticatable $user): array
    {
        $reachable = [];

        foreach ($this->all() as $zone => $route) {
            if ($this->access->allows($route, $user)) {
                $reachable[$zone] = route((string) $route->getName());
            }
        }

        return $reachable;
    }

    /** The zone this person lands in without choosing, or null when it is theirs to choose. */
    public function landingFor(?Authenticatable $user): ?string
    {
        $reachable = $this->reachableBy($user);

        $candidates = [
            $user instanceof HasPreferredZone ? $user->preferredZone() : null,
            $this->config->get('wire-panels.routes.zone_entry.primary'),
        ];

        foreach ($candidates as $zone) {
            if (is_string($zone) && isset($reachable[$zone])) {
                return $zone;
            }
        }

        return count($reachable) === 1 ? (string) array_key_first($reachable) : null;
    }

    private function takesParameters(Route $route): bool
    {
        return preg_match('/\{[^}?]+\}/', $route->uri()) === 1;
    }
}
