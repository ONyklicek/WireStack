<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use RuntimeException;

/**
 * A route registration that cannot mean what it says.
 *
 * Three shapes: a resource asked to route pages it does not declare, pages
 * asked to be registered twice, and a page and another route on one path.
 *
 * Loud rather than silent, and only on the explicit path: `Route::wireResource()`
 * names one resource, so being handed one with no pages is a mistake worth
 * saying out loud. `Route::wireResources()` skips such a resource instead —
 * there, having none is the ordinary way an internal resource stays unrouted.
 */
final class ResourceRoutingException extends RuntimeException implements WireException
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

    /**
     * A page would have taken a path another route already answers.
     *
     * The same failure {@see twoAtTheRoot()} refuses, at any path and against
     * anybody's route: the page would replace that route and take its name, so
     * whatever linked to it — a hand-written route the application still
     * names, or another resource's page — would stop without a word.
     */
    public static function pathTaken(string $key, string $page, string $uri, ?string $holder): self
    {
        return new self(
            "[{$key}] routes its [{$page}] page at `{$uri}`, where ".self::describe($holder).' already answers. '.
            'Laravel keys routes by method and URI, so registering it would replace that route and take '.
            'its name with it. Give the resource a routePrefix() of its own, leave it out of this group '.
            'with only()/except(), or move the other route.'
        );
    }

    /**
     * A route registered after a page replaced it.
     *
     * Found once every route is loaded, because the route that did it came
     * later in the application's route file than the page. Refused like the
     * other order is: the page's route name is gone, so its menu entry renders
     * and links nowhere.
     */
    public static function pathTakenLater(string $key, string $page, string $uri, ?string $holder): self
    {
        return new self(
            "[{$key}] routed its [{$page}] page at `{$uri}`, and ".self::describe($holder).' registered '.
            'there afterwards replaced it. Laravel keys routes by method and URI, so the page lost its '.
            'route name and its menu entry links nowhere. Move the other route, or give the resource a '.
            'routePrefix() of its own.'
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
    public static function alreadyRegisteredFromConfig(): self
    {
        return new self(
            'Resource pages were already registered from `wire-panels.routes`, so calling '.
            'Route::wireResources() registers every one of them a second time under the same '.
            'route name. Use one or the other: set `wire-panels.routes.enabled` to false to '.
            'keep the call in your route file, or delete the call to keep the config.'
        );
    }

    private static function describe(?string $route): string
    {
        return $route === null ? 'an unnamed route' : "the route [{$route}]";
    }

    /** A nested resource whose parent is itself nested. */
    public static function nestedTooDeep(string $resource, string $parent): self
    {
        return new self(
            "[{$resource}] is nested under [{$parent}], which is nested itself. A nested resource's ".
            'pages carry one {parent} in their URL, so nesting goes one level deep: nest it under '.
            "the top-level resource instead, or give [{$resource}] pages of its own."
        );
    }
}
