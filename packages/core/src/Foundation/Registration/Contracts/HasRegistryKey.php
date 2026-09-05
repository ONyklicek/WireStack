<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Registration\Contracts;

/**
 * A class that knows the key it is registered under.
 *
 * The half of identity every consumer of a {@see RegistrySource} needs and none
 * of them should re-derive: a menu keys its entries by it, the router turns it
 * into a URL prefix and a route name, and the search palette groups results by
 * it. `DescribesResource` and `Dashboard` have declared exactly this method
 * since before the catalogue existed — this only gives it one name, so a
 * contract can require it without requiring a resource *or* a dashboard.
 */
interface HasRegistryKey
{
    /**
     * The stable key this class is addressed by — the same one its source keys
     * it under, and the one a URL and a menu entry are built from.
     */
    public static function key(): string;
}
