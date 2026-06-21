<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Concerns;

use NyonCode\WireCore\Infolists\Infolist;

/**
 * Host trait for components that expose one or more infolists.
 *
 * Infolists are stateless read-only views, so this trait is intentionally
 * light: it provides a factory hook so host components can build infolists in a
 * consistent place and override creation if needed.
 */
trait WithInfolists
{
    /**
     * Create a fresh infolist instance. Override to customise defaults.
     */
    public function makeInfolist(): Infolist
    {
        return Infolist::make();
    }
}
