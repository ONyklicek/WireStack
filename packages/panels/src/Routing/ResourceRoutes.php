<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\ResourceRegistry;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;
use NyonCode\WirePanels\Resources\Contracts\ConfiguresResourceRoutes;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourcePages;

/**
 * Turns a resource's declared pages into routes — and owns nothing else.
 *
 * The registry deliberately holds no URL shell (ADR 0020 §5), and that stays
 * true: this does not route anything by itself, it registers what an application
 * asks it to, inside whatever group the application put it in. A
 * `Route::domain(…)->middleware(…)->prefix(…)->group()` wrapping the call still
 * applies to every route below, because these are ordinary Laravel routes.
 *
 * What it removes is the part that was pure repetition — four `Route::get()`
 * lines per resource and a hand-written key→page map beside them, which is where
 * a mismatch between the menu's key and the URL used to hide.
 *
 * **The URL shape is the convention, and it lives here:**
 *
 *   GET  {prefix}                    → index    named wire.{key}.index
 *   GET  {prefix}/create             → create   named wire.{key}.create
 *   GET  {prefix}/{record}           → view     named wire.{key}.view
 *   GET  {prefix}/{record}/edit      → edit     named wire.{key}.edit
 *   GET  {prefix}/{anything-else}    → that page, at its own name
 *
 * `{prefix}` is the resource key unless {@see ConfiguresResourceRoutes} says
 * otherwise, so the menu key and the URL agree without anyone repeating either.
 *
 * The record parameter is a **key**, not a bound model: the pages resolve their
 * own record (`ResolvesOneRecord`) so a soft-delete scope, a tenant guard or a
 * non-Eloquent source stays the page's decision rather than the router's.
 */
final class ResourceRoutes
{
    /**
     * Where each known page kind sits, relative to the resource, and whether it
     * takes a record. An unknown kind falls back to its own name as the segment.
     *
     * @var array<string, array{uri: string, record: bool}>
     */
    private const SHAPES = [
        'index' => ['uri' => '', 'record' => false],
        'create' => ['uri' => 'create', 'record' => false],
        'view' => ['uri' => '{record}', 'record' => true],
        'edit' => ['uri' => '{record}/edit', 'record' => true],
    ];

    /**
     * Register every registered resource that declares pages.
     *
     * @param  array<int, string>  $only  Resource keys to include; empty means all.
     * @param  array<int, string>  $except  Resource keys to skip.
     * @return array<int, Route>
     */
    public static function all(array $only = [], array $except = []): array
    {
        $routes = [];

        foreach (app(ResourceRegistry::class)->all() as $key => $resource) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            if (in_array($key, $except, true)) {
                continue;
            }

            // A resource that names no pages is registered and routable by hand,
            // just not routed here — what an internal or nested resource wants,
            // and the same opt-in rule the menu follows.
            if (! is_subclass_of($resource, ProvidesResourcePages::class)) {
                continue;
            }

            $routes = [...$routes, ...self::for($resource)];
        }

        return $routes;
    }

    /**
     * Register one resource's pages.
     *
     * @param  class-string<DescribesResource>  $resource
     * @return array<int, Route>
     */
    public static function for(string $resource): array
    {
        if (! is_subclass_of($resource, ProvidesResourcePages::class)) {
            throw ResourceRoutingException::declaresNoPages($resource);
        }

        $key = $resource::key();
        $prefix = self::prefixFor($resource, $key);
        $domain = self::domainFor($resource);
        $shared = self::middlewareFor($resource);

        $routes = [];

        foreach ($resource::pages() as $name => $page) {
            $page = $page instanceof RoutePage ? $page : RoutePage::make($page);
            $shape = self::SHAPES[$name] ?? ['uri' => $name, 'record' => false];
            $uri = $page->getUri() ?? $shape['uri'];

            // Merged, not chained: RouteRegistrar::middleware() *replaces* what
            // it was given, so setting the resource's and then the page's left
            // only the page's — and a resource-wide `auth` silently disappeared
            // from every page that added one of its own.
            $registrar = RouteFacade::middleware([...$shared, ...$page->getMiddleware()]);

            if ($domain !== null) {
                $registrar = $registrar->domain($domain);
            }

            $routes[] = $registrar
                ->get(trim($prefix.'/'.$uri, '/'), $page->component)
                ->name("wire.{$key}.{$name}");
        }

        return $routes;
    }

    /**
     * The URL of a resource's page, or null when it is not routed here.
     *
     * What a menu needs and what used to be a hand-written map in every
     * application: the workspace keys its entries by resource key, and this
     * turns that key into a link when — and only when — a route exists for it.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function urlFor(string $key, string $page = 'index', array $parameters = []): ?string
    {
        $name = "wire.{$key}.{$page}";

        return RouteFacade::has($name) ? route($name, $parameters) : null;
    }

    /**
     * Every routed resource key mapped to its index URL.
     *
     * @return array<string, string>
     */
    public static function urls(string $page = 'index'): array
    {
        $urls = [];

        foreach (app(ResourceRegistry::class)->all() as $key => $resource) {
            $url = self::urlFor($key, $page);

            if ($url !== null) {
                $urls[$key] = $url;
            }
        }

        return $urls;
    }

    /**
     * @param  class-string  $resource
     */
    private static function prefixFor(string $resource, string $key): string
    {
        return is_subclass_of($resource, ConfiguresResourceRoutes::class)
            ? ($resource::routePrefix() ?? $key)
            : $key;
    }

    /**
     * @param  class-string  $resource
     * @return array<int, string>
     */
    private static function middlewareFor(string $resource): array
    {
        return is_subclass_of($resource, ConfiguresResourceRoutes::class)
            ? $resource::routeMiddleware()
            : [];
    }

    /**
     * @param  class-string  $resource
     */
    private static function domainFor(string $resource): ?string
    {
        return is_subclass_of($resource, ConfiguresResourceRoutes::class)
            ? $resource::routeDomain()
            : null;
    }
}
