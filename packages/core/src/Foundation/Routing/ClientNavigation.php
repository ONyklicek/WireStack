<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Whether a link is followed with Livewire's `wire:navigate` or as a full page
 * load.
 *
 * The one place that question is answered. Every link the framework renders —
 * the sidebar, a breadcrumb, an action with a url, a record link in a table —
 * and every redirect a component issues asks here, so `wire-core.navigate`
 * switches all of them at once and an application never meets a page where the
 * menu keeps its state and the row's "Edit" throws it away.
 *
 * Three things decide, in this order:
 *
 *  1. The URL must be this application's. `wire:navigate` fetches the target
 *     and swaps the body in, which is meaningless for another origin, a
 *     `mailto:` or a fragment — those are always plain links, whatever the
 *     switch says, because a menu item that points elsewhere is not a mistake.
 *  2. A per-link preference, when the link has one (`->navigate(false)` on an
 *     action, a column, a table's record url). It wins over the switch in both
 *     directions: a download stays a full load in a navigating app, and one
 *     link may navigate in an app that turned it off.
 *  3. The switch, `wire-core.navigate`, on by default.
 *
 * Opening in a new tab is the caller's to rule out: a `target="_blank"` link
 * with `wire:navigate` still opens a tab, so there is nothing to decide here,
 * only a needless attribute to leave off.
 */
final class ClientNavigation
{
    /** The attribute a navigating link carries. */
    public const ATTRIBUTE = 'wire:navigate';

    public function __construct(
        private readonly Repository $config,
        private readonly UrlGenerator $urls,
    ) {}

    /** Whether links navigate when nothing more specific says otherwise. */
    public function enabled(): bool
    {
        return (bool) $this->config->get('wire-core.navigate', true);
    }

    /**
     * Whether a link to `$url` is followed with `wire:navigate`.
     *
     * `$preference` is the link's own choice, null when it has none.
     */
    public function shouldNavigate(?string $url, ?bool $preference = null): bool
    {
        if ($url === null || ! $this->isInternal($url)) {
            return false;
        }

        return $preference ?? $this->enabled();
    }

    /**
     * The attribute to write on a link to `$url`, or an empty string.
     *
     * What `@wireNavigate($url)` echoes.
     */
    public function attribute(?string $url, ?bool $preference = null): string
    {
        return $this->shouldNavigate($url, $preference) ? self::ATTRIBUTE : '';
    }

    /**
     * Whether `$url` is a page of this application.
     *
     * A relative path is; a scheme-relative or absolute URL is when its scheme,
     * host and port are the application's own. A fragment, an empty string and
     * any other scheme (`mailto:`, `tel:`, `javascript:`) are not pages.
     */
    public function isInternal(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#')) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        if (! isset($parts['scheme']) && ! isset($parts['host'])) {
            return true;
        }

        if (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $root = parse_url($this->urls->to('/'));

        return strtolower($parts['host'] ?? '') === strtolower($root['host'] ?? '')
            && ($parts['port'] ?? null) === ($root['port'] ?? null)
            && (! isset($parts['scheme']) || strtolower($parts['scheme']) === strtolower($root['scheme'] ?? ''));
    }
}
