<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether a URL would let this person in, for the surfaces that may not ask.
 *
 * The other half of {@see ResolvesPageUrls}. That one answers *where* a page is
 * and deliberately not *who may open it* — a breadcrumb and a search result
 * want the address regardless — while a surface that sends somebody there on
 * their behalf (a tour carrying on to the next page) must not walk them into a
 * 403. The page's rules live on its route, as `can:` middleware `wire-panels`
 * writes, so core declares the question and the routing package answers it.
 *
 * The default answer is yes: without a package that routes, no surface has a
 * URL to ask about, and one that does came from the application, whose own
 * routes are its own to guard.
 */
interface AuthorizesUrls
{
    /** Whether this person may open the page at this URL. */
    public function allowsUrl(string $url, ?Authenticatable $user): bool;
}
