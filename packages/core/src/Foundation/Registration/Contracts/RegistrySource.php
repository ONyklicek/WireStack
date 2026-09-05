<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Registration\Contracts;

use NyonCode\WireCore\Foundation\Registration\Catalog;

/**
 * Somewhere an application's registered classes can come from.
 *
 * This began as `NavigationSource`, and the inversion it introduced was right:
 * a menu could not be taught what a dashboard is, because `Workspace` lives in
 * `Core/` (L1) and `Widgets/` is L2, so the direction was flipped — the menu
 * knows a contract and the classes handed back, and whoever registers them
 * decides what they are.
 *
 * What the name got wrong is who asks. A menu was the first consumer, not the
 * only one: a router turns the same classes into routes and a palette searches
 * them, and both reached past this contract into `ResourceRegistry` directly —
 * which is why a dashboard could appear in a menu but never be routed or found
 * (ADR 0026). One question, one seam.
 *
 * Being registered is not being listed, routed or searchable. Those stay
 * separate opt-ins on the class itself — `ProvidesNavigation`, `ProvidesPages`,
 * `GloballySearchable` — and each consumer applies its own.
 *
 * @see Catalog The aggregate that reads every source and owns the key namespace.
 */
interface RegistrySource
{
    /**
     * The classes this source holds, keyed by the key they are addressed by.
     *
     * Keys are unique across *all* sources: {@see Catalog} refuses a collision
     * rather than letting one entry take another's place, because a menu that
     * quietly lost a row is the kind of thing nobody notices until the row was
     * the one they needed — and a route registered at a prefix another key owns
     * is the same mistake with a worse blast radius.
     *
     * @return array<string, class-string>
     */
    public function registeredClasses(): array;
}
