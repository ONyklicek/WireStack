<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Router;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WireModuleUsers\Resources\UserResource;
use NyonCode\WirePanels\Routing\RouteAccess;

/**
 * Where "Profile" in the user menu leads — wherever this person is.
 *
 * The page is the signed-in person's own account, so a link to it belongs
 * beside every sign-out. It used to ask for the page without a zone, which in
 * an application with zones answers nothing — there is no `wire.users.profile`,
 * only `admin.wire.users.profile` — and the link vanished from every menu,
 * the administrator's included.
 *
 * So it looks where the person is first, and then wider:
 *
 *   1. the zone of the page being rendered — the profile inside the frame the
 *      person is already in, with that zone's sidebar round it;
 *   2. the page outside any zone;
 *   3. any zone that routes it and lets this person in — the account page
 *      behind an administrators-only zone is no use to anybody else.
 *
 * A zone's `can:` middleware is asked through {@see RouteAccess}, the same
 * question the admin's own entry asks. Nothing reachable is no link, never a
 * link into a 403.
 *
 * Full-page renders only, like {@see Zone::current()}: the user menu is part of
 * the layout, which is drawn once per page, not on a Livewire round trip.
 */
final readonly class ProfileLink
{
    public const PAGE = 'profile';

    public function __construct(
        private ResolvesPageUrls $urls,
        private Router $router,
        private RouteAccess $access,
    ) {}

    public function url(?Authenticatable $user): ?string
    {
        $key = UserResource::key();

        foreach ([Zone::current(), null] as $zone) {
            $url = $this->urls->urlFor($key, self::PAGE, [], $zone);

            if ($url !== null && $this->access->allowsUrl($url, $user)) {
                return $url;
            }
        }

        $name = 'wire.'.$key.'.'.self::PAGE;

        foreach ($this->router->getRoutes()->getRoutesByName() as $routeName => $route) {
            if (str_ends_with($routeName, '.'.$name) && $this->access->allows($route, $user)) {
                return route($routeName);
            }
        }

        return null;
    }
}
