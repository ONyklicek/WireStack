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
        return $this->rendered[$package ?? '*'] ??= new HtmlString(implode("\n", array_map(
            static fn (Asset $asset): string => $asset->toHtml(),
            $this->getScripts($package),
        )));
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
