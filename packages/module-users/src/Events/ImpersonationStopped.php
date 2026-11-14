<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody went back to their own account after acting as another.
 */
final class ImpersonationStopped
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $impersonator,
        public readonly ?Authenticatable $impersonated,
    ) {}
}
