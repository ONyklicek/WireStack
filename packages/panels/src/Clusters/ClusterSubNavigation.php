<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Clusters;

use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WirePanels\Enums\SubNavigationPosition;

/**
 * What one member page draws beside its content: the cluster's other screens.
 *
 * Resolved by {@see ClusterNavigation::for()} and handed to the view whole, so
 * the partial decides nothing but markup — which member is current is a
 * comparison of registered keys made here, not of URLs made in Blade.
 */
final readonly class ClusterSubNavigation
{
    /**
     * @param  string  $label  The cluster's name — the navigation's accessible name.
     * @param  array<string, NavigationItem>  $items  The members' entries, by registered key.
     * @param  string|null  $currentKey  The member this page belongs to.
     */
    public function __construct(
        public string $label,
        public array $items,
        public SubNavigationPosition $position,
        public ?string $currentKey,
    ) {}

    /** Whether there is anything to draw — one member is its own heading written twice. */
    public function isEmpty(): bool
    {
        return count($this->items) < 2;
    }

    public function isCurrent(string $key): bool
    {
        return $key === $this->currentKey;
    }

    /**
     * The side a column sits on, or null when the navigation is a row of tabs.
     *
     * What the page's root element carries as `data-cluster-frame`; the layout
     * is plain CSS keyed on it, so no utility has to exist in the consumer's
     * Tailwind build for the column to appear.
     */
    public function frame(): ?string
    {
        return $this->isEmpty() || $this->position === SubNavigationPosition::Top ? null : $this->position->value;
    }
}
