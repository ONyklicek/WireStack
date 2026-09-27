<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\ListPage;
use Workbench\App\Resources\ProjectResource;

class ListProjects extends ListPage
{
    protected static ?string $resource = ProjectResource::class;
}
