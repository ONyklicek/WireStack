<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\ManagePage;
use Workbench\App\Resources\TeamResource;

class ManageTeams extends ManagePage
{
    protected static ?string $resource = TeamResource::class;
}
