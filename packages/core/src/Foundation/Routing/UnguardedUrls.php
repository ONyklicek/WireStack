<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Routing;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;

/**
 * The answer when nothing owns routing: every URL is let through.
 *
 * Bound by `wire-core` beside {@see UnroutedPageUrls} and rebound by
 * `wire-panels`, for the same reason — a null object rather than a nullable
 * dependency every caller would have to check.
 */
final class UnguardedUrls implements AuthorizesUrls
{
    public function allowsUrl(string $url, ?Authenticatable $user): bool
    {
        return true;
    }
}
