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
        if (in_array($variant, ['columns', 'morph'], true) && ! auth()->check()) {
            $user = User::query()->first();

            if ($user !== null) {
                auth()->login($user);
            }
        }
    }

    public function table(Table $table): Table
    {
        if ($this->variant === 'morph') {
            return $this->morphTable($table);
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
     * A reorderable table that still has to answer the ordinary controls.
     *
     * `columnReorderable()` alone, deliberately: it renders the sortable
     * wrapper — and with it the drag controller's morph guards — without
     * putting the table in row-reorder mode, which bypasses search by design
     * (see WithSortable::interceptTableRecords). So what the search box does
     * here is exactly what it does on any other table, and any difference is
     * the wrapper's doing.
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

    public function render()
    {
        return view('livewire.previews.sortable-preview');
    }
}
