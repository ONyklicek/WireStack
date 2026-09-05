<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Routing;

use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;

/**
 * The routing package's answer to core's URL question.
 *
 * An adapter over {@see ResourceRoutes::urlFor()} and nothing more — the method
 * it delegates to already existed, already answered null for an unrouted key,
 * and was already tested to both edges. What was missing was a way to reach it
 * from `wire-core`, which is why every application wrote its own key→URL map and
 * why this repository's own workbench wrote three (ADR 0026).
 */
final class RegisteredPageUrls implements ResolvesPageUrls
{
    public function urlFor(string $key, string $page = 'index', array $parameters = []): ?string
    {
        return ResourceRoutes::urlFor($key, $page, $parameters);
    }
}
