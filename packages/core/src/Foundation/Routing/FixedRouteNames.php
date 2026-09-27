<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use Illuminate\Support\Facades\Route;
use NyonCode\WireCore\Exceptions\RouteRegistrationException;

/**
 * For a route macro whose routes are linked to by a fixed name.
 *
 * A macro registers its routes inside whatever group the application calls it
 * in, and that is the point: the prefix and the middleware are the
 * application's. The name prefix is the one part that cannot be: an invitation
 * e-mail links to `wire-module-tenants.invitations.accept`, a sign-in screen to
 * `wire-auth.login-code`, and a `Route::name('app.')` around the call would
 * rename them to something nothing asks for — a missing-route error at the
 * first mail, far from the line that caused it. So it is refused where it is
 * written.
 */
final class FixedRouteNames
{
    public static function refuseNamedGroup(string $macro): void
    {
        $group = Route::hasGroupStack() ? (array) last(Route::getGroupStack()) : [];
        $prefix = (string) ($group['as'] ?? '');

        if ($prefix !== '') {
            throw RouteRegistrationException::namedGroup($macro, $prefix);
        }
    }
}
