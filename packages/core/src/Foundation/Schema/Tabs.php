<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Tabbed layout: a horizontal tab bar over a set of {@see Tab} panels, switched
 * client-side (no server round trip). All panels stay in the DOM so nested
 * fields validate together on submit regardless of the active tab.
 *
 * Shared schema vocabulary consumed by forms and infolists; the child schema of
 * each tab flattens normally for state/validation because {@see Tab} is a
 * {@see LayoutComponent}.
 */
class Tabs extends LayoutComponent
{
    protected int $activeTab = 0;

    /**
     * Zero-based index of the tab shown first.
     */
    public function activeTab(int $index): static
    {
        $this->activeTab = $index;

        return $this;
    }

    public function getActiveTab(): int
    {
        return $this->activeTab;
    }

    /**
     * The visible tabs, re-indexed so the active-tab index and rendered order
     * stay aligned when a tab is hidden.
     *
     * @return array<int, Tab>
     */
    public function getTabs(): array
    {
        return array_values(array_filter(
            $this->getSchema(),
            static fn ($component): bool => $component instanceof Tab && $component->isVisible(),
        ));
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.tabs';
    }
}
