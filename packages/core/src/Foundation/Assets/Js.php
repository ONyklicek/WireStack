<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Assets;

use NyonCode\WireCore\Exceptions\AssetRegistrationException;
use NyonCode\WireCore\Foundation\Assets\Contracts\Asset;

/**
 * A JavaScript bundle shipped inside a package's `dist/`.
 *
 * Delivery is deliberately publish-free: every package already exposes a named
 * asset route (`wire-core.asset`, `wire-table.asset`, `wire-forms.asset`) that
 * streams the file straight out of the package, so a consumer needs neither npm nor
 * `vendor:publish`. This value object is the declaration of one such bundle — the
 * route name follows from the registering package (`{package}.asset`) and the
 * `{asset}` parameter is the bundle's id, which keeps registration to one line.
 *
 * A path that already looks like a URL (`https://…`, `//…`) is used verbatim, the
 * same way Filament detects remoteness; there is no `remote()` builder to get wrong.
 *
 * Local bundles are cache-busted by the file's mtime (`?id=<mtime>`), the convention
 * the per-surface partials already used. That query string is also what makes
 * `data-navigate-track` meaningful: Livewire full-page-reloads a `wire:navigate`
 * visit when a tracked asset's query string changed, so a deploy is picked up
 * instead of running new markup against a stale bundle.
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

        $version = @filemtime($this->path) ?: null;

        return $this->url = route($this->package.'.asset', ['asset' => $this->id])
            .($version ? '?id='.$version : '');
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
