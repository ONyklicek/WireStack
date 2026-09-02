<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Resources;

use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\NavigationSource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Exceptions\ResourceRegistrationException;

/**
 * Everything registered for a menu, arranged the way a menu shows it.
 *
 * Reads its sources and the declared groups and nothing else, so it never
 * instantiates anything: {@see ProvidesNavigation} is static for exactly this,
 * and a sidebar built from fifty resources composes no table and no form.
 *
 * It does not know what a resource is, and since V2.6 step 3 that is the point.
 * A dashboard belongs in a menu too, and `Widgets/` is a layer above this one —
 * so the classes arrive through {@see NavigationSource}, and whoever registers
 * them decides what they are.
 *
 * Deliberately small. It groups and orders entries; it owns no routing, no URL
 * shell and no layout, because a registry that held those would be a panel and
 * this layer is not one. What renders the result is the application's, and V2.6's
 * domain-module axis is expected to sit on top of this rather than replace it.
 */
final readonly class Workspace
{
    /**
     * @param  array<int, NavigationSource>  $sources  Where the menu's classes come from, in the order they are read.
     */
    public function __construct(
        private array $sources,
        private NavigationGroups $groups,
    ) {}

    /**
     * The menu: its groups in order, each carrying its own entries in order.
     *
     * A {@see NavigationGroup} rather than the bare key it used to be, because a
     * heading needs to say more than its own identity — see that class for what
     * a string could not do. A key nothing declared still appears, as an
     * implicit group, so grouping never requires registration.
     *
     * Group order is the declared `sort()`, and groups that tie keep the order
     * their first entry was registered in — the same rule entries follow inside
     * a group, so an application that numbers nothing still reads in the order
     * it declared things.
     *
     * A hidden group takes its entries with it, and is therefore not a heading
     * with nothing under it — it is absent. That is the whole reason the group
     * owns visibility: the alternative is the same condition repeated on every
     * resource in the group, which drifts the first time someone adds the n+1st
     * resource and forgets. The dropping happens once, in `entries()`.
     *
     * @return array<string, NavigationGroup> Keyed by group key; the ungrouped top level is `''`.
     */
    public function navigation(): array
    {
        $buckets = [];

        // Registration order, deliberately: it decides which group came first,
        // and sorting the entries beforehand would make that depend on the
        // entry numbering instead.
        foreach ($this->entries() as $key => $item) {
            $buckets[$item->getGroup() ?? ''][$key] = $item;
        }

        $groups = [];

        foreach ($buckets as $key => $items) {
            // No visibility check here on purpose. A hidden group's entries were
            // already dropped by entries(), so no bucket for one can exist —
            // a guard here would be a second copy of the rule, and an
            // unreachable one, which is worse than a missing one: it reads like
            // the place the rule lives.
            $group = $this->groups->find($key) ?? NavigationGroup::make($key);

            $groups[$key] = $group->withItems($this->sorted($items));
        }

        uasort($groups, static fn (NavigationGroup $a, NavigationGroup $b): int => $a->getSort() <=> $b->getSort());

        return $groups;
    }

    /**
     * Every visible entry, flat and in `sort()` order — the menu without its
     * headings.
     *
     * What a menu that draws no groups shows. Entries whose group is hidden are
     * not here either: "in the menu" has to mean one thing, and a flat list that
     * disagreed with {@see navigation()} about it would be two answers to one
     * question.
     *
     * @return array<string, NavigationItem> Keyed by resource key.
     */
    public function items(): array
    {
        return $this->sorted($this->entries());
    }

    /**
     * Every class behind the menu, in registration order — whatever kind it is.
     *
     * Includes the ones that declare no entry: registered and listed are two
     * different questions, and this answers the first.
     *
     * @return array<string, class-string>
     */
    public function registered(): array
    {
        $classes = [];

        foreach ($this->sources as $source) {
            foreach ($source->navigableClasses() as $key => $class) {
                $existing = $classes[$key] ?? null;

                // Two sources claiming one key is refused rather than resolved.
                // Whichever way it were resolved, one entry would take another's
                // place in the menu — and a menu that quietly lost a row is
                // noticed on the day the row was the one someone needed.
                if ($existing !== null && $existing !== $class) {
                    throw ResourceRegistrationException::duplicateResourceKey($key, $existing, $class);
                }

                $classes[$key] = $class;
            }
        }

        return $classes;
    }

    /**
     * Every entry that belongs in the menu, in registration order.
     *
     * Keyed by the key its class was registered under, because an entry is only
     * half of what a menu row needs: the other half is what it stands for, and
     * that is what a consumer turns into a link. {@see NavigationItem}
     * deliberately holds no URL — a registry that held URLs would be a panel —
     * so identity has to arrive some other way, and the key a source already
     * addresses the class by is that way.
     *
     * @return array<string, NavigationItem>
     */
    private function entries(): array
    {
        $items = [];

        foreach ($this->registered() as $key => $class) {
            if (! is_subclass_of($class, ProvidesNavigation::class)) {
                // Registered and reachable, just not in the menu — what an
                // internal or nested resource wants.
                continue;
            }

            $item = $class::navigation();

            if (! $item->isVisible() || ! $this->groupIsVisible($item)) {
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
            //
            // Only a resource has a plural to fall back to. Anything else in a
            // menu names its own entry, which is the honest division: there is
            // no general "what is this class called" to reach for, and inventing
            // one would be a third vocabulary beside `HasLabel` and this.
            if ($item->getLabel() === null && is_subclass_of($class, DescribesResource::class)) {
                $item->label($class::pluralLabel());
            }

            $items[$key] = $item;
        }

        return $items;
    }

    private function groupIsVisible(NavigationItem $item): bool
    {
        $group = $this->groups->find($item->getGroup() ?? '');

        return $group === null || $group->isVisible();
    }

    /**
     * @param  array<string, NavigationItem>  $items
     * @return array<string, NavigationItem>
     */
    private function sorted(array $items): array
    {
        // Stable: equal sort values keep declaration order, which is what makes
        // `sort()` optional rather than mandatory. `uasort`, not `usort`, so the
        // resource keys survive — a menu that has been ordered but can no longer
        // say which resource an entry belongs to cannot link it anywhere.
        uasort($items, static fn (NavigationItem $a, NavigationItem $b): int => $a->getSort() <=> $b->getSort());

        return $items;
    }
}
