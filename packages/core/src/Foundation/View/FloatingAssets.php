<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use NyonCode\WireCore\Foundation\Assets\AssetManager;

/**
 * The floating-dropdown bundle URL, by the name a dozen partials already ask for it.
 *
 * The "Teleport + Floating UI" dropdown script is emitted by a partial that is
 * `@include`d many times per page (once per action-group dropdown, in both the
 * desktop and mobile layouts), so the route + cache-busting mtime must resolve once
 * per request rather than once per include (see
 * architecture/plans/render-engine-htmlable-first.md §4).
 *
 * That memo now belongs to the canonical {@see AssetManager}, which owns the same
 * concern for every package's bundles; this stays as a thin facade so the existing
 * include sites keep working unchanged. It deliberately holds no cache of its own —
 * a canonical owner is the resolve-once, and wrapping it in a second cache would
 * only add a second thing to invalidate.
 */
final class FloatingAssets
{
    public function __construct(private readonly AssetManager $assets) {}

    /** URL of the pre-bundled dropdown script, cache-busted by the file's mtime. */
    public function url(): string
    {
        return $this->assets->url('wire-core', 'dropdown');
    }
}
