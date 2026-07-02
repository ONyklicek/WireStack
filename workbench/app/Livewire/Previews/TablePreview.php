<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\User;

class TablePreview extends Component
{
    use WithTable;

    public string $variant = 'overview';

    /** Variants backed by the Invoice → InvoiceItem relationship. */
    private const INVOICE_VARIANTS = [
        'subrows', 'summary',
        'subrows-flatten', 'subrows-limit', 'subrows-filter',
    ];

    /** Variants that expand only the first invoice (a single drill-down). */
    private const EXPAND_FIRST_VARIANTS = ['subrows', 'subrows-limit', 'subrows-filter'];

    /** Variants that auto-open the header-action form modal for visual QA. */
    private const MODAL_VARIANTS = ['modal-form', 'modal-slideover-mobile', 'modal-fullscreen-mobile', 'modal-wizard'];

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    /**
     * Seed expand/selection state after WithTable boots its state container,
     * so each variant renders in the state the screenshot needs.
     */
    public function booted(): void
    {
        if ($this->variant === 'selection') {
            $this->tableState->set('selection.records', User::query()
                ->orderBy('id')
                ->limit(3)
                ->pluck('id')
                ->map(fn (int $id): string => (string) $id)
                ->all());

            return;
        }

        if (in_array($this->variant, self::MODAL_VARIANTS, true)) {
            $this->openHeaderActionModal('invite');

            return;
        }

        if (in_array($this->variant, self::EXPAND_FIRST_VARIANTS, true)) {
            $this->tableState->set('rows.expanded', Invoice::query()
                ->orderBy('id')
                ->limit(1)
                ->pluck('id')
                ->map(fn (int $id): string => (string) $id)
                ->all());
        }
    }

    public function table(Table $table): Table
    {
        if (in_array($this->variant, self::INVOICE_VARIANTS, true)) {
            return $this->variant === 'summary'
                ? $this->summaryTable($table)
                : $this->subRowsTable($table);
        }

        $table = $this->usersTable($table);

        if ($this->variant === 'paginated') {
            $table->paginated()->perPage(3);
        }

        return $table;
    }

    /**
     * Sub-rows showcase. Variant tweaks: flatten, limit (show-more), and
     * filterable child rows.
     */
    private function subRowsTable(Table $table): Table
    {
        $filterable = $this->variant === 'subrows-filter';

        $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number')->label('Invoice')->sortable(),
                TextColumn::make('customer')->label('Customer'),
                BadgeColumn::make('status')->label('Status')->colors($this->statusColors()),
                $this->invoiceTotalColumn(),
            ])
            ->defaultSort('number', 'asc')
            ->paginated(false)
            ->subRows('items')
            ->subRowColumns($this->invoiceItemColumns($filterable))
            ->subRowsSortable(default: 'line_total', direction: 'desc')
            ->subRowActions([
                Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                DeleteAction::make(),
            ]);

        if ($this->variant === 'subrows-flatten') {
            $table->flattenSubRows();
        }

        if ($this->variant === 'subrows-limit') {
            $table->subRowsLimit(2);
        }

        if ($filterable) {
            $table->subRowsFilterable();
        }

        return $table;
    }

    /**
     * Summary showcase: rollup totals per invoice, a multi-aggregate footer
     * (sum + average), and the page/all scope toggle.
     */
    private function summaryTable(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number')->label('Invoice')->sortable(),
                TextColumn::make('customer')->label('Customer'),
                BadgeColumn::make('status')->label('Status')->colors($this->statusColors()),
                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->numeric(0)
                    ->alignment('right')
                    ->summarizeSum('Total items'),
                $this->invoiceTotalColumn()
                    ->summarizeAvg('Average'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                        'overdue' => 'Overdue',
                    ]),
            ])
            ->defaultSort('number', 'asc')
            ->searchable()
            ->paginated(false);
    }

    /**
     * Original users table (overview + selection variants).
     */
    private function usersTable(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->sortable(),
                BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'admin' => 'primary',
                        'manager' => 'info',
                        'editor' => 'warning',
                        'viewer' => 'gray',
                    ]),
                BooleanColumn::make('is_active')->label('Active')->sortable(),
                TextColumn::make('created_at')->label('Created')->date('M j')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ]),
            ])
            ->actions($this->variant === 'actions-group'
                ? [
                    ActionGroup::make([
                        Action::make('view')->label('View')->icon('outline:eye'),
                        Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                        Action::make('duplicate')->label('Duplicate')->icon('outline:document-duplicate'),
                        DeleteAction::make(),
                    ]),
                ]
                : [
                    Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                    DeleteAction::make(),
                ])
            ->bulkActions([
                BulkAction::make('activate')->label('Activate')->icon('check')->color('success'),
                BulkAction::make('export')->label('Export selected')->icon('outline:arrow-down-tray')->color('gray'),
                DeleteBulkAction::make(),
            ])
            ->headerActions([
                $this->inviteHeaderAction(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable()
            ->paginated(false);
    }

    /**
     * Invite action: a real modal form. The modal-* preview variants switch its
     * mobile presentation so the responsive modal surfaces can be screenshotted.
     */
    private function inviteHeaderAction(): HeaderAction
    {
        $action = HeaderAction::make('invite')
            ->label('Invite user')
            ->icon('plus')
            ->color('primary')
            ->modalHeading('Invite user')
            ->modalDescription('Send an invitation with a role and a personal note.')
            ->form([
                TextInput::make('name')->label('Name')->required(),
                TextInput::make('email')->label('Email')->required(),
                Select::make('role')->label('Role')->options([
                    'admin' => 'Administrator',
                    'manager' => 'Manager',
                    'editor' => 'Editor',
                    'viewer' => 'Viewer',
                ]),
                Textarea::make('note')->label('Personal note')->rows(4),
                Toggle::make('send_copy')->label('Send me a copy'),
            ]);

        return match ($this->variant) {
            'modal-slideover-mobile' => $action->slideOverOnMobile(),
            'modal-fullscreen-mobile' => $action->fullScreenOnMobile(),
            'modal-wizard' => $action->steps([
                ModalStep::make('Account')->schema([
                    TextInput::make('name')->label('Name')->required(),
                    TextInput::make('email')->label('Email')->required(),
                ]),
                ModalStep::make('Role')->schema([
                    Select::make('role')->label('Role')->options(['admin' => 'Administrator', 'viewer' => 'Viewer']),
                ]),
                ModalStep::make('Note')->schema([
                    Textarea::make('note')->label('Personal note')->rows(3),
                ]),
            ]),
            default => $action,
        };
    }

    /**
     * Rollup "Total" column = SUM(items.line_total) per invoice, with a
     * grand-total footer.
     */
    private function invoiceTotalColumn(): TextColumn
    {
        return TextColumn::make('items_total')
            ->label('Total')
            ->sums('items', 'line_total')
            ->numeric(0)
            ->suffix(' Kč')
            ->alignment('right')
            ->summaryDecimals(0)
            ->summarizeSum('Grand total');
    }

    /**
     * @return array<int, TextColumn>
     */
    private function invoiceItemColumns(bool $filterable): array
    {
        $product = TextColumn::make('product')->label('Product');

        if ($filterable) {
            $product->filterable();
        }

        return [
            $product,
            TextColumn::make('quantity')->label('Qty')->numeric(0)->alignment('right'),
            TextColumn::make('unit_price')->label('Unit')->numeric(0)->suffix(' Kč')->alignment('right'),
            TextColumn::make('line_total')
                ->label('Line total')
                ->numeric(0)
                ->suffix(' Kč')
                ->alignment('right')
                ->summaryDecimals(0)
                ->summarizeSum('Subtotal', scope: 'subRows'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusColors(): array
    {
        return [
            'paid' => 'success',
            'pending' => 'warning',
            'overdue' => 'danger',
        ];
    }

    public function render()
    {
        return view('livewire.previews.table-preview');
    }
}
