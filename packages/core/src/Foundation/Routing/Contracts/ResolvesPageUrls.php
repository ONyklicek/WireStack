<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Contracts;

use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;

/**
 * Where a registered key's page lives, for the surfaces that may not ask.
 *
 * A menu entry and a search result both need a URL, and both are `wire-core`
 * while the URL convention is `wire-panels` — core may not name it. So the
 * direction is inverted the way {@see RegistrySource}
 * inverted it for the menu: core declares the question, whoever owns routing
 * answers it, and the default answer is `null`.
 *
 * Null is a real answer, not a failure. A resource that declares no pages is
 * deliberately unrouted, an application may route nothing at all, and every
 * consumer already renders without a link — a menu entry with no href, a search
 * result that only reads. Nothing here may make routing mandatory.
 *
 * The signature is `ResourceRoutes::urlFor()`'s, deliberately: the binding in
 * `wire-panels` is an adapter over the method that already exists and is already
 * tested, not a second implementation of it.
 */
interface ResolvesPageUrls
{
    /**
     * The URL of one registered key's page, or null when it is not routed.
     *
     * @param  string  $key  The key its source registered it under.
     * @param  string  $page  A page kind — `index`, `view`, `edit`, or one of the application's own.
     * @param  array<string, mixed>  $parameters  Route parameters; `['record' => …]` for the record pages.
     */
    public function urlFor(string $key, string $page = 'index', array $parameters = []): ?string;
}
