<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

use NyonCode\WireCore\WireCoreServiceProvider;

/**
 * Canonical owner of the floating-dropdown asset URL.
 *
 * The "Teleport + Floating UI" dropdown script is emitted by a partial that is
 * `@include`d many times per page (once per action-group dropdown, in both the
 * desktop and mobile layouts). Computing the route + cache-busting mtime on every
 * include is wasted work, so this resolves it once per request and memoises the
 * string (see architecture/plans/render-engine-htmlable-first.md §4). Registered as a
 * container singleton so the memo spans the whole request.
 */
final class FloatingAssets
{
    private ?string $url = null;

    /**
     * URL of the pre-bundled dropdown script, cache-busted by the file's mtime.
     * Resolved once per request, then memoised.
     */
    public function url(): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        $version = @filemtime(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js') ?: null;

        return $this->url = route('wire-core.asset', ['asset' => 'dropdown'])
            .($version ? '?id='.$version : '');
    }
}
