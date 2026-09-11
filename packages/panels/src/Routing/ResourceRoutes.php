<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Routing\Contracts\ConfiguresRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Exceptions\ResourceRoutingException;

/**
 * Turns declared pages into routes — and owns nothing else.
 *
 * Reads the {@see Catalog}, not a registry, so what it can route is whatever an
 * application registered: a resource, a dashboard, or the next kind of thing a
 * source holds. Before ADR 0026 it held a `ResourceRegistry`, which is why three
 * of four entries in a real menu could not be linked anywhere — they were
 * registered, and this could not see them.
 *
 * The catalogue deliberately holds no URL shell (ADR 0020 §5), and that stays
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
 * `{prefix}` is the registered key unless {@see ConfiguresRoutes} says
 * otherwise, so the menu key and the URL agree without anyone repeating either.
 *
 * The record parameter is a **key**, not a bound model: the pages resolve their
 * own record (`ResolvesOneRecord`) so a soft-delete scope, a tenant guard or a
 * non-Eloquent source stays the page's decision rather than the router's.
 */
final class ResourceRoutes
{
    /**
     * Where each known page kind sits, relative to the resource. An unknown kind
     * falls back to its own name as the segment.
     *
     * It used to carry a second column saying whether the kind takes a record,
     * and that column was a lie waiting to be told: a page may override the
     * segment with `uri()`, so `'edit' => RoutePage::make(…)->uri('modify')`
     * declared a record in the table and had none in the URL. The URI is the
     * only thing that knows — see {@see takesRecord()}.
     *
     * @var array<string, string>
     */
    private const SHAPES = [
        'index' => '',
        'create' => 'create',
        'view' => '{record}',
        'edit' => '{record}/edit',
    ];

    /**
     * Register everything in the catalogue that declares pages.
     *
     * A class that names no pages is registered and routable by hand, just not
     * routed here — what an internal or nested resource wants, and the same
     * opt-in rule the menu follows. {@see Catalog::implementing()} is that
     * filter, so this method never repeats it.
     *
     * @param  array<int, string>  $only  Keys to include; empty means all.
     * @param  array<int, string>  $except  Keys to skip.
     * @return array<int, Route>
     */
    public static function all(array $only = [], array $except = []): array
    {
        $routes = [];
        $atRoot = null;

        foreach (app(Catalog::class)->implementing(ProvidesPages::class) as $key => $class) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            if (in_array($key, $except, true)) {
                continue;
            }

            $prefix = self::prefixFor($class, $key);

            // A landing page — `routePrefix()` of `ConfiguresRoutes::ROOT` — sits
            // on the group's own path, and only one thing can. Refused rather
            // than resolved, and for a sharper reason than tidiness: Laravel
            // keys its route collection by method and URI, so the second
            // registration *replaces* the first and takes its name with it. The
            // first key then looks routed, answers null, and its menu entry dies
            // without a word.
            if ($prefix === '') {
                if ($atRoot !== null) {
                    throw ResourceRoutingException::twoAtTheRoot($atRoot, $key, self::groupPrefix());
                }

                $atRoot = $key;
            }

            $routes = [...$routes, ...self::for($class)];
        }

        return $routes;
    }

    /**
     * The prefix of the group this is being registered inside, for a message.
     *
     * Read off the router's group stack rather than passed in, because the group
     * is the application's and this deliberately never knew about it — which is
     * the whole point of the macro. Best effort, and used only to name the zone
     * in an error.
     */
    private static function groupPrefix(): string
    {
        $stack = RouteFacade::getFacadeRoot()->getGroupStack();

        return trim(implode('/', array_filter(array_column($stack, 'prefix'))), '/');
    }

    /**
     * Register one registered class's pages.
     *
     * @param  class-string  $resource
     * @return array<int, Route>
     */
    public static function for(string $resource): array
    {
        if (! is_subclass_of($resource, ProvidesPages::class)) {
            throw ResourceRoutingException::declaresNoPages($resource);
        }

        $key = $resource::key();
        $prefix = self::prefixFor($resource, $key);
        $domain = self::domainFor($resource);
        $shared = self::middlewareFor($resource);

        $routes = [];

        foreach ($resource::pages() as $name => $page) {
            $uri = self::uriFor($name, $page);
            $page = $page instanceof RoutePage ? $page : RoutePage::make($page);

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
     * The segment a page sits at, relative to its resource.
     *
     * Public because it is the shape itself, and two things now need it: this
     * class, to register the route, and a record's sub-navigation, to know which
     * of a resource's pages are about *one record* rather than about the list.
     * The alternative was a second copy of the four defaults, which is the copy
     * that stops matching the router the first time someone adds a page kind.
     *
     * @param  string  $name  The key the page was declared under.
     * @param  string|RoutePage  $page  What it was declared as.
     */
    public static function uriFor(string $name, string|RoutePage $page): string
    {
        $declared = $page instanceof RoutePage ? $page->getUri() : null;

        return $declared ?? self::SHAPES[$name] ?? $name;
    }

    /**
     * Whether this page is about one record.
     *
     * Read off the URI rather than off a list of kinds, because the URI is what
     * the router builds a parameter from: a declared `uri('{record}/history')`
     * takes one however the page was named, and a `uri('modify')` does not
     * however familiar its key looks.
     *
     * @param  string|RoutePage  $page  What the page was declared as.
     */
    public static function takesRecord(string $name, string|RoutePage $page): bool
    {
        return str_contains(self::uriFor($name, $page), '{record}');
    }

    /**
     * The URL of a registered class's page, or null when it is not routed here.
     *
     * What a menu needs and what used to be a hand-written map in every
     * application: the workspace keys its entries by the same key, and this
     * turns that key into a link when — and only when — a route exists for it.
     * Reached from `wire-core` through `ResolvesPageUrls`, which is what lets a
     * menu entry and a search result carry a URL without core naming this class.
     *
     * Null covers both ways a key can fail to be a link: nothing routes it, and
     * nothing here can finish the URL. The second is not hypothetical — a
     * resource on a `{tenant}.example.com` domain, or a page at a `uri()` with a
     * segment of its own, has a route that cannot be built without a parameter
     * this caller did not give. Laravel throws `UrlGenerationException` for
     * that, and a menu asking every key what its URL is would take the page down
     * over one entry it would have rendered without an href anyway.
     *
     * `$zone` is the route-name prefix of the mount point to answer for
     * (ADR 0027): `null` is the unzoned name every single-zone application has,
     * `'business'` and `'business.'` both mean the group registered with
     * `Route::name('business.')`. A key routed in another zone and not this one
     * answers null, which is the same answer an unrouted key gives and renders
     * the same way.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
    {
        $name = Zone::prefix($zone)."wire.{$key}.{$page}";

        if (! RouteFacade::has($name)) {
            return null;
        }

        try {
            return route($name, $parameters);
        } catch (UrlGenerationException) {
            return null;
        }
    }

    /**
     * Every key this zone routes, mapped to its URL.
     *
     * Scoped to one mount point, because "every routed key" is a question that
     * only has an answer inside a zone: the same key may be routed in three of
     * them at three URLs, and a menu asks on behalf of the one it is drawn in.
     *
     * @return array<string, string>
     */
    public static function urls(string $page = 'index', ?string $zone = null): array
    {
        $urls = [];

        foreach (array_keys(app(Catalog::class)->all()) as $key) {
            $url = self::urlFor($key, $page, [], $zone);

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
        return is_subclass_of($resource, ConfiguresRoutes::class)
            ? ($resource::routePrefix() ?? $key)
            : $key;
    }

    /**
     * @param  class-string  $resource
     * @return array<int, string>
     */
    private static function middlewareFor(string $resource): array
    {
        return is_subclass_of($resource, ConfiguresRoutes::class)
            ? $resource::routeMiddleware()
            : [];
    }

    /**
     * @param  class-string  $resource
     */
    private static function domainFor(string $resource): ?string
    {
        return is_subclass_of($resource, ConfiguresRoutes::class)
            ? $resource::routeDomain()
            : null;
    }
}
