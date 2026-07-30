<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Assets\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Foundation\Assets\AssetManager;

/**
 * A browser asset a package ships and the {@see AssetManager} can emit into a
 * document.
 *
 * The asset is `Htmlable` so a caller renders it by echoing it (Rendering Rule 3);
 * it never needs a helper and never goes through an `<x-*>` component.
 */
interface Asset extends Htmlable
{
    /**
     * Identity inside the owning package. It doubles as the `{asset}` parameter of
     * that package's asset route, which is what keeps registration declarative.
     */
    public function getId(): string;

    /** The package that registered it, or `null` while it is still unregistered. */
    public function getPackage(): ?string;

    /** A copy of this asset bound to the package registering it. */
    public function withPackage(string $package): static;

    /**
     * The cache-busted URL the browser fetches. Resolved once, then memoised — an
     * asset instance is registry-lifetime, so the route and the mtime are read at
     * most once per request.
     */
    public function getUrl(): string;

    /**
     * Whether the asset is fetched on demand by the surface that needs it, rather
     * than emitted into every document. Heavy, optional bundles (a rich-text editor,
     * an image processor) opt in; the small controller that loads them must not.
     */
    public function isLoadedOnRequest(): bool;
}
