<?php

declare(strict_types=1);

namespace Workbench\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `bar` zone's one difference from `admin`: the menu is a bar under the
 * header rather than a column, so verify-topnav drives the same menu in the
 * other shape. An application sets `wire-admin.layout.navigation` once.
 */
class UseBarNavigation
{
    public function handle(Request $request, Closure $next): Response
    {
        config()->set('wire-admin.layout.navigation', 'top');

        return $next($request);
    }
}
