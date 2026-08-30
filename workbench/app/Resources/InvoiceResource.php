<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Livewire\Resources\InvoiceItemsRelationManager;
use Workbench\App\Models\Invoice;

/**
 * The workbench's own resource, on a real entity with real data.
 *
 * V2.3's plan asked for exactly this before the API was declared finished —
 * "prototype R.1 on one real entity" — because a contract set is only proven by
 * a consumer, and every other exercise of it so far has been a test fixture.
 *
 * It declares every surface deliberately: all five contracts on one class is the
 * case most likely to expose a clash between them, and the previews render it
 * through the real pages rather than through anything the workbench invents.
 */
final class InvoiceResource implements DescribesResource, ProvidesNavigation, ProvidesRelationManagers, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    public static function modelClass(): ?string
    {
        return Invoice::class;
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()
            ->icon('outline:document-text')
            ->group('Billing')
            ->sort(10)
            ->badge(fn (): int => Invoice::where('status', 'overdue')->count(), 'danger');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('customer')->searchable(),
                BadgeColumn::make('status'),
                TextColumn::make('issued_at')->dateTime('d.m.Y'),
            ])
            ->defaultSort('number');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('number')->required(),
            TextInput::make('customer')->required(),
            Select::make('status')->options([
                'draft' => 'Draft',
                'sent' => 'Sent',
                'paid' => 'Paid',
                'overdue' => 'Overdue',
            ]),
            DateTimePicker::make('issued_at'),
        ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('number'),
            TextEntry::make('customer'),
            TextEntry::make('status')->badge(),
            TextEntry::make('issued_at')->dateTime('d.m.Y'),
        ]);
    }

    public function relationManagers(): array
    {
        return [InvoiceItemsRelationManager::class];
    }
}
