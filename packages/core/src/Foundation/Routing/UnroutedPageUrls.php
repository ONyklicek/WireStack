<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/**
 * The answer when nothing owns routing: there is no URL.
 *
 * Bound by `wire-core` so that a menu and a search palette work in an
 * application that installed no page package at all, and rebound by
 * `wire-panels` when one is present. A null object rather than a nullable
 * dependency, because every consumer would otherwise carry the same
 * `?ResolvesPageUrls` check and one of them would eventually forget it.
 */
final class UnroutedPageUrls implements ResolvesPageUrls
{
    public function urlFor(string $key, string $page = 'index', array $parameters = [], ?string $zone = null): ?string
    {
        return null;
    }
}
