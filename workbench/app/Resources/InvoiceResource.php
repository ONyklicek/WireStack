<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Actions\TransitionAction;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Workflow\WorkflowState;
use NyonCode\WireCore\Foundation\Routing\Contracts\ConfiguresRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;
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
use Workbench\App\Enums\InvoiceStatus;
use Workbench\App\Livewire\Resources\CreateInvoice;
use Workbench\App\Livewire\Resources\EditInvoice;
use Workbench\App\Livewire\Resources\InvoiceItemsRelationManager;
use Workbench\App\Livewire\Resources\ListInvoices;
use Workbench\App\Livewire\Resources\ViewInvoice;
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
final class InvoiceResource implements ConfiguresRoutes, DescribesResource, GloballySearchable, ProvidesNavigation, ProvidesPages, ProvidesRelationManagers, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
{
    use DescribesRecords;

    /**
     * The pages that render this resource, and therefore its routes.
     *
     * Four `Route::get()` lines and a hand-written key→URL map used to live in
     * the workbench's route file for exactly this. The edit page carries a
     * permission the others do not, which is the case the per-page shape exists
     * for — and it lands as Laravel's own `can:` middleware, so Gate answers it
     * the way it answers every other surface here.
     */
    public static function pages(): array
    {
        return [
            'index' => ListInvoices::class,
            'create' => CreateInvoice::class,
            'view' => ViewInvoice::class,
            'edit' => RoutePage::make(EditInvoice::class)->permission('invoices.update'),
        ];
    }

    /**
     * Nothing unusual: the defaults are the resource key as the prefix and the
     * surrounding group's middleware and domain. Declared anyway because this is
     * the prototype — an application with a tenant-per-domain setup puts
     * `'{tenant}.example.test'` here and the parameter reaches its own
     * TenantResolver like any other route parameter.
     */
    public static function routeMiddleware(): array
    {
        return [];
    }

    public static function routeDomain(): ?string
    {
        return null;
    }

    public static function routePrefix(): ?string
    {
        return null;
    }

    public static function modelClass(): ?string
    {
        return Invoice::class;
    }

    /**
     * V2.5 GS: what the command palette matches a term against.
     *
     * The same two columns the table marks searchable, and deliberately not the
     * status — a palette that answered "overdue" with every overdue invoice is a
     * report, not a jump-to.
     */
    public static function globallySearchableAttributes(): array
    {
        return ['number', 'customer'];
    }

    public static function toGlobalSearchResult(object $record): GlobalSearchResult
    {
        return new GlobalSearchResult(
            resourceKey: self::key(),
            recordKey: $record->getKey(),
            title: $record->number,
            subtitle: $record->customer.' · '.$record->status,
            icon: 'outline:document-text',
        );
    }

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make()
            ->icon('outline:document-text')
            ->group('billing')
            ->sort(10)
            ->badge(fn (): int => Invoice::where('status', 'overdue')->count(), 'danger');
    }

    /**
     * V2.6 step 4's measurement, on a real entity: what an application does with
     * a workflow, and how many places need the same machine.
     *
     * A static method on the resource, because that is where the answer already
     * lives — the resource is what says which entity this is and what its
     * surfaces are, and a machine over its status column is one more of those.
     * Whether the framework should instead hold a registry of these is exactly
     * what building this was meant to answer.
     */
    public static function workflow(): WorkflowState
    {
        return WorkflowState::for(InvoiceStatus::class)
            ->column('status')
            ->allow(InvoiceStatus::Draft, InvoiceStatus::Pending)
            ->allow([InvoiceStatus::Pending, InvoiceStatus::Overdue], InvoiceStatus::Paid)
            ->allow(InvoiceStatus::Pending, InvoiceStatus::Overdue)
            // Reopening a paid invoice is a real thing (a payment posted in
            // error), and it is also what keeps the graph a cycle — a driver
            // that could only move an invoice forward would leave the shared
            // workbench database one state further along on every run.
            ->allow(InvoiceStatus::Paid, InvoiceStatus::Pending)
            // An invoice with no lines cannot be paid — the domain's rule, which
            // is why the machine takes a closure rather than modelling it.
            ->guard(InvoiceStatus::Paid, fn (Invoice $record): bool => $record->items()->exists());
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
            ->actions([
                TransitionAction::to(InvoiceStatus::Paid)->workflow(self::workflow()),
                TransitionAction::to(InvoiceStatus::Overdue)->workflow(self::workflow()),
                TransitionAction::to(InvoiceStatus::Pending)->workflow(self::workflow()),
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
