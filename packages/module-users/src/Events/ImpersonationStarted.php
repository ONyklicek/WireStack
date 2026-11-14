<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody began acting as another account — for an audit trail to record.
 */
final class ImpersonationStarted
{
    use Dispatchable;

    public function __construct(
        public readonly Authenticatable $impersonator,
        public readonly ?Authenticatable $impersonated,
    ) {}
}
