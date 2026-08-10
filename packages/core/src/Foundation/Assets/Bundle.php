<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Assets;

use Closure;
use NyonCode\LaravelPackageToolkit\Support\Asset;

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

            foreach ([$package.'-', 'wire-'] as $prefix) {
                if (str_starts_with($id, $prefix)) {
                    $id = substr($id, strlen($prefix));

                    break;
                }
            }

            return $id === '' ? null : route($package.'.asset', ['asset' => $id]);
        };
    }
}
