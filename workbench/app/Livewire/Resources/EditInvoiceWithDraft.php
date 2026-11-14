<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

/** The invoice edit page keeping its unsaved input as a draft in the browser. */
class EditInvoiceWithDraft extends EditInvoice
{
    protected function remembersDraft(): bool
    {
        return true;
    }
}
