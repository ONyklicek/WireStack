<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Resources;

use NyonCode\WireTable\Columns\MoneyColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

/**
 * The invoice's line items, scoped to their owner.
 *
 * Written the way it was before the owner layer existed and left that way on
 * purpose: {@see InvoiceResource} only *names* it, and the edit and view pages
 * embed it. Nothing here knows a resource exists, which is the property the
 * layer promised not to break.
 */
class InvoiceItemsRelationManager extends RelationManager
{
    protected string $relationship = 'items';

    protected ?string $title = 'Line items';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('product'),
            TextColumn::make('quantity'),
            MoneyColumn::make('unit_price', 'Kč'),
        ]);
    }
}
