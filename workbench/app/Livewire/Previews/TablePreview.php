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
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\Textarea;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\ImageColumn;
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
        'subrows', 'summary', 'row-color',
        'subrows-flatten', 'subrows-limit', 'subrows-filter',
    ];

    /** Variants that expand only the first invoice (a single drill-down). */
    private const EXPAND_FIRST_VARIANTS = ['subrows', 'subrows-limit', 'subrows-filter'];

    /** Variants that auto-open the header-action form modal for visual QA. */
    private const MODAL_VARIANTS = ['modal-form', 'modal-slideover-mobile', 'modal-slideover-compose', 'modal-fullscreen-mobile', 'modal-wizard', 'modal-nested'];

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;

        // Verification aid: the *-tablet variants raise the mobile-sheet
        // breakpoint to md so tablet-width screenshots show the sheet.
        if (str_ends_with($variant, '-tablet')) {
            config(['wire-core.mobile.breakpoint' => 'md']);
            $this->variant = str_replace('-tablet', '', $variant);
        }
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
            // booted() runs on every request; only open the modal once, or a
            // stacked/nested modal would be re-opened and its form reset on each
            // roundtrip. No open frames = nothing mounted yet.
            if ($this->actionFrameCount() === 0) {
                $this->openHeaderActionModal('invite');
            }

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
            return match ($this->variant) {
                'summary' => $this->summaryTable($table),
                'row-color' => $this->rowColorTable($table),
                default => $this->subRowsTable($table),
            };
        }

        if ($this->variant === 'column-filters') {
            return $this->columnFiltersTable($table);
        }

        if ($this->variant === 'image-gallery') {
            return $this->imageGalleryTable($table);
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
     * Conditional whole-row coloring: each invoice row is tinted by its status
     * (overdue → danger, pending → warning, paid → success) via a rowColor()
     * Closure, plus a rowClass() Closure emphasising overdue rows.
     */
    private function rowColorTable(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number')->label('Invoice')->sortable(),
                TextColumn::make('customer')->label('Customer'),
                BadgeColumn::make('status')->label('Status')->colors($this->statusColors()),
                $this->invoiceTotalColumn(),
            ])
            ->rowColor(fn (Invoice $record) => match ($record->status) {
                'overdue' => 'danger',
                'pending' => 'warning',
                'paid' => 'success',
                default => null,
            })
            ->rowClass(fn (Invoice $record) => $record->status === 'overdue' ? 'font-semibold' : null)
            ->defaultSort('number', 'asc')
            ->striped()
            ->stackedOnMobile()
            ->paginated(false);
    }

    /**
     * Per-column header filters showcase: a text filter, a single-select, the
     * new multi-select (checkbox dropdown), and a boolean filter — each rendered
     * inline in the header row.
     */
    private function columnFiltersTable(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')->label('Name')->sortable()
                    ->filterable(), // text filter (LIKE)
                TextColumn::make('email')->label('Email')
                    ->filterable(),
                BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'admin' => 'primary',
                        'manager' => 'info',
                        'editor' => 'warning',
                        'viewer' => 'gray',
                    ])
                    ->filterAsMultiSelect([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ], 'Any role'), // NEW: pick several roles → whereIn
                BooleanColumn::make('is_active')->label('Active')
                    ->filterAsBoolean('Active', 'Inactive'),
            ])
            ->searchable(false)
            ->paginated(false);
    }

    /**
     * Original users table (overview + selection variants).
     */
    /**
     * ImageColumn gallery: an array state renders every image, stacked ones
     * overlap and a stackLimit summarises the rest as a "+N" chip.
     */
    private function imageGalleryTable(Table $table): Table
    {
        // Deterministic, dependency-free avatars — an <svg> data URI, so the
        // preview never waits on (or is broken by) a remote image host.
        $avatar = static fn (string $hue): string => 'data:image/svg+xml;utf8,'.rawurlencode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="'.$hue.'"/></svg>'
        );

        $palette = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7'];

        return $table
            ->model(User::class)
            ->columns([
                TextColumn::make('name')->label('Name'),
                ImageColumn::make('single')
                    ->label('Single')
                    ->circular()
                    ->state(fn ($record) => $avatar($palette[0])),
                ImageColumn::make('gallery')
                    ->label('Gallery')
                    ->state(fn ($record) => array_map($avatar, array_slice($palette, 0, 3))),
                ImageColumn::make('team')
                    ->label('Stacked (limit 3)')
                    ->circular()
                    ->stacked()
                    ->stackLimit(3)
                    // 6 avatars against a limit of 3 => three images plus "+3".
                    ->state(fn ($record) => array_map($avatar, $palette)),
            ])
            ->searchable(false)
            ->paginated(false);
    }

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
            ->actions(match ($this->variant) {
                'actions-group' => [
                    ActionGroup::make([
                        Action::make('view')->label('View')->icon('outline:eye'),
                        Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                        Action::make('duplicate')->label('Duplicate')->icon('outline:document-duplicate'),
                        DeleteAction::make(),
                    ]),
                ],
                // Quiet row actions: neutral at rest, colour on hover/focus. The
                // green "Approve" uses ->solid() to stay prominent despite the
                // quiet table; Delete stays legible red at rest.
                'actions-quiet' => [
                    Action::make('view')->label('View')->icon('outline:eye'),
                    Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                    Action::make('approve')->label('Approve')->icon('check')->color('success')->solid(),
                    DeleteAction::make(),
                ],
                default => [
                    Action::make('edit')->label(fn ($r) => "Edit $r->name")->icon('pencil')->color('primary'),
                    DeleteAction::make(),
                ],
            })
            ->actionsStyle($this->variant === 'actions-quiet' ? 'quiet' : 'solid')
            ->bulkActions([
                BulkAction::make('activate')->label('Activate')->icon('check')->color('success'),
                BulkAction::make('export')->label('Export selected')->icon('outline:arrow-down-tray')->color('gray'),
                DeleteBulkAction::make(),
            ])
            ->headerActions($this->variant === 'modal-nested'
                ? [$this->inviteHeaderAction(), $this->quickRoleHeaderAction()]
                : [$this->inviteHeaderAction()])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable()
            // Right-click a row → a dedicated context menu (declared separately
            // from the ->actions() toolbar above).
            ->rowContextMenu([
                Action::make('view')->label('View')->icon('outline:eye'),
                Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                Action::make('duplicate')->label('Duplicate')->icon('outline:document-duplicate'),
                DeleteAction::make(),
            ])
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
            'modal-slideover-compose' => $action->slideOver()->slideOverOnMobile(),
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
            // A footer action that stacks a second modal on top of "Invite user".
            'modal-nested' => $action->modalFooterActions([
                ModalFooterAction::make('quickRole')
                    ->label('Add a role first')
                    ->icon('plus')
                    ->color('gray')
                    ->action(fn ($component) => $component->openHeaderActionModal('quickRole')),
            ]),
            default => $action,
        };
    }

    /**
     * A small secondary modal, opened *on top of* the invite modal, to
     * demonstrate stacked (nested) modals.
     */
    private function quickRoleHeaderAction(): HeaderAction
    {
        return HeaderAction::make('quickRole')
            ->label('Quick role')
            ->modalHeading('New role')
            ->modalDescription('This modal is stacked on top of "Invite user".')
            ->form([
                TextInput::make('role_name')->label('Role name')->required(),
                Textarea::make('role_desc')->label('Description')->rows(3),
            ]);
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
