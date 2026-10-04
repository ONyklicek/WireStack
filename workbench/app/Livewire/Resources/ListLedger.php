<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\ListPage;
use Workbench\App\Resources\LedgerResource;

class ListLedger extends ListPage
{
    protected static ?string $resource = LedgerResource::class;
}
