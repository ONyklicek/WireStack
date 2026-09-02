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
     * ungrouped top level comes first under an empty key. Inside a group the
     * entries stay keyed by resource key, exactly as {@see items()} hands them
     * over.
     *
     * @return array<string, array<string, NavigationItem>>
     */
    public function navigation(): array
    {
        $groups = [];

        foreach ($this->items() as $key => $item) {
            $groups[$item->getGroup() ?? ''][$key] = $item;
        }

        foreach ($groups as $group => $items) {
            // Stable within a group: equal sort values keep declaration order,
            // which is what makes `sort()` optional rather than mandatory.
            // Sorted by `uasort`, not `usort`, so the resource keys survive the
            // sort — a menu that has been ordered but can no longer say which
            // resource an entry belongs to cannot link it anywhere.
            uasort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());
            $groups[$group] = $items;
        }

        return $groups;
    }

    /**
     * Every visible entry, flat and ordered — the menu without its headings.
     *
     * Keyed by resource key, because an entry is only half of what a menu row
     * needs: the other half is which resource it stands for, and that is what a
     * consumer turns into a link. {@see NavigationItem} deliberately holds no
     * URL — a registry that held URLs would be a panel — so identity has to
     * arrive some other way, and the key the registry already routes on is that
     * way.
     *
     * @return array<string, NavigationItem>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->registry->all() as $key => $resource) {
            if (! is_subclass_of($resource, ProvidesNavigation::class)) {
                // Registered and routable, just not in the menu — what an
                // internal or nested resource wants.
                continue;
            }

            $item = $resource::navigation();

            if (! $item->isVisible()) {
                continue;
            }

            // An entry that did not name itself is named by its resource.
            //
            // `label` and `pluralLabel` are already the resource's words for
            // this (`DescribesRecords` derives both off the model), so a menu
            // entry forced to repeat one of them would be a second vocabulary
            // for the same question — and the copy that drifts, because nothing
            // renders the two side by side. Here the entry stays free to
            // override, and one that does not is still named.
            if ($item->getLabel() === null) {
                $item->label($resource::pluralLabel());
            }

            $items[$key] = $item;
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
