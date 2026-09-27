<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Routing\WireRoutes;

/*
 * The framework's one route file (ADR 0041).
 *
 * It registers the groups `wire-core.routes.groups` describes — nothing when
 * that list is empty, which is the default. The other way to place a group is
 * `Route::wire('…')` in the application's own route file; both go through
 * WireRoutes, so a group means the same whichever placed it.
 *
 * Loaded by WireCoreServiceProvider at the end of its boot, the first moment
 * every package's resources and groups are registered.
 */
app(WireRoutes::class)->registerConfigured();
