<?php

declare(strict_types=1);

namespace Workbench\App\Livewire\Pages;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WireSortable\Board\Board;
use NyonCode\WireSortable\Board\Lane;
use NyonCode\WireSortable\Concerns\WithBoard;
use Workbench\App\Models\Task;

/**
 * The workbench's tasks as a board: a page of the application's own composing
 * a board, which is the whole of what a kanban is here — `Page` for the
 * heading and header actions, `WithBoard` for the lanes and the drop.
 *
 * No order column: the tasks' `sort_order` belongs to the sortable table
 * previews, and a board renumbering it would move their rows. So a drop here
 * changes the lane and the lane keeps `sort_order`'s order.
 */
class TaskBoard extends Page
{
    use WithBoard;

    protected static string $view = 'wire-sortable::board.content';

    protected ?string $title = 'Task board';

    public function board(Board $board): Board
    {
        return $board
            ->model(Task::class)
            ->query(fn (Builder $query) => $query->orderBy('sort_order'))
            ->groupBy('status')
            ->lanes([
                Lane::make('todo')->label('To do'),
                Lane::make('in_progress')->label('In progress')->color('info'),
                Lane::make('review')->color('warning'),
                Lane::make('blocked')->color('danger'),
                Lane::make('done')->color('success'),
            ])
            ->cardTitle('title')
            ->cardDescription('owner_name');
    }

    protected function headerActions(): array
    {
        return [
            Action::make('refresh')->label('Refresh')->icon('outline:arrow-path')->action(fn () => null),
        ];
    }
}
