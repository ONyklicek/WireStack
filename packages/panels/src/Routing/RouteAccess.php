<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Whether a route would let this person in — asked before sending them there.
 *
 * A link into a 403 is worse than no link, and the panel's pages say who may
 * open them with Laravel's own `can:` middleware (`RoutePage::permission()`,
 * a zone's group). So a place that picks a destination — the admin's entry
 * ({@see PanelEntry}), a menu item that could point into more than one zone —
 * asks the route's `can:` middleware of the Gate first.
 *
 * Only `can:` is asked. It is the one middleware the panel uses for
 * authorization; `auth` and `verified` already passed on the way to whatever is
 * asking, and anything else is the application's to answer.
 */
final readonly class RouteAccess
{
    public function __construct(private Router $router, private Gate $gate) {}

    /** The route's `can:` middleware, answered for this person. */
    public function allows(Route $route, ?Authenticatable $user): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'can:')) {
                continue;
            }

            [$ability, $arguments] = array_pad(explode(',', substr($middleware, 4), 2), 2, null);

            if (! $this->gate->forUser($user)->allows($ability, $arguments === null ? [] : explode(',', $arguments))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The same question about a URL. One this application does not route — an
     * external link in a menu — has nothing to ask, and is let through.
     */
    public function allowsUrl(string $url, ?Authenticatable $user): bool
    {
        try {
            $route = $this->router->getRoutes()->match(Request::create($url, 'GET'));
        } catch (Throwable) {
            return true;
        }

        return $this->allows($route, $user);
    }
}
