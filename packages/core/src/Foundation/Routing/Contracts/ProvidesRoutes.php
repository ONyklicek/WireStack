<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Contracts;

use Illuminate\Routing\Route;
use NyonCode\WireCore\Foundation\Routing\RouteGroups;

/**
 * A package's routes, as one group the application places (ADR 0041).
 *
 * A package declares what it routes and nothing about where: the prefix, the
 * domain, the middleware and `can:` are the group the application puts around
 * it — written out in `routes/web.php` around `Route::wire('key')`, or described
 * in `wire-core.routes.groups` and registered by the framework's one route
 * file. The same implementation serves both, and no provider registers a route.
 *
 * Registered with {@see RouteGroups} from
 * the package's provider:
 *
 *   RouteGroups::instance()->register(TenantRoutes::class);
 */
interface ProvidesRoutes
{
    /** The group's key: what `Route::wire('…')` and a config entry name it by. */
    public static function key(): string;

    /**
     * The group attributes a config entry starts from — `wire-core.routes.defaults`
     * under them, the entry over them. A group whose pages must not be open to
     * anybody says `auth` here, so an entry that names no middleware is guarded
     * rather than public. The route-file path does not read it: there the group
     * around the call is the application's, written out where it can be seen.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * Whether something links to these routes by name — a mail, a screen — so a
     * `name()` group around them is refused rather than allowed to rename them.
     */
    public function fixesNames(): bool;

    /**
     * Register the routes inside the group being built, and hand them back keyed
     * for anything one of them needs on its own.
     *
     * @param  array<string, mixed>  $options  What the call, or the config entry, passed beyond Laravel's own group attributes.
     * @return array<string, Route>
     */
    public function register(array $options): array;
}
