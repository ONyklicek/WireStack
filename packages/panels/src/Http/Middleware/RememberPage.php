<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use NyonCode\WireCore\Foundation\Routing\RenderedPage;
use NyonCode\WireCore\Foundation\Routing\Zone;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers which page route this request is working on.
 *
 * On every page route `Route::wireResources()` registers, and registered as
 * Livewire persistent middleware — so on a round trip, which is a request to
 * `livewire/update`, Livewire rebuilds the page's original request and this
 * runs again against it. {@see Zone} then answers the page's zone, key and kind
 * on the second render as it did on the first, and `X::url()`, breadcrumbs and
 * the menu stop losing their zone after the first paint (ADR 0027's trap).
 */
final class RememberPage
{
    public function handle(Request $request, Closure $next): Response
    {
        app(RenderedPage::class)->remember($request->route()->getName());

        return $next($request);
    }
}
