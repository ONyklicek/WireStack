<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use Workbench\App\Models\Task;
use Workbench\App\Models\User;

class SortablePreview extends Component
{
    use WithSortable;
    use WithTable;

    public string $variant = 'overview';

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;

        // Column order is persisted per user, so the reorder preview needs
        // someone logged in — without an id, reorderColumns() returns early and
        // the next render puts the columns straight back.
        if (in_array($variant, ['columns', 'morph', 'everything'], true) && ! auth()->check()) {
            $user = User::query()->first();

            if ($user !== null) {
                auth()->login($user);
            }
        }
    }

    /**
     * Saved column order is keyed by user + model + this identifier, which
     * defaults to the component class — and every column-reorderable variant
     * here is the same class over the same model. Without the variant in the
     * key, a drag on `sortable-columns` decides what `sortable-morph` and
     * `sortable-everything` open with, and one driver run leaves the next
     * fixture in a state nobody set up.
     */
    protected function getReorderableTableIdentifier(): string
    {
        return static::class.':'.$this->variant;
    }

    public function table(Table $table): Table
    {
        if ($this->variant === 'morph') {
            return $this->morphTable($table);
        }

        if ($this->variant === 'everything') {
            return $this->everythingTable($table);
        }

        if ($this->variant === 'partials') {
            return $this->partialsTable($table);
        }

        $status = BadgeColumn::make('status')
            ->label('Status')
            ->colors([
                'todo' => 'gray',
                'in_progress' => 'info',
                'review' => 'warning',
                'blocked' => 'danger',
                'done' => 'success',
            ]);

        $priority = BadgeColumn::make('priority')
            ->label('Priority')
            ->colors([
                'high' => 'danger',
                'medium' => 'warning',
                'low' => 'success',
            ]);

        // The "detail" variant is a focused close-up with fewer columns so the
        // reorder handles and rows read clearly when zoomed in.
        $columns = $this->variant === 'detail'
            ? [
                TextColumn::make('title')->label('Task')->searchable(),
                $status,
                $priority,
            ]
            : [
                TextColumn::make('title')->label('Task')->searchable(),
                $status,
                $priority,
                TextColumn::make('owner_name')->label('Owner'),
                TextColumn::make('due_at')->label('Due')->date('M j'),
            ];

        $table
            // Reordering shares the row with the selection gestures, which is
            // the point of this fixture — so it asks for the opt-in layer.
            ->gestures()
            ->model(Task::class)
            ->alwaysReorderable('sort_order')
            ->columns($columns)
            // Selection + reorder on one table: the selection sweep (drag over
            // the checkbox column) must coexist with the row drag handles.
            ->selectable()
            ->defaultSort('sort_order')
            ->paginated(false);

        // Dragging a header to reorder columns, on a table that also has a
        // selection column and row drag handles — the combination where the
        // body cells and the header cells do not line up by position.
        if ($this->variant === 'columns') {
            // The context menu is what makes the header and the body rows stop
            // lining up by position: each body <tr> leads with the teleport
            // <template> that carries its menu.
            $table->columnReorderable()->recordActions([
                Action::make('open')->label('Open')->onContextMenu()->action(fn () => null),
            ]);
        }

        return $table;
    }

    /**
     * Row partials and row reordering on one table.
     *
     * The drag handle `<td>` is created client-side and prepended to every
     * `<tr>` (`sortable.js`), rebuilt from Livewire's `morph.updated` hook. A row
     * partial is morphed by `partials.js`, which calls `Alpine.morph()` directly
     * and never reaches that hook — so an inline save in reorder mode used to
     * replace a three-cell row with the server's two-cell one and leave the
     * handle gone, with nothing to put it back.
     *
     * The editable column is what makes a save produce a partial at all;
     * `alwaysReorderable()` keeps the table in reorder mode with no toggle, so
     * the driver can type into a cell while the handles are up.
     */
    private function partialsTable(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->alwaysReorderable('sort_order')
            ->rowPartials()
            ->columns([
                TextInputColumn::make('title')->label('Task'),
                TextColumn::make('owner_name')->label('Owner'),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    /**
     * A reorderable table that still has to answer the ordinary controls.
     *
     * `columnReorderable()` alone, deliberately: it renders the sortable
     * wrapper — and with it the drag controller's morph guards — without
     * putting the table in row-reorder mode, so nothing but the wrapper stands
     * between the search box and the records. What the search box does here is
     * exactly what it does on any other table, and any difference is the
     * wrapper's doing.
     *
     * The editable column is the other half: the guards exist to protect a
     * cell mid-write from a morph, and narrowing them must not stop them
     * doing that.
     */
    private function morphTable(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->columnReorderable()
            ->columns([
                TextColumn::make('title')->label('Task')->searchable(),
                TextInputColumn::make('owner_name')->label('Owner'),
                TextColumn::make('status')->label('Status'),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    /**
     * Both kinds of reordering on a table that still has every ordinary control.
     *
     * The combination is the fixture: row drag handles, draggable headers, a
     * search box, pagination and a poll on one surface, because each of them is
     * something reordering could quietly break, and only together do they break
     * in the ways worth seeing.
     *
     * - Search and pagination stay live *inside* reorder mode. They used to be
     *   dropped there, which is what made a search box on an always-reorderable
     *   table inert.
     * - `paginatedWhileReordering()` keeps the pager rather than expanding to
     *   the full list on toggle, so a drag here permutes one page. That is only
     *   safe because a drop redistributes the slots the dragged rows already
     *   own — writing the client's `1..n` positions would move page one every
     *   time someone tidied page two.
     * - `poll()` rather than `live()`: change detection would skip the render on
     *   a fixture whose data nobody is changing, and the render is the point.
     *   Every tick is a morph aimed at a table that may be mid-drag or holding a
     *   half-typed search term — the two things the drag controller's guards
     *   exist to protect.
     */
    private function everythingTable(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->reorderable('sort_order')
            ->paginatedWhileReordering()
            ->columnReorderable()
            ->columns([
                TextColumn::make('title')->label('Task')->searchable(),
                TextColumn::make('owner_name')->label('Owner')->searchable(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'todo' => 'gray',
                        'in_progress' => 'info',
                        'review' => 'warning',
                        'blocked' => 'danger',
                        'done' => 'success',
                    ]),
                TextColumn::make('due_at')->label('Due')->date('M j'),
            ])
            ->defaultSort('sort_order')
            // Six seeded tasks over three a page: enough for a second page, so
            // paging and dragging are visible at the same time.
            ->paginated()
            ->perPage(3)
            ->poll('3s');
    }

    public function render()
    {
        return view('livewire.previews.sortable-preview');
    }
}
