<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAudit\Tests\Fixtures;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;

/** The application's own resource for the audited model — what makes the trail linkable. */
class InvoiceResource implements DescribesResource, ProvidesPages, ProvidesResourceInfolist
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Invoice::class;
    }

    public static function pages(): array
    {
        return ['view' => ViewInvoice::class];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([TextEntry::make('number')]);
    }
}
