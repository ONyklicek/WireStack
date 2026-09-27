<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Clusters;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Foundation\Registration\Catalog;
use NyonCode\WireCore\Foundation\Routing\Contracts\AuthorizesUrls;
use NyonCode\WireCore\Foundation\Routing\Contracts\BelongsToCluster;
use NyonCode\WirePanels\Exceptions\ClusterException;
use NyonCode\WirePanels\Resources\Contracts\NestedResource;

/**
 * Everything a cluster knows about its members, asked in one place.
 *
 * The router asks which cluster a class is routed inside, the cluster's own
 * address asks where to send somebody, and every member page asks what to draw
 * beside itself. Three readers, one answer each — so a member the router put
 * under `settings/` cannot be missing from the settings tabs (ADR 0039).
 *
 * The tabs are the members' own menu entries, read from {@see Workspace::items()}:
 * the label fallback, the routed URL, the visibility and the `navigation.building`
 * hook have all been applied there already, and a second reading of the same
 * declarations would be the copy that drifts.
 */
final readonly class ClusterNavigation
{
    public function __construct(
        private Catalog $catalog,
        private Workspace $workspace,
        private AuthorizesUrls $access,
    ) {}

    /**
     * The cluster a class sits inside, or null — refusing one it cannot be in.
     *
     * A nested resource sits wherever its parent does: its pages are routed
     * under the parent's record, and so under the parent's prefix already.
     *
     * @param  class-string  $class
     * @return class-string<Cluster>|null
     *
     * @throws ClusterException When it names something that is not a registered, top-level cluster.
     */
    public function clusterOf(string $class): ?string
    {
        if (is_subclass_of($class, NestedResource::class)) {
            return $this->clusterOf($class::parentResource());
        }

        $cluster = is_subclass_of($class, BelongsToCluster::class) ? $class::cluster() : null;

        if ($cluster === null) {
            return null;
        }

        if (! is_subclass_of($cluster, Cluster::class)) {
            throw ClusterException::notACluster($class, $cluster);
        }

        $outer = $cluster::cluster();

        if ($outer !== null) {
            throw ClusterException::nested($cluster, $outer);
        }

        if (! in_array($cluster, $this->catalog->all(), true)) {
            throw ClusterException::notRegistered($class, $cluster);
        }

        return $cluster;
    }

    /**
     * Every registered class that names this cluster, by key, in registration order.
     *
     * @param  class-string<Cluster>  $cluster
     * @return array<string, class-string>
     */
    public function members(string $cluster): array
    {
        return array_filter(
            $this->catalog->implementing(BelongsToCluster::class),
            static fn (string $class): bool => $class::cluster() === $cluster,
        );
    }

    /**
     * The members' menu entries — the cluster's sub-navigation — in `sort()` order.
     *
     * @param  class-string<Cluster>  $cluster
     * @return array<string, NavigationItem> Keyed by registered key.
     */
    public function items(string $cluster, ?string $zone): array
    {
        return array_intersect_key($this->workspace->items($zone), $this->members($cluster));
    }

    /**
     * The first member this person may open, in the order the tabs draw them.
     *
     * @param  class-string<Cluster>  $cluster
     */
    public function landing(string $cluster, ?string $zone, ?Authenticatable $user): ?string
    {
        foreach ($this->items($cluster, $zone) as $item) {
            $url = $item->getUrl();

            if ($url !== null && $this->access->allowsUrl($url, $user)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * What a member page draws beside its content, or null outside a cluster.
     *
     * @param  class-string  $member  The class the page is about — a resource, or the page itself.
     */
    public function for(string $member, ?string $zone): ?ClusterSubNavigation
    {
        $cluster = $this->clusterOf($member);

        if ($cluster === null) {
            return null;
        }

        return new ClusterSubNavigation(
            label: $cluster::label(),
            items: $this->items($cluster, $zone),
            position: $cluster::subNavigationPosition(),
            currentKey: $this->memberKey($member, $cluster),
        );
    }

    /**
     * The key of the member a page belongs to — a nested resource's parent's.
     *
     * @param  class-string  $class
     * @param  class-string<Cluster>  $cluster
     */
    private function memberKey(string $class, string $cluster): ?string
    {
        while (is_subclass_of($class, NestedResource::class)) {
            $class = $class::parentResource();
        }

        return array_search($class, $this->members($cluster), true) ?: null;
    }
}
