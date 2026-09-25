<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WirePanels\Resources\ListTab;
use NyonCode\WirePanels\Resources\Pages\ListPage;
use Workbench\App\Resources\InvoiceResource;

class ListInvoices extends ListPage
{
    protected static ?string $resource = InvoiceResource::class;

    /** The invoice list, by where an invoice stands — each tab counted. */
    protected function tabs(): array
    {
        return [
            ListTab::make('all')->showCount(),
            ListTab::make('pending')->query(fn (Builder $query) => $query->where('status', 'pending'))->showCount(),
            ListTab::make('paid')->query(fn (Builder $query) => $query->where('status', 'paid'))->showCount(),
            ListTab::make('overdue')
                ->icon('outline:exclamation-triangle')
                ->badgeColor('danger')
                ->query(fn (Builder $query) => $query->where('status', 'overdue'))
                ->showCount(),
        ];
    }
}
