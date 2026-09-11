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
     */
    public function __construct(
        public bool $linkedOnly = false,
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

    public function render(): View
    {
        return view('wire-admin::sidebar', [
            'groups' => $this->groups(),
            'active' => $this->active(),
        ]);
    }
}
