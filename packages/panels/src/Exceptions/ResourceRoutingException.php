<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use RuntimeException;

/**
 * A route registration that cannot mean what it says.
 *
 * Two shapes: a resource asked to route pages it does not declare, and pages
 * asked to be registered twice.
 *
 * Loud rather than silent, and only on the explicit path: `Route::wireResource()`
 * names one resource, so being handed one with no pages is a mistake worth
 * saying out loud. `Route::wireResources()` skips such a resource instead —
 * there, having none is the ordinary way an internal resource stays unrouted.
 */
class ResourceRoutingException extends RuntimeException
{
    /**
     * @param  class-string  $resource
     */
    public static function declaresNoPages(string $resource): self
    {
        return new self(
            "[{$resource}] cannot be routed: it does not implement ".
            ProvidesPages::class.', so nothing says which pages render it. '.
            'Declare pages() on it, or register its routes by hand.'
        );
    }

    /**
     * Both registration paths were used at once (ADR 0026 §5).
     *
     * Refused rather than resolved, for the reason a duplicate registry key is:
     * every page would be registered twice under one route name, the second
     * quietly winning the name lookup, and the fix is deleting one line — which
     * nobody can do while nothing says so.
     */
    /**
     * Two registered things claimed the root of one group (ADR 0027).
     *
     * Refused because Laravel's route collection is keyed by method and URI, so
     * the second registration does not shadow the first — it **replaces** it,
     * name and all. `urlFor()` then answers null for a key that looks routed,
     * the menu entry goes quietly dead, and nothing anywhere says why. Measured,
     * not feared: `route('…ls-overview.index')` threw RouteNotFoundException for
     * a route the same call had just registered.
     */
    public static function twoAtTheRoot(string $existing, string $incoming, string $prefix): self
    {
        $where = $prefix === '' ? 'this route group' : "the `{$prefix}` group";

        return new self(
            "[{$incoming}] and [{$existing}] both route their index page at the root of {$where}. ".
            'Laravel keys routes by URI, so the second would replace the first and take its route '.
            'name with it — a menu entry that silently stops linking anywhere. Give one of them a '.
            'routePrefix() of its own, or keep only one in this zone with only()/except().'
        );
    }

    public static function alreadyRegisteredFromConfig(): self
    {
        return new self(
            'Resource pages were already registered from `wire-panels.routes`, so calling '.
            'Route::wireResources() registers every one of them a second time under the same '.
            'route name. Use one or the other: set `wire-panels.routes.enabled` to false to '.
            'keep the call in your route file, or delete the call to keep the config.'
        );
    }
}
