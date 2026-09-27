<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Pages\Setup;

use NyonCode\WirePanels\Pages\Page;
use Workbench\App\Clusters\Setup;

/** The first member of the Setup cluster. */
class General extends Page
{
    protected static string $view = 'livewire.setup.general';

    protected ?string $title = 'General';

    protected static ?string $slug = 'general';

    protected static ?string $navigationIcon = 'outline:cog-6-tooth';

    protected static int $navigationSort = 10;

    protected static ?string $cluster = Setup::class;
}
