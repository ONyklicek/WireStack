<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Assets;

use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
use NyonCode\WireCore\Exceptions\AssetRegistrationException;
use NyonCode\WireCore\Foundation\Assets\Contracts\Asset;

/**
 * A JavaScript bundle shipped inside a package's `dist/`.
 *
 * Delivery needs neither npm nor `vendor:publish` from a consumer. The toolkit
 * mirrors each package's `dist/` into `public/vendor/{package}` and this object
 * emits that path — a real file, which is what makes it survive a web server that
 * answers `.js` from `try_files $uri =404` and never forwards it to PHP. See
 * {@see PublishedAssets} for the mirror, which is lazy, incremental and atomic.
 *
 * Where `public/` cannot be written the mirror returns nothing and the package's own
 * asset route (`wire-core.asset`, `wire-table.asset`, …) streams the file straight
 * out of the package instead. This value object is the declaration of one bundle —
 * the route name follows from the registering package (`{package}.asset`) and the
 * `{asset}` parameter is the bundle's id, which keeps registration to one line.
 *
 * A path that already looks like a URL (`https://…`, `//…`) is used verbatim, the
 * same way Filament detects remoteness; there is no `remote()` builder to get wrong.
 *
 * Local bundles are cache-busted by an mtime (`?id=<mtime>`) — the mirrored copy's
 * where there is one, the shipped file's otherwise. That query string is also what
 * makes `data-navigate-track` meaningful: Livewire full-page-reloads a
 * `wire:navigate` visit when a tracked asset's query string changed, so an upgrade
 * is picked up instead of running new markup against a bundle the browser cached.
 */
final class Js implements Asset
{
    private ?string $package = null;

    private bool $module = false;

    private bool $defer = false;

    private bool $navigateTrack = false;

    private bool $navigateOnce = false;

    private bool $loadedOnRequest = false;

    private ?string $url = null;

    private ?string $html = null;

    private function __construct(
        private readonly string $id,
        private readonly string $path,
    ) {}

    /**
     * Declare a bundle: its id (the `{asset}` route parameter) and either an
     * absolute filesystem path inside the package or an absolute URL.
     */
    public static function make(string $id, string $path): self
    {
        return new self($id, $path);
    }

    /** Load the bundle as an ES module (`type="module"`). */
    public function module(): static
    {
        $this->module = true;

        return $this;
    }

    /** Defer execution until the document has been parsed. */
    public function defer(): static
    {
        $this->defer = true;

        return $this;
    }

    /** Force a full page reload on `wire:navigate` when this bundle changes. */
    public function navigateTrack(): static
    {
        $this->navigateTrack = true;

        return $this;
    }

    /** Never re-execute this bundle on a `wire:navigate` visit. */
    public function navigateOnce(): static
    {
        $this->navigateOnce = true;

        return $this;
    }

    /**
     * Keep the bundle out of the always-emitted set; the surface that needs it
     * fetches it on demand. For heavy, optional bodies only — never for the small
     * controller that registers an Alpine component.
     */
    public function loadedOnRequest(): static
    {
        $this->loadedOnRequest = true;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPackage(): ?string
    {
        return $this->package;
    }

    public function withPackage(string $package): static
    {
        $clone = clone $this;
        $clone->package = $package;
        $clone->url = null;
        $clone->html = null;

        return $clone;
    }

    public function isLoadedOnRequest(): bool
    {
        return $this->loadedOnRequest;
    }

    public function getUrl(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        if ($this->isRemote()) {
            return $this->url = $this->path;
        }

        if ($this->package === null) {
            throw AssetRegistrationException::notRegistered($this->id);
        }

        // The toolkit mirrors each package's dist/ into public/vendor and hands back
        // that path — a real file, which is what a web server answering `.js` from a
        // `try_files $uri =404` block will serve. The route below is the fallback for
        // a deployment whose public/ cannot be written.
        $published = app(PublishedAssets::class)->url($this->package, $this->path);

        if ($published !== null) {
            return $this->url = $published;
        }

        $version = @filemtime($this->path) ?: null;

        return $this->url = route($this->package.'.asset', ['asset' => $this->id])
            .($version ? '?id='.$version : '');
    }

    public function isStale(): bool
    {
        return ! $this->isRemote()
            && $this->package !== null
            && app(PublishedAssets::class)->isStale($this->package, $this->path);
    }

    public function toHtml(): string
    {
        return $this->html ??= '<script src="'.e($this->getUrl()).'"'
            .($this->module ? ' type="module"' : '')
            .($this->defer ? ' defer' : '')
            .($this->navigateTrack ? ' data-navigate-track' : '')
            .($this->navigateOnce ? ' data-navigate-once' : '')
            .'></script>';
    }

    /** A path that is already a URL is served as-is — no route, no mtime. */
    private function isRemote(): bool
    {
        return str_starts_with($this->path, 'http://')
            || str_starts_with($this->path, 'https://')
            || str_starts_with($this->path, '//');
    }
}
