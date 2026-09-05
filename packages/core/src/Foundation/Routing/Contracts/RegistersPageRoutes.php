<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Contracts;

/**
 * Whoever turns the catalogue into routes, called at the one moment it is safe.
 *
 * The ordering problem this exists to remove (ADR 0026 §5): auto-registration
 * reads the catalogue, and the catalogue is filled while `wire-core` boots.
 * Provider boot order across packages is `PackageManifest` order, so a routing
 * package that registered its own routes in its own `boot()` might run first and
 * register nothing — which looks exactly like a resource that declares no pages,
 * and is therefore invisible.
 *
 * So the direction is inverted, as it is for {@see ResolvesPageUrls}: core does
 * not know how to route and never learns, but it does know when the registries
 * are full, and it calls this then. Bound only by a package that routes; an
 * application without one resolves nothing.
 *
 * Called during `boot()`, never from a `booted()` callback — Laravel installs a
 * cached route collection from one of those, and a route registered after that
 * either vanishes or is applied twice depending on callback order.
 */
interface RegistersPageRoutes
{
    /**
     * Register whatever the application asked to be registered automatically.
     *
     * A no-op when it was not asked, which is the default: this being bound
     * means a package can route, not that it should.
     */
    public function register(): void;
}
