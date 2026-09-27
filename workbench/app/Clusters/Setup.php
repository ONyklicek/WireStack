<?php

declare(strict_types=1);

namespace Workbench\App\Clusters;

use NyonCode\WirePanels\Clusters\Cluster;

/**
 * A section of two screens, so the cluster driver has something real to walk:
 * one menu entry, the `setup/` prefix, and the members beside each page.
 */
class Setup extends Cluster
{
    protected static ?string $slug = 'setup';

    protected static ?string $navigationIcon = 'outline:adjustments-horizontal';

    protected static ?string $navigationGroup = 'system';

    protected static int $navigationSort = 90;
}
