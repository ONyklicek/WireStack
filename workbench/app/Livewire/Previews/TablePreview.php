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
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Support\RecordAction;
use NyonCode\WireTable\Table;
use Workbench\App\Models\GestureRow;
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

    /** Whether the one-off expansion seed above has already been applied. */
    public bool $expansionSeeded = false;

    /** Variants that auto-open the header-action form modal for visual QA. */
    private const MODAL_VARIANTS = ['modal-form', 'modal-slideover-mobile', 'modal-slideover-compose', 'modal-fullscreen-mobile', 'modal-wizard', 'modal-nested'];

    /** Variants backed by the GestureRow selection-gesture fixtures. */
    private const GESTURE_VARIANTS = ['selection-gestures', 'selection-gestures-paged', 'selection-only'];

    /**
     * Variants of the users table that exist to show whole-row interaction, and
     * therefore ask for the (opt-in) gesture layer. Every other users-table
     * variant stays on the shipped default on purpose — a preview of an
     * ordinary table should look like one.
     */
    private const RECORD_ACTION_VARIANTS = ['record-actions', 'record-actions-dual', 'record-actions-keyboard'];

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
        if (in_array($this->variant, ['selection', 'stacked-selection'], true)) {
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
            // booted() runs on every request; seed once, or the screenshot state
            // is re-applied on each roundtrip and the preview cannot be clicked
            // (every expand/collapse would be undone before it renders).
            if ($this->expansionSeeded) {
                return;
            }

            $this->expansionSeeded = true;

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

        if ($this->variant === 'empty-state') {
            return $this->emptyStateTable($table);
        }

        if ($this->variant === 'image-gallery') {
            return $this->imageGalleryTable($table);
        }

        if (in_array($this->variant, ['editable-fill', 'editable-fill-selectable', 'editable-fill-paged'], true)) {
            return $this->editableFillTable($table);
        }

        if (in_array($this->variant, self::GESTURE_VARIANTS, true)) {
            return $this->gestureTable($table);
        }

        $table = $this->usersTable($table);

        if ($this->variant === 'paginated') {
            $table->paginated()->perPage(3);
        }

        // Stacked cards + selection + more rows than one page: what the card
        // select-all strip, the "select all matching" escalation and the mobile
        // sort control need in order to be visible at all. Applied after
        // usersTable(), whose chain ends with ->paginated(false).
        if ($this->variant === 'stacked-selection') {
            // Sorted by id so the first page is the same two records the
            // pre-seeded selection in booted() picks. Without it the page order
            // comes from timestamps the seeder generates relative to today, and
            // whether the strip starts fully checked or mixed changes with the
            // day the database was rebuilt.
            $table->stackedOnMobile()->paginated()->perPage(2)->defaultSort('id', 'asc');
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
            ->stackedOnMobile()
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
            ->stackedOnMobile()
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
     * The empty state with a way out of it. Three surfaces in one preview: a
     * link action (static url), an inline one, and one that opens a modal form
     * — all record-less, and all repeated in the stacked card layout, which is
     * what the phone width shows.
     */
    private function emptyStateTable(Table $table): Table
    {
        return $table
            // No user has this role, so the table is genuinely empty rather
            // than emptied by a filter — the state the actions belong to.
            ->query(User::query()->whereRaw('1 = 0'))
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('email')->label('Email'),
            ])
            ->searchable(false)
            ->paginated(false)
            ->stackedOnMobile()
            ->emptyState(
                heading: 'No users yet',
                description: 'Invite the first one to get started.',
                icon: 'outline:users',
            )
            ->emptyStateActions([
                Action::make('docs')
                    ->label('Read the guide')
                    ->icon('outline:book-open')
                    ->outlined()
                    ->url('/docs'),
                Action::make('inviteFirst')
                    ->label('Invite a user')
                    ->icon('outline:plus')
                    ->form(fn () => [
                        TextInput::make('name')->label('Name')->required(),
                        TextInput::make('email')->label('Email')->email()->required(),
                    ])
                    ->modalHeading('Invite a user')
                    ->action(fn () => null),
            ]);
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

    /**
     * Inline editing plus the Excel-style fill handle.
     *
     * Every editable column type is present because each dehydrates its state
     * differently and the drag must survive all three; `email` opts out with
     * ->fillable(false), so the handle must never appear on it.
     *
     * The `editable-fill-selectable` variant adds `selectable()` on top. It is a
     * fixture in its own right, not a nicer version of the other one: the fill
     * handle wraps the table in an x-data INSIDE the selection root, so every
     * selection expression on this table evaluates in a scope stack whose
     * innermost component is the fill handle — and both gestures drag over the
     * same rows with the same pointer. Nothing but a browser can tell whether
     * the two stay out of each other's way.
     *
     * The `editable-fill-paged` variant adds pagination, which no other editable
     * fixture had. Inline editing and a page-size select are what makes one
     * Livewire commit carry both a state change and a call that answers with no
     * HTML: `updateTableCell()` skips the render on purpose, and a per-page
     * change riding along in that same commit used to be swallowed with it.
     */
    private function editableFillTable(Table $table): Table
    {
        $table
            ->model(User::class)
            ->gestures()
            ->fillHandle()
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextInputColumn::make('email')->label('Email (not fillable)')->fillable(false),
                SelectColumn::make('role')
                    ->label('Role')
                    ->options(['admin' => 'Administrator', 'editor' => 'Editor', 'viewer' => 'Viewer']),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->searchable(false)
            ->paginated(false);

        if ($this->variant === 'editable-fill-paged') {
            // Two page sizes over the four seeded users, so switching visibly
            // doubles the rows on screen — a driver can tell the change landed
            // apart from anything else that might re-render the table.
            $table
                ->paginated()
                ->perPage(2)
                ->perPageOptions([2, 4])
                ->defaultSort('id', 'asc');
        }

        if ($this->variant === 'editable-fill-selectable') {
            $table
                ->selectable()
                ->bulkActions([
                    BulkAction::make('activate')->label('Activate')->icon('check')->color('success'),
                    BulkAction::make('archive')->label('Archive')->icon('outline:archive-box')->color('warning'),
                ]);
        }

        return $table;
    }

    /**
     * Selection-gesture fixtures: 40 deterministic rows, checkboxes, a bulk bar
     * and mobile cards. `selection-gestures` shows every row on one page (the
     * document scrolls), `selection-gestures-paged` splits them 20 a page, and
     * `selection-only` is selectable *without* record actions — the variant
     * that proves grid semantics attach to `selectable()` itself, not to the
     * record actions every other selectable preview happens to carry.
     *
     * All of them call `gestures()`: the layer is opt-in, and these fixtures are
     * what the drivers point at to prove it works when asked for.
     */
    private function gestureTable(Table $table): Table
    {
        $table
            ->gestures()
            ->model(GestureRow::class)
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'new' => 'info',
                        'active' => 'success',
                        'paused' => 'warning',
                        'archived' => 'gray',
                    ]),
                TextColumn::make('amount')->label('Amount')->sortable(),
            ])
            ->searchable()
            ->selectable()
            ->stackedOnMobile()
            ->bulkActions([
                BulkAction::make('activate')->label('Activate')->icon('check')->color('success'),
                BulkAction::make('archive')->label('Archive')->icon('outline:archive-box')->color('warning'),
                BulkAction::make('export')->label('Export selected')->icon('outline:arrow-down-tray')->color('gray'),
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');

        if ($this->variant === 'selection-gestures-paged') {
            $table->paginated()->perPage(20);
        } else {
            $table->paginated(false);
        }

        if ($this->variant === 'selection-only') {
            return $table;
        }

        return $table->recordActions([
            RecordAction::make(
                Action::make('open')
                    ->label('Open')
                    ->icon('outline:eye')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($r) => "Opened {$r->name}")
                    ->modalDescription('Enter (or a double-click) runs the primary record action against the active row.')
                    ->action(fn () => null)
            )->onDoubleClick(),
            RecordAction::make(
                Action::make('archive-one')
                    ->label('Archive')
                    ->icon('outline:archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($r) => "Archive {$r->name}?")
                    ->modalDescription('Fired by the Delete key against the active row — an onKey() binding.')
                    ->action(fn () => null)
            )->onKey('Delete'),
            RecordAction::make(
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('outline:document-duplicate')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($r) => "Duplicate {$r->name}?")
                    ->action(fn () => null)
            )->onContextMenu(),
        ]);
    }

    private function usersTable(Table $table): Table
    {
        if ($this->variant === 'stacked-actions-collapse') {
            $table->stackedOnMobile()->collapseActionsOnMobile();
        }

        // The two-action case at threshold 1: the default action arm below gives
        // exactly Edit + Delete, so this reproduces "only two actions, collapse
        // forced" without a dedicated action list.
        if ($this->variant === 'stacked-actions-collapse-two') {
            $table->stackedOnMobile()->collapseActionsOnMobile(threshold: 1)->lazy();
        }

        $table
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
                // Same group, deferred: the menu markup is built client-side on open
                // (ActionGroup::lazyMenu()). Used to CDP-verify the lazy path.
                'actions-group-lazy' => [
                    ActionGroup::make([
                        Action::make('view')->label('View')->icon('outline:eye'),
                        Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                        Action::make('duplicate')->label('Duplicate')->icon('outline:document-duplicate'),
                        DeleteAction::make(),
                    ])->lazyMenu(),
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
                // Three inline row actions; on the mobile stacked card these collapse
                // into a single "⋮" dropdown once the count reaches the threshold.
                'stacked-actions-collapse' => [
                    Action::make('view')->label('View')->icon('outline:eye'),
                    Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
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
            // Double-click a row → open it (a confirmation modal here). Because the
            // table is selectable, the default trigger is double-click, leaving the
            // single click to the selection gestures (it only marks the row).
            // Behaviour-only: no toolbar button.
            ->recordActions(match ($this->variant) {
                'record-actions' => [
                    RecordAction::make(
                        Action::make('open')
                            ->label('Open')
                            ->icon('outline:eye')
                            ->requiresConfirmation()
                            ->modalHeading(fn ($r) => "Opened {$r->name}")
                            ->modalDescription('Double-clicking the row opened this record.')
                            ->action(fn () => null)
                    )->onDoubleClick(),
                ],
                // Both gestures bound: a single click "views", a double-click
                // "edits". The double-click must not also fire the deferred view.
                'record-actions-dual' => [
                    RecordAction::make(Action::make('view')->label('View')->action(fn () => null))->onClick(),
                    RecordAction::make(Action::make('edit')->label('Edit')->action(fn () => null))->onDoubleClick(),
                ],
                // Keyboard navigation showcase (see the legend above the table).
                // Keyboard nav follows gestures() plus a table the keyboard can
                // drive — record actions here: the rows become a grid with a roving tabindex, ↑/↓ move the active row,
                // Enter runs the double-click action, Shift+Enter the single-click
                // one, and each onKey() binding fires against the active row.
                // Clicking a row adopts it as the active row, so pointer and
                // keyboard always continue from the same place.
                'record-actions-keyboard' => [
                    RecordAction::make(
                        Action::make('open')
                            ->label('Open')
                            ->icon('outline:eye')
                            ->requiresConfirmation()
                            ->modalHeading(fn ($r) => "Opened {$r->name}")
                            ->modalDescription('Enter (or a double-click) runs the primary record action against the active row.')
                            ->action(fn () => null)
                    )->onDoubleClick(),
                    RecordAction::make(
                        Action::make('preview')
                            ->label('Preview')
                            ->icon('outline:document-magnifying-glass')
                            ->requiresConfirmation()
                            ->modalHeading(fn ($r) => "Preview of {$r->name}")
                            ->modalDescription('Shift+Enter (or a single click) runs the secondary record action.')
                            ->action(fn () => null)
                    )->onClick(),
                    RecordAction::make(
                        Action::make('archive')
                            ->label('Archive')
                            ->icon('outline:archive-box')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading(fn ($r) => "Archive {$r->name}?")
                            ->modalDescription('Fired by the Delete key against the active row — an onKey() binding.')
                            ->action(fn () => null)
                    )->onKey('Delete'),
                ],
                default => [],
            })
            // Right-click a row → a dedicated context menu (declared separately
            // from the ->actions() toolbar above). The menu is one of the
            // capabilities the shipped default leaves allowed, so it works on
            // every variant — unlike the keyboard, the ranges and the sweep,
            // which only the record-action variants below ask for.
            ->rowContextMenu([
                Action::make('view')->label('View')->icon('outline:eye'),
                Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
                Action::make('duplicate')->label('Duplicate')->icon('outline:document-duplicate'),
                DeleteAction::make(),
            ])
            ->paginated(false);

        // Only the variants whose whole point is whole-row interaction ask for
        // the opt-in layer. The rest — `table-overview` included, the preview
        // people look at to see what a table *is* — stay on the shipped
        // default, which is what a consumer gets out of the box.
        if (in_array($this->variant, self::RECORD_ACTION_VARIANTS, true)) {
            $table->gestures();
        }

        return $table;
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
