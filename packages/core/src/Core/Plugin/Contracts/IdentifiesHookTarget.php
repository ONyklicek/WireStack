<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Contracts;

/**
 * A host that can say which registered entry it is showing.
 *
 * Implemented by the component a surface renders in — a resource page knows the
 * resource it belongs to, so its tables and forms can be addressed by that
 * resource's key rather than by class name. That is what lets a hook be scoped
 * to *one module's* list:
 *
 * ```php
 * $manager->hook(Hook::TableConfiguring, $callback, for: 'invoices');
 * ```
 *
 * Declared rather than duck-typed. A `method_exists($host, 'resourceClass')`
 * check would read a same-named method written for other reasons as a
 * declaration, and the resulting scope would be wrong in the one direction
 * nobody tests: a callback silently running somewhere it was not meant to.
 *
 * Optional, and absent for good reasons: a standalone table in a hand-written
 * component belongs to no registry entry and has no key. Such a host is still
 * addressable by its class or its model.
 */
interface IdentifiesHookTarget
{
    /**
     * The registered key this host shows, or null when it shows nothing
     * registered.
     */
    public function hookKey(): ?string;
}
