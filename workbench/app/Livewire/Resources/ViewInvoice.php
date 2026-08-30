<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\ViewPage;
use Workbench\App\Resources\InvoiceResource;

class ViewInvoice extends ViewPage
{
    protected static ?string $resource = InvoiceResource::class;
}
