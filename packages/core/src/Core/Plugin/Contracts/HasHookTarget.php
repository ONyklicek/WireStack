<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Contracts;

use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * A hook payload that knows where it came from.
 *
 * The dispatcher reads this, and nothing else, to decide whether a scoped
 * callback (`hook(..., for: 'invoices')`) applies to this payload. A payload
 * that does not implement it is never scoped away — an unscoped callback still
 * runs, which is what every 2.x plugin expects.
 */
interface HasHookTarget
{
    public function hookTarget(): ?HookTarget;
}
