<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WirePanels\Resources\Pages\ViewPage;
use Workbench\App\Resources\InvoiceResource;

class ViewInvoice extends ViewPage
{
    protected static ?string $resource = InvoiceResource::class;

    /**
     * One header action that has to ask first, so the preview shows a record
     * page opening a confirmation from beside its heading — and the driver can
     * prove the page's own action host answers the click.
     */
    protected function headerActions(): array
    {
        return [
            Action::make('remind')
                ->label('Send reminder')
                ->icon('outline:bell')
                ->requiresConfirmation()
                ->successNotification('Reminder queued')
                ->action(fn () => null),
        ];
    }
}
