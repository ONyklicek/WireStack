<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\ListPage;
use Workbench\App\Resources\TaskResource;

class ListTasks extends ListPage
{
    protected static ?string $resource = TaskResource::class;
}
