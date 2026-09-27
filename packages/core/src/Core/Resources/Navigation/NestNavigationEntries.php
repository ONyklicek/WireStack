<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources\Navigation;

use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Exceptions\NavigationParentException;
use NyonCode\WireCore\Foundation\Routing\Contracts\BelongsToCluster;

/**
 * Moves every entry that named a parent under that parent.
 *
 * Its own class rather than a method on {@see Workspace}, which orders a menu
 * and should stay small enough to read; this is the one step of building it
 * that has rules of its own.
 *
 * First, an entry whose class belongs to a registered cluster leaves the menu
 * altogether: the cluster's own entry stands for the whole section, and its
 * members are drawn on the cluster's pages instead (ADR 0039). A cluster that
 * is not registered takes nothing away — the router refuses that declaration,
 * and a menu that hid the member meanwhile would hide the evidence.
 *
 * Then the parent rules, in the order they are checked:
 *
 * 1. A parent nothing registered, or the entry itself, is refused — see
 *    {@see NavigationParentException} for why neither is quietly skipped.
 * 2. A parent that is registered but **not in this menu** — hidden from this
 *    user, left out by `linkedOnly`, removed by a hook — leaves the entry where
 *    it would have been without one, in its own group. Taking the child away
 *    with the parent would hide a page this user may open because of a page
 *    they may not.
 * 3. A parent that is itself under another entry is refused: the menu nests one
 *    level, as {@see NavigationItem::children()} does.
 * 4. Otherwise the entry leaves its group and joins the parent's children,
 *    after any the parent wrote itself, ordered by `sort()` among them.
 *
 * A branch is a disclosure in the menu, not a link, so a parent that is a page
 * of its own would stop being reachable the moment something moved under it.
 * It is therefore repeated as its own first child — same label, same URL, same
 * key, so it is lit on its own pages the way the row was.
 */
final class NestNavigationEntries
{
    /**
     * @param  array<string, NavigationItem>  $items  The menu, keyed by registered key.
     * @param  array<string, class-string>  $registered  Every registered class by key, in the menu or not.
     * @return array<string, NavigationItem>
     */
    public function __invoke(array $items, array $registered): array
    {
        $items = array_diff_key($items, $this->clustered($registered));

        /** @var array<string, array<int, NavigationItem>> $adopted */
        $adopted = [];

        // Looked up in the menu as it arrived, not as it is being rebuilt: a
        // parent moved under its own parent a moment ago is still a parent that
        // sits one level down, and must be refused rather than read as absent.
        $menu = $items;

        foreach ($menu as $key => $item) {
            $parent = $item->getParent();

            if ($parent === null) {
                continue;
            }

            if ($parent === $key) {
                throw NavigationParentException::itself($key);
            }

            if (! isset($menu[$parent])) {
                if (! isset($registered[$parent])) {
                    throw NavigationParentException::unknown($key, $parent);
                }

                continue;
            }

            $grandparent = $menu[$parent]->getParent();

            if ($grandparent !== null) {
                throw NavigationParentException::tooDeep($key, $parent, $grandparent);
            }

            $adopted[$parent][] = $item;
            unset($items[$key]);
        }

        foreach ($adopted as $parent => $children) {
            $items[$parent] = $items[$parent]->withAdoptedChildren([
                ...$this->itself($items[$parent], $parent),
                ...$children,
            ]);
        }

        return $items;
    }

    /**
     * The keys whose class sits inside a registered cluster.
     *
     * @param  array<string, class-string>  $registered
     * @return array<string, true>
     */
    private function clustered(array $registered): array
    {
        $clustered = [];

        foreach ($registered as $key => $class) {
            if (! is_a($class, BelongsToCluster::class, true)) {
                continue;
            }

            $cluster = $class::cluster();

            if ($cluster !== null && in_array($cluster, $registered, true)) {
                $clustered[$key] = true;
            }
        }

        return $clustered;
    }

    /**
     * The parent's own destination, as the first of its children.
     *
     * @return array<int, NavigationItem>
     */
    private function itself(NavigationItem $parent, string $key): array
    {
        $url = $parent->getUrl();

        if ($url === null) {
            return [];
        }

        return [
            NavigationItem::make($parent->getLabel())
                ->icon($parent->getIcon())
                ->url($url)
                ->key($key)
                ->sort(PHP_INT_MIN),
        ];
    }
}
