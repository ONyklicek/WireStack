<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;

/**
 * The registered resources, arranged the way a menu shows them.
 *
 * Reads the registry and nothing else, so it never instantiates a resource:
 * {@see ProvidesNavigation} is static for exactly this, and a sidebar built from
 * fifty resources composes no table and no form.
 *
 * Deliberately small. It groups and orders entries; it owns no routing, no URL
 * shell and no layout, because a registry that held those would be a panel and
 * this layer is not one. What renders the result is the application's, and V2.6's
 * domain-module axis is expected to sit on top of this rather than replace it.
 */
final readonly class Workspace
{
    public function __construct(private ResourceRegistry $registry) {}

    /**
     * Every visible entry, grouped by heading and ordered within each group.
     *
     * Groups keep the order their first entry appeared in, so a declaration
     * order that reads sensibly in config reads the same way in the menu; the
     * ungrouped top level comes first under an empty key.
     *
     * @return array<string, array<int, NavigationItem>>
     */
    public function navigation(): array
    {
        $groups = [];

        foreach ($this->items() as $item) {
            $groups[$item->getGroup() ?? ''][] = $item;
        }

        foreach ($groups as $group => $items) {
            // Stable within a group: equal sort values keep declaration order,
            // which is what makes `sort()` optional rather than mandatory.
            usort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());
            $groups[$group] = $items;
        }

        return $groups;
    }

    /**
     * Every visible entry, flat and ordered — the menu without its headings.
     *
     * @return array<int, NavigationItem>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->registry->all() as $resource) {
            if (! is_subclass_of($resource, ProvidesNavigation::class)) {
                // Registered and routable, just not in the menu — what an
                // internal or nested resource wants.
                continue;
            }

            $item = $resource::navigation();

            if ($item->isVisible()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The resource classes behind the menu, in registration order.
     *
     * @return array<string, class-string<DescribesResource>>
     */
    public function resources(): array
    {
        return $this->registry->all();
    }
}
