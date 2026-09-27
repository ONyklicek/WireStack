<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Pages\Setup;

use NyonCode\WirePanels\Pages\Page;
use Workbench\App\Clusters\Setup;

/** The second member of the Setup cluster. */
class Appearance extends Page
{
    protected static string $view = 'livewire.setup.appearance';

    protected ?string $title = 'Appearance';

    protected static ?string $slug = 'appearance';

    protected static ?string $navigationIcon = 'outline:swatch';

    protected static int $navigationSort = 20;

    protected static ?string $cluster = Setup::class;
}
