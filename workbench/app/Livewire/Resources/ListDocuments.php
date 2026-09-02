<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\ListPage;
use Workbench\App\Resources\DocumentResource;

class ListDocuments extends ListPage
{
    protected static ?string $resource = DocumentResource::class;
}
