<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\CreatePage;
use Workbench\App\Resources\InvoiceResource;

class CreateInvoice extends CreatePage
{
    protected static ?string $resource = InvoiceResource::class;
}
