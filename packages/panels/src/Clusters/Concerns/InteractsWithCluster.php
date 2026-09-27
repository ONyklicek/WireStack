<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Clusters\Concerns;

use Illuminate\Contracts\View\View;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\Zone;
use NyonCode\WirePanels\Clusters\ClusterNavigation;
use NyonCode\WirePanels\Clusters\ClusterSubNavigation;

/**
 * A page that draws its cluster's other screens beside itself.
 *
 * Composed by every resource page (through `BelongsToResource`) and by
 * `Pages\Page`, so a member needs nothing but its `cluster()` declaration. What
 * is drawn is {@see ClusterNavigation}'s answer; this only carries the zone and
 * hands the answer to the view.
 *
 * The zone is read on mount and kept, for the reason every page keeps it: in a
 * Livewire update the route is `livewire.update`, and tabs rebuilt from that
 * would link out of the zone the person is in (ADR 0027).
 */
trait InteractsWithCluster
{
    public ?string $clusterZone = null;

    /** Livewire calls this for the trait, on mount. */
    public function mountInteractsWithCluster(): void
    {
        $this->clusterZone = Zone::current();
    }

    /** Livewire calls this for the trait, before every render: every page view gets the navigation without asking for it. */
    public function renderingInteractsWithCluster(View $view): void
    {
        $view->with('clusterNavigation', $this->clusterNavigation());
    }

    /** The cluster's members, or null for a page outside any cluster. */
    public function clusterNavigation(): ?ClusterSubNavigation
    {
        $member = $this->clusterMember();

        return $member === null ? null : app(ClusterNavigation::class)->for($member, $this->clusterZone);
    }

    /**
     * The crumb that leads back to the cluster's address, or null outside one.
     */
    protected function clusterBreadcrumb(): ?NavigationItem
    {
        $member = $this->clusterMember();
        $cluster = $member === null ? null : app(ClusterNavigation::class)->clusterOf($member);

        if ($cluster === null) {
            return null;
        }

        return NavigationItem::make($cluster::label())
            ->url(app(ResolvesPageUrls::class)->urlFor($cluster::key(), 'index', [], $this->clusterZone));
    }

    /**
     * The class whose cluster this page draws — its resource, or the page itself.
     *
     * @return class-string|null
     */
    abstract protected function clusterMember(): ?string;
}
