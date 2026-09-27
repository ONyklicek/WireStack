<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Core\Resources\Navigation\ActiveNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroup;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Routing\Zone;

/**
 * The menu: `<x-wire-admin::sidebar />`.
 *
 * Reads {@see Workspace} and nothing else. It owns no URL scheme — every entry's
 * link comes from `ResolvesPageUrls`, answered by whoever owns routing and by
 * nothing at all otherwise, so an application that registers resources and
 * routes none draws a menu of unlinked rows rather than a menu of dead links.
 *
 * **The zone is read once, here, while the page renders.** Not inside a Livewire
 * update, where `Route::currentRouteName()` answers `livewire.update` and every
 * zone-derived answer is null (ADR 0027). This component is rendered by the
 * layout, which only runs on a full page load — that is what makes reading it
 * here correct rather than lucky, and why the zone is not re-derived per render
 * further down.
 *
 * @property-read array<string, NavigationGroup> $groups
 */
class Sidebar extends Component
{
    public ?string $zone;

    public ?string $activeKey;

    /**
     * @param  bool  $linkedOnly  Drop entries this zone cannot reach, instead of
     *                            drawing them unlinked. An application that routes only part of
     *                            its catalogue wants one or the other, and which one is taste.
     * @param  bool  $drawerOnly  Only the phone drawer: from `lg` up the menu is drawn
     *                            elsewhere — the bar a `top` layout puts under the header.
     */
    public function __construct(
        public bool $linkedOnly = false,
        public bool $drawerOnly = false,
        ?string $zone = null,
        ?string $activeKey = null,
    ) {
        $this->zone = $zone ?? Zone::current();
        $this->activeKey = $activeKey ?? Zone::currentKey();
    }

    /**
     * The menu, grouped and ordered, for this zone.
     *
     * @return array<string, NavigationGroup>
     */
    public function groups(): array
    {
        return app(Workspace::class)->navigation($this->zone, $this->linkedOnly);
    }

    /**
     * Where the reader is, read once for the whole menu.
     *
     * Once, and handed down — not asked per row. It reads the route being
     * rendered, which is the same reason the zone above it is read here: inside
     * a Livewire update the answer is null, and a row that asked for itself
     * would be right on the first paint and wrong afterwards (ADR 0027).
     *
     * `withKey()` rather than a second reading, so a host that passed an
     * explicit `activeKey` still gets the request's URL for the other rules.
     *
     * **Protected, and that is not tidiness.** A public method on a class
     * component is handed to its view as an `InvokableComponentVariable` under
     * the same name, applied *after* the data `render()` passes — so a public
     * `active()` would shadow the object below with a lazy wrapper that has none
     * of its methods, and the first `$active->isActive(…)` in the menu would
     * fatal. `Core\Resources\View\Breadcrumbs` has the same note for the same
     * reason, found the same way.
     */
    protected function active(): ActiveNavigation
    {
        return ActiveNavigation::current()->withKey($this->activeKey);
    }

    /**
     * Whether the menu draws its filter field.
     *
     * `auto` counts what the menu would draw — every entry and every child —
     * against `wire-admin.navigation.filter_threshold`, because a filter over
     * a handful of rows is a field in the way of them.
     *
     * @param  array<string, NavigationGroup>  $groups
     */
    protected function showsFilter(array $groups): bool
    {
        $mode = config('wire-admin.navigation.filter', 'auto');

        if ($mode === 'always' || $mode === 'never') {
            return $mode === 'always';
        }

        return $this->rowCount($groups) >= (int) config('wire-admin.navigation.filter_threshold', 12);
    }

    /**
     * What the filter announces for each possible number of matches.
     *
     * Resolved here, per count, because a plural rule is the translation's and
     * not the browser's: Czech has three forms where English has two, and a
     * controller that pasted a number into one string would be wrong in one of
     * them. The menu is small, so the whole table is.
     *
     * @param  array<string, NavigationGroup>  $groups
     * @return array<int, string>
     */
    protected function filterMessages(array $groups): array
    {
        $messages = [];

        foreach (range(0, $this->rowCount($groups)) as $count) {
            $messages[$count] = trans_choice('wire-admin::messages.filter_matches', $count, ['count' => $count]);
        }

        return $messages;
    }

    /** @param  array<string, NavigationGroup>  $groups */
    private function rowCount(array $groups): int
    {
        $rows = 0;

        foreach ($groups as $group) {
            foreach ($group->getItems() as $item) {
                $rows += 1 + count($item->getChildren());
            }
        }

        return $rows;
    }

    public function render(): View
    {
        $groups = $this->groups();

        return view('wire-admin::sidebar', [
            'groups' => $groups,
            'active' => $this->active(),
            'filter' => $filter = $this->showsFilter($groups),
            'filterMessages' => $filter ? $this->filterMessages($groups) : [],
        ]);
    }
}
