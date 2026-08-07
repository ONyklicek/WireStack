<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Assets;

use Illuminate\Support\HtmlString;
use NyonCode\WireCore\Exceptions\AssetRegistrationException;
use NyonCode\WireCore\Foundation\Assets\Contracts\Asset;
use NyonCode\WireCore\Foundation\View\FloatingAssets;

/**
 * Canonical owner of the browser assets every wireStack package ships.
 *
 * Each provider declares its bundles once in `bootedPackage()`; the consuming app
 * puts a single `@wireStackScripts` in its layout `<head>` and every controller is
 * in the initial document. That placement is the point, not a convenience: on the
 * cached Back/Forward `wire:navigate` path Livewire does not wait for newly injected
 * head scripts before initialising Alpine (`swapCurrentPageWithNewHtml` keeps its
 * no-op continuation there), so a bundle that arrives with the new page can lose the
 * race. One that was already in the document cannot.
 *
 * Registered as a container singleton, so the registry — and the URL memo each asset
 * holds — spans the whole request. This generalises {@see FloatingAssets}, which
 * memoised exactly one bundle URL for the same reason and now delegates here.
 */
final class AssetManager
{
    /** @var array<string, array<string, Asset>> package => id => asset */
    private array $assets = [];

    /** @var array<string, HtmlString> */
    private array $rendered = [];

    /**
     * Register a package's assets. Re-registering an id replaces it, so an app can
     * point a bundle somewhere else without a second tag being emitted.
     *
     * @param  array<array-key, Asset>  $assets
     * @param  string  $package  short package name, e.g. `wire-core`; also names the asset route
     */
    public function register(array $assets, string $package): void
    {
        foreach ($assets as $asset) {
            $this->assets[$package][$asset->getId()] = $asset->withPackage($package);
        }

        $this->rendered = [];
    }

    /**
     * The script assets to emit into the document, in registration order.
     *
     * Assets marked `loadedOnRequest()` are excluded — their surface fetches them
     * itself. Pass a package to narrow the set to one package's bundles.
     *
     * @return list<Asset>
     */
    public function getScripts(?string $package = null): array
    {
        $groups = $package === null
            ? $this->assets
            : [$this->assets[$package] ?? []];

        $scripts = [];

        foreach ($groups as $group) {
            foreach ($group as $asset) {
                if (! $asset->isLoadedOnRequest()) {
                    $scripts[] = $asset;
                }
            }
        }

        return $scripts;
    }

    /**
     * The `<script>` tags for {@see getScripts()}, memoised per package: the
     * directive renders once per request, and nothing about the output varies
     * within one.
     */
    public function renderScripts(?string $package = null): HtmlString
    {
        if (isset($this->rendered[$package ?? '*'])) {
            return $this->rendered[$package ?? '*'];
        }

        // The tags first, the warning second, and the order is load-bearing: resolving
        // a URL is what runs the mirror, so judging staleness before that would find
        // every copy out of date on the first request after an upgrade — and warn
        // about a state the very next line repairs.
        $tags = array_map(
            static fn (Asset $asset): string => $asset->toHtml(),
            $this->getScripts($package),
        );

        return $this->rendered[$package ?? '*'] = new HtmlString(implode("\n", array_filter([
            $this->stalePublishWarning($package),
            ...$tags,
        ])));
    }

    /**
     * A console warning when `public/vendor` holds a copy of a bundle older than the
     * one the package ships.
     *
     * That copy **is** what the page loads ({@see PublishedAssets} explains why
     * dropping back to the route is not an option), so this is the only thing
     * standing between an app and last release's JavaScript running against this
     * release's markup. It is therefore not gated on `app.debug`: the deployment that
     * gets this wrong is a production one, where a debug-only warning would never be
     * seen. Livewire warns unconditionally for the same reason.
     */
    private function stalePublishWarning(?string $package): ?string
    {
        $stale = array_values(array_unique(array_map(
            static fn (Asset $asset): string => $asset->getPackage().'/'.$asset->getId(),
            array_filter($this->getScripts($package), static fn (Asset $asset): bool => $asset->isStale()),
        )));

        if ($stale === []) {
            return null;
        }

        return '<script>console.warn('.json_encode(
            'wireStack: the published copies of '.implode(', ', $stale).' are older than '
            .'the bundles the packages ship, and are what this page just loaded. Re-run '
            .'`php artisan vendor:publish --tag=laravel-assets --force`.',
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ).')</script>';
    }

    /**
     * Forget every resolved URL and rendered tag, keeping the registry itself.
     *
     * For a long-lived worker (Octane), where these memos would otherwise outlive the
     * deploy that changed the files. A bundle's `?id=` is the mtime of its mirrored
     * copy, so a worker holding last release's query string is a worker whose
     * `data-navigate-track` never fires — the browser keeps a bundle the mirror has
     * already replaced on disk. Re-binding each asset to its package is what clears
     * its URL, the same path {@see register()} takes.
     */
    public function flushUrls(): void
    {
        foreach ($this->assets as $package => $group) {
            foreach ($group as $id => $asset) {
                $this->assets[$package][$id] = $asset->withPackage($package);
            }
        }

        $this->rendered = [];
    }

    /**
     * One registered asset, including an on-request one.
     *
     * @throws AssetRegistrationException when nothing is registered under that id
     */
    public function get(string $package, string $id): Asset
    {
        return $this->assets[$package][$id]
            ?? throw AssetRegistrationException::unknown($package, $id);
    }

    /**
     * The cache-busted URL of one registered asset — for a surface that emits its
     * own tag (a per-surface `@assets` partial, a lazy import).
     *
     * @throws AssetRegistrationException when nothing is registered under that id
     */
    public function url(string $package, string $id): string
    {
        return $this->get($package, $id)->getUrl();
    }
}
