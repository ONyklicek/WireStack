<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WirePanels\Resources\Pages\EditPage;
use Workbench\App\Resources\InvoiceResource;

class EditInvoice extends EditPage
{
    protected static ?string $resource = InvoiceResource::class;
}
