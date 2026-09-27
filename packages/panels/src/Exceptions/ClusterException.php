<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WirePanels\Clusters\Cluster;

/**
 * A class that names a cluster it cannot belong to.
 *
 * Refused when routes are registered rather than quietly routed at the root:
 * a member that missed its cluster is a page at the wrong URL, out of the
 * section's sub-navigation and out from under its permission, and nothing on
 * the screen would say so.
 */
final class ClusterException extends InvalidArgumentException implements WireException
{
    public static function notACluster(string $member, string $cluster): self
    {
        return new self("[{$member}] names [{$cluster}] as its cluster, which does not extend ".Cluster::class.'.');
    }

    public static function notRegistered(string $member, string $cluster): self
    {
        return new self(
            "[{$member}] names [{$cluster}] as its cluster, and that cluster is not registered. ".
            "A cluster is a page: list it in config('wire-panels.pages') or discover its folder."
        );
    }

    public static function nested(string $cluster, string $outer): self
    {
        return new self(
            "The cluster [{$cluster}] names [{$outer}] as its own cluster. A cluster is one level: ".
            'put its members in the outer cluster directly.'
        );
    }
}
