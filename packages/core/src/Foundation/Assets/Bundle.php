<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Assets;

use Closure;
use Illuminate\Support\Facades\Route;
use NyonCode\LaravelPackageToolkit\Support\Asset;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * How a wireStack package declares a browser bundle to the toolkit's renderer.
 *
 * Every bundle in the stack is the same shape — an esbuild IIFE holding one or more
 * Alpine registrars — so the three things that shape implies belong to one owner
 * rather than to four providers repeating them. Delivery, cache busting and the tag
 * itself are the toolkit's (`PackageAssets`, `PublishedAssets`); this is only the
 * declaration, and the fallback URL the toolkit cannot know.
 *
 * @see Asset the value object this builds
 */
final class Bundle
{
    /**
     * What every wireStack bundle's tag carries beyond the toolkit's own defaults.
     *
     * `data-navigate-once` matches what the stack emitted before the toolkit owned
     * the tag. `defer => null` *removes* a default: `Asset::classic()` adds `defer`,
     * and while nothing is known to break under it — the registration idiom is
     * order-independent by construction, `if (window.Alpine) register()` with
     * `alpine:init` behind it — nothing had watched it in a browser either. A
     * structural change should not smuggle a timing change in with it. Turning it on
     * is a separate decision, behind `npm run verify:drivers`.
     *
     * `data-navigate-track="reload"` is the toolkit's default and is kept.
     *
     * @var array<string, string|bool|null>
     */
    public const ATTRIBUTES = [
        'data-navigate-once' => true,
        'defer' => null,
    ];

    /**
     * Declare one shipped bundle.
     *
     * `classic()` is not optional for anything in this repo: every bundle is built
     * with `--format=iife`, and the toolkit renders a `.js` entry as `type="module"`
     * unless told otherwise. A module is deferred and its top-level declarations
     * never reach `window` — which is exactly how the registration idiom works — so
     * a bundle emitted as a module registers nothing, and every `x-data` referencing
     * its factory fails with no error at the point of the mistake.
     *
     * @param  string  $file  the built file, relative to the package's `dist/`
     */
    public static function make(string $file): Asset
    {
        return Asset::make($file)->classic()->attributes(self::ATTRIBUTES);
    }

    /**
     * Prefixes a package's built bundle may carry, most specific first.
     *
     * One list for both directions of the same mapping: {@see servedByRoute()}
     * strips a prefix off a filename to get the route's id, {@see serve()} puts
     * one back on to find the file. Split across two owners they drift, and the
     * drift is invisible — the route 404s for a bundle that is right there.
     *
     * The stack's own three shapes are `wire-core-dropdown.js` (package prefix),
     * `wire-sortable.js` (the package is the bundle) and, for a package outside
     * this repo, whatever it chose to call the file — hence the empty prefix
     * last. It is inert on the stripping side, where an earlier prefix always
     * matches first, and on the serving side it is what makes the pair total:
     * every name `servedByRoute()` can turn into an id, `serve()` can turn back.
     *
     * @return list<string>
     */
    private static function prefixes(string $package): array
    {
        return [$package.'-', 'wire-', ''];
    }

    /**
     * Register the route that answers {@see servedByRoute()}.
     *
     * The four providers each wrote this out, and what they were repeating was
     * not the route — it was the contract behind it: `basename()` and the id
     * pattern are the only things between the URL and the filesystem, the 404
     * is what keeps a missing bundle from surfacing as a 500, and the immutable
     * cache header is what stops a fallback the renderer reaches on every page
     * from costing a request every page. Three of those four copies had no test
     * over any of it.
     *
     * @param  string  $package  short package name, e.g. `wire-core`
     * @param  string  $assetsPath  absolute path to the package's `dist/`
     */
    public static function serve(string $package, string $assetsPath): void
    {
        Route::get("/{$package}/assets/{asset}.js", static function (string $asset) use ($package, $assetsPath): BinaryFileResponse {
            $file = self::fileFor($package, $assetsPath, $asset);

            abort_unless($file !== null, 404);

            return response()
                ->file($file, ['Content-Type' => 'application/javascript; charset=utf-8'])
                ->setPublic()
                ->setMaxAge(31536000);
        })
            ->where('asset', '[A-Za-z0-9_-]+')
            ->name($package.'.asset');
    }

    /**
     * The built file a route id names, or null when the package ships no such bundle.
     *
     * The id reaches here already unable to carry a path. Every copy of this
     * route ran the segment through `basename()` first, and measuring it says
     * that call could never fire: Symfony compiles a route parameter as
     * `[^/]++` — possessive, so it cannot give characters back — and the literal
     * `.js` after it means a segment holding a slash *or a dot* fails to match
     * the route at all. `../../composer.js`, encoded or not, is a 404 before any
     * closure runs. What the `where()` on the route does add is the alphabet:
     * without it `a~b`, `a b` and `á` are ids the lookup would go to disk with.
     */
    private static function fileFor(string $package, string $assetsPath, string $id): ?string
    {
        foreach (self::prefixes($package) as $prefix) {
            $file = $assetsPath.'/'.$prefix.$id.'.js';

            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * The package's own asset route, as the toolkit's `hasAssetFallback()` resolver.
     *
     * Reached only where `public/` cannot be written and nothing was published —
     * ADR 0024 chose static files first and a route behind them, and without this the
     * renderer drops the tag and says nothing. The route streams the file straight out
     * of the package, so it needs no `?id=`: nothing about it is cached past the
     * response headers it sets itself.
     *
     * Each package names its route `{short-name}.asset` and takes an id rather than a
     * filename — `dropdown`, not `wire-core-dropdown.js` — so the id is read back off
     * the file the entry was declared with. Stripping the package's own prefix covers
     * `wire-core-dropdown.js` → `dropdown`; falling back to `wire-` covers
     * `wire-sortable.js` → `sortable`, whose route builds `wire-{id}.js`.
     *
     * @param  string  $package  short package name, e.g. `wire-core`
     * @return Closure(string, string): ?string
     */
    public static function servedByRoute(string $package): Closure
    {
        return static function (string $file) use ($package): ?string {
            $id = basename($file, '.js');

            foreach (self::prefixes($package) as $prefix) {
                if (str_starts_with($id, $prefix)) {
                    $id = substr($id, strlen($prefix));

                    break;
                }
            }

            return $id === '' ? null : route($package.'.asset', ['asset' => $id]);
        };
    }
}
