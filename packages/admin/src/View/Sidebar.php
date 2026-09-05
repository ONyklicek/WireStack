<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
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

    public function render(): View
    {
        return view('wire-admin::sidebar', [
            'groups' => $this->groups(),
        ]);
    }
}
