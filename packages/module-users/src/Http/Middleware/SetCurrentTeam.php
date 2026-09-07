<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NyonCode\WireModuleUsers\Support\Teams;
use Symfony\Component\HttpFoundation\Response;

/**
 * Put the current team on the request before anything reads a permission.
 *
 * With `permission.teams` on, every role and permission lookup is scoped by
 * whatever team id the registrar was last told about — so a request that never
 * tells it sees the *previous* one, which in a queue worker or a long-lived
 * process is somebody else's. Setting it once, early, in one place, is the whole
 * job, and it is a middleware because that is the only place early enough.
 *
 * Registered by the module's provider onto the `web` group, and inert where
 * teams are off: `Teams::currentId()` answers null and the registrar is told
 * null, which is exactly what an installation without teams means.
 */
class SetCurrentTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        Teams::apply(Teams::currentId());

        return $next($request);
    }
}
