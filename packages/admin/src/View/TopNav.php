<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Routing\Zone;

/**
 * The menu as a bar under the header: `<x-wire-admin::top-nav />`.
 *
 * The same {@see Workspace::navigation()} the sidebar reads, the same
 * {@see ActiveNavigation} deciding what is marked — only the drawing is its own,
 * which is the line the repository keeps between shared semantics and
 * per-surface rendering. Read once while the page renders, for the reason
 * {@see Sidebar} gives.
 *
 * A group becomes a button opening its entries; an entry with no group, and a
 * group of one, are links in the bar itself — a panel holding one row is a
 * click spent on nothing. What does not fit the bar's width is shown under
 * "More" by the `wireTopNav` controller.
 */
class TopNav extends Component
{
    public ?string $zone;

    public ?string $activeKey;

    public function __construct(
        public bool $linkedOnly = false,
        ?string $zone = null,
        ?string $activeKey = null,
    ) {
        $this->zone = $zone ?? Zone::current();
        $this->activeKey = $activeKey ?? Zone::currentKey();
    }

    /**
     * The bar's entries, in menu order: a group with a heading and more than one
     * row, or a single link.
     *
     * @return array<int, array{id: string, group: ?NavigationGroup, key: ?string, item: ?NavigationItem}>
     */
    protected function entries(): array
    {
        $entries = [];

        foreach (app(Workspace::class)->navigation($this->zone, $this->linkedOnly) as $group) {
            $items = $group->getItems();

            if ($group->getKey() !== '' && $group->hasVisibleLabel() && count($items) > 1) {
                $entries[] = ['id' => 'g-'.$group->getKey(), 'group' => $group, 'key' => null, 'item' => null];

                continue;
            }

            foreach ($items as $key => $item) {
                $entries[] = ['id' => 'i-'.$key, 'group' => null, 'key' => (string) $key, 'item' => $item];
            }
        }

        return $entries;
    }

    /** Protected for the reason {@see Sidebar::active()} gives. */
    protected function active(): ActiveNavigation
    {
        return ActiveNavigation::current()->withKey($this->activeKey);
    }

    public function render(): View
    {
        return view('wire-admin::top-nav', [
            'entries' => $this->entries(),
            'active' => $this->active(),
        ]);
    }
}
