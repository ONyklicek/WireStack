<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use NyonCode\LaravelPackageToolkit\Support\PackageAssets;

/**
 * The floating-dropdown bundle URL, by the name a dozen partials already ask for it.
 *
 * The "Teleport + Floating UI" dropdown script is emitted by a partial that is
 * `@include`d many times per page (once per action-group dropdown, in both the
 * desktop and mobile layouts), so resolving it must not repeat the work once per
 * include (see architecture/plans/render-engine-htmlable-first.md §4).
 *
 * That concern belongs to the toolkit's {@see PackageAssets}, which resolves every
 * declared entry the same way and memoises the published URL one layer down in
 * `PublishedAssets`. This stays as a thin facade so the existing include sites keep
 * working unchanged, and so one place — not a dozen — knows that the entry key is a
 * filename rather than the short id the old registry used. It deliberately holds no
 * cache of its own: a canonical owner *is* the resolve-once, and a second cache would
 * only be a second thing to invalidate.
 */
final class FloatingAssets
{
    /** The entry key, which under the toolkit is the shipped file. */
    private const ENTRY = 'wire-core-dropdown.js';

    public function __construct(private readonly PackageAssets $assets) {}

    /**
     * URL of the pre-bundled dropdown script, cache-busted by the file's mtime.
     *
     * `null` only where nothing is published, `public/` cannot be written *and* the
     * package's own asset route could not be built — a combination the fallback in
     * `WireCoreServiceProvider` exists to prevent. Callers emit a `<script>` around
     * this, and an empty `src` makes a browser fetch the current page's HTML as a
     * script, so the empty string is not a safe stand-in and the tag is skipped
     * instead.
     */
    public function url(): ?string
    {
        return $this->assets->url('wire-core', self::ENTRY);
    }
}
