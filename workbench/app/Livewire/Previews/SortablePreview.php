<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Previews;

use Livewire\Component;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use Workbench\App\Models\Task;

class SortablePreview extends Component
{
    use WithSortable;
    use WithTable;

    public string $variant = 'overview';

    public function mount(string $variant = 'overview'): void
    {
        $this->variant = $variant;
    }

    public function table(Table $table): Table
    {
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

        return $table
            ->model(Task::class)
            ->alwaysReorderable('sort_order')
            ->columns($columns)
            // Selection + reorder on one table: the selection sweep (drag over
            // the checkbox column) must coexist with the row drag handles.
            ->selectable()
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public function render()
    {
        return view('livewire.previews.sortable-preview');
    }
}
