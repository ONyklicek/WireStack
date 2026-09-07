<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Tests\Fixtures;

use NyonCode\WirePanels\Resources\Pages\ViewPage;

class ViewInvoice extends ViewPage
{
    protected static ?string $resource = InvoiceResource::class;
}
