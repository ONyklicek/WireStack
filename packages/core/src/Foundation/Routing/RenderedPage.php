<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

/**
 * The route of the page being worked on, kept for the requests that are not it.
 *
 * A Livewire round trip is a request to `livewire/update`, so
 * `Route::currentRouteName()` answers that and every zone-derived answer used
 * to be null on the second render (ADR 0027) — a menu, a breadcrumb or an
 * `X::url()` right on the first paint and wrong on every one after. The routing
 * package remembers the page's route here, as Livewire persistent middleware
 * re-runs against the page's original request, and {@see Zone} reads it when the
 * current route is not a page.
 *
 * Scoped, so each request and job starts empty, and empty where no routing
 * package remembers anything — which is exactly the old behaviour.
 */
final class RenderedPage
{
    private ?string $routeName = null;

    public function remember(?string $routeName): void
    {
        $this->routeName = $routeName;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }
}
