<?php

declare(strict_types=1);

namespace Workbench\App\Resources;

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\TransitionAction;
use NyonCode\WireCore\Core\Resources\Concerns\DescribesRecords;
use NyonCode\WireCore\Core\Resources\Contracts\DescribesResource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Workflow\WorkflowState;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Contracts\ProvidesCommands;
use NyonCode\WireCore\Foundation\Routing\Contracts\ConfiguresRoutes;
use NyonCode\WireCore\Foundation\Routing\Contracts\ProvidesPages;
use NyonCode\WireCore\Foundation\Routing\Contracts\ResolvesPageUrls;
use NyonCode\WireCore\Foundation\Routing\RoutePage;
use NyonCode\WireCore\GlobalSearch\Contracts\GloballySearchable;
use NyonCode\WireCore\GlobalSearch\GlobalSearchResult;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Contracts\ProvidesResourceInfolist;
use NyonCode\WireCore\Infolists\Infolist;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\TiptapEditor;
use NyonCode\WireForms\Contracts\ProvidesResourceForm;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireModuleMedia\Forms\MediaField;
use NyonCode\WirePanels\Resources\Contracts\ProvidesRelationManagers;
use NyonCode\WirePanels\Resources\Contracts\ProvidesResourceTable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;
use Workbench\App\Enums\InvoiceStatus;
use Workbench\App\Livewire\Resources\CreateInvoice;
use Workbench\App\Livewire\Resources\EditInvoice;
use Workbench\App\Livewire\Resources\InvoiceHistory;
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
final class InvoiceResource implements ConfiguresRoutes, DescribesResource, GloballySearchable, ProvidesCommands, ProvidesNavigation, ProvidesPages, ProvidesRelationManagers, ProvidesResourceForm, ProvidesResourceInfolist, ProvidesResourceTable
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
     *
     * `history` is the other shape: a page the framework does not know, at a URI
     * of the resource's own. The `{record}` in it is the whole declaration —
     * the router builds the parameter from it, and the record's tab bar reads
     * the same URI to decide that this page belongs in it.
     */
    public static function pages(): array
    {
        return [
            'index' => ListInvoices::class,
            'create' => CreateInvoice::class,
            'view' => ViewInvoice::class,
            'edit' => RoutePage::make(EditInvoice::class)->permission('invoices.update'),

            // A page of the application's own, about one record: the `{record}`
            // in the URI is what makes the router pass one — and what puts the
            // page in the record's sub-navigation beside `view` and `edit`.
            'history' => RoutePage::make(InvoiceHistory::class)
                ->uri('{record}/history')
                ->icon('outline:clock')
                ->sort(30),
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
    /**
     * What the command palette may offer for invoices.
     *
     * Both shapes on purpose, because the palette treats them differently and
     * the browser driver has to see both: `mark-seen` has nothing to ask and is
     * run where it stands, while `archive` carries a confirmation and therefore
     * has to be handed to a page that owns a modal.
     *
     * The runnable one writes to the cache rather than to the invoice, so the
     * driver can assert that it *ran* without depending on a column that other
     * previews also write.
     *
     * @return array<int, ActionContract>
     */
    public static function commands(?object $record = null): array
    {
        if ($record !== null) {
            return [
                Action::make('mark-seen')
                    ->label('Mark invoice seen')
                    ->icon('outline:eye')
                    ->action(fn () => cache()->put('workbench.invoice.seen', $record->getKey(), 60)),
                Action::make('archive')
                    ->label('Archive invoice')
                    ->icon('outline:archive-box')
                    ->requiresConfirmation(),
            ];
        }

        return [
            Action::make('recount-invoices')
                ->label('Recount invoices')
                ->icon('outline:calculator')
                ->action(fn () => cache()->put('workbench.invoices.recounted', true, 60)),
        ];
    }

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
            ->badge(fn (): int => Invoice::where('status', 'overdue')->count(), 'danger')
            // The workbench's one submenu, and the reason it is here: a second
            // level that nothing renders is a second level nobody notices is
            // broken. The children are filtered views of the same list, which is
            // what a submenu is actually for.
            ->children([
                NavigationItem::make('All invoices')->url(fn (): ?string => self::listUrl()),
                NavigationItem::make('Overdue')->url(fn (): ?string => self::listUrl('overdue'))->sort(10),
                NavigationItem::make('Paid')->url(fn (): ?string => self::listUrl('paid'))->sort(20),
            ]);
    }

    /**
     * The list, optionally narrowed — the URL the submenu entries point at.
     *
     * Asked of the URL resolver rather than of `route()`, for the same reason
     * every other entry is: an application that registers this resource and
     * routes nothing gets null here and an unlinked row, instead of an exception
     * while the menu renders.
     */
    private static function listUrl(?string $status = null): ?string
    {
        $url = app(ResolvesPageUrls::class)->urlFor(self::key());

        if ($url === null || $status === null) {
            return $url;
        }

        return $url.'?status='.$status;
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

            // The two seams the media module adds to the rest of the system,
            // exercised on a real form rather than only in a preview: a field
            // that attaches library rows to this record, and an editor whose
            // image button opens the same library instead of asking for a URL.
            MediaField::make('attachments')
                ->label('Attachments')
                ->multiple()
                ->helperText('Chosen from the media library — the file is not copied.'),

            TiptapEditor::make('notes')
                ->label('Notes')
                ->withImages()
                ->helperText('The image button opens the media library.'),
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
