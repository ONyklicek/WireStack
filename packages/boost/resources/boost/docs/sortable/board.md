---
title: Board
order: 45
summary: Records in lanes — a kanban over a model and a column, cards dragged between lanes and inside one, with the order kept.
---

# Board

A board shows records in lanes by the value of one column — tasks by status,
deals by stage — and moving a card between lanes changes that value. It is a
surface like a table: declared once, rendered by the component that hosts it.

```php
use NyonCode\WireSortable\Board\Board;
```

## How It Works

**The declaration and the host are separate.** `Board` says what the lanes are,
which records go in them and what a card shows. `WithBoard`, composed into any
Livewire component, renders it and answers a drop. A board page is therefore a
[`Page`](../panels/pages.md#a-page-of-your-own) that composes `WithBoard` — no
class of its own, and no package depending on another.

**The drag is Livewire's.** Every lane is a `wire:sort` list in one group, with
the lane's value as its group id, so a card can leave its lane and a drop calls
`moveBoardCard(key, position, lane)` on the host. This package ships no script
for it.

**A drop is checked, then written.** The lane must be one the board declares;
the record is looked up through the board's own query, so a card the board would
not show cannot be moved by naming its key; `canMoveBoardCard()` may refuse the
transition. Then `MoveBoardCard` writes the lane into the column and — when the
board has an `orderColumn()` — renumbers the lane from zero with the card at its
drop, in one transaction, writing only the rows whose number changed. Without an
order column a drop changes the lane and nothing else.

**One query draws the whole board**, sorted into lanes in PHP; a record whose
value matches no lane is not drawn. Narrow a large board with `query()`.

## Basic Usage

```php
use NyonCode\WirePanels\Pages\Page;
use NyonCode\WireSortable\Board\Board;
use NyonCode\WireSortable\Concerns\WithBoard;

final class TaskBoard extends Page
{
    use WithBoard;

    protected static string $view = 'wire-sortable::board.content';   // [tl! focus]

    public function board(Board $board): Board                         // [tl! focus:start]
    {
        return $board->model(Task::class)->groupBy('status')->lanes(TaskStatus::class);
    }                                                                   // [tl! focus:end]
}
```

## Lanes

Three ways to say what the lanes are, in the order they are drawn:

```php
->lanes(TaskStatus::class)                      // a backed enum: its cases, its labels and colours
->lanes(['todo' => 'To do', 'done' => 'Done'])  // value => label
->lanes([Lane::make('todo')->label('To do'), Lane::make('done')->color('success')])
```

A lane's name is the column's value as stored — for an enum-cast column, the
case's backing value. An enum that implements the core `HasLabel` and `HasColor`
contracts labels and colours its lanes; otherwise the case name is humanised and
the marker is gray.

## Cards

```php
->cardTitle('title')                                     // an attribute, or fn (Task $task) => …
->cardDescription(fn (Task $task) => $task->owner?->name)
->cardUrl(fn (Task $task) => route('tasks.edit', $task)) // the title becomes a wire:navigate link
```

## Order

```php
->orderColumn('position')
```

The lane a card lands in is renumbered from zero around it. Leave it out and
the board keeps whatever order `query()` gives, changing only the lane.

## Guarding A Move

```php
protected function canMoveBoardCard(Model $record, string $lane): bool
{
    return $record->status !== TaskStatus::Done;   // a finished task is reopened by hand
}

protected function boardCardMoved(Model $record, string $from, string $to): void
{
    Notification::make()->title("Moved to {$to}")->send();
}
```

## Extended Example

A board in its own component, beside other content, rather than as a page:

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use NyonCode\WireSortable\Board\Board;
use NyonCode\WireSortable\Board\Lane;
use NyonCode\WireSortable\Concerns\WithBoard;

final class DealPipeline extends Component
{
    use WithBoard;

    public function board(Board $board): Board                                    // [tl! focus:start]
    {
        return $board
            ->model(Deal::class)
            ->query(fn (Builder $query) => $query->where('owner_id', auth()->id()))
            ->groupBy('stage')
            ->lanes([
                Lane::make('lead')->label('Leads'),
                Lane::make('proposal')->color('info'),
                Lane::make('won')->color('success'),
                Lane::make('lost')->color('danger'),
            ])
            ->cardTitle('name')
            ->cardDescription(fn (Deal $deal) => money($deal->value))
            ->orderColumn('stage_position');
    }                                                                              // [tl! focus:end]

    protected function canMoveBoardCard(Model $record, string $lane): bool
    {
        return $lane !== 'won' || $record->signed_at !== null;
    }

    public function render()
    {
        return view('livewire.deal-pipeline');   // @include('wire-sortable::board.board', ['lanes' => $this->boardLanesForView()])
    }
}
```

## Board API

```php
Board::make()
->model(string $model)                          // class-string<Model>
->query(?Closure $callback)                     // fn (Builder $query) => $query->… — narrows the records
->groupBy(string $column)                       // [tl! focus:start] the column a lane is a value of
->lanes(array|string $lanes)                    // [tl! focus:end] Lane[], value => label, or a backed enum class
->cardTitle(string|Closure $title)              // an attribute or fn (Model $record) => string — default 'id'
->cardDescription(string|Closure|null $description)
->cardUrl(?Closure $url)                        // fn (Model $record) => ?string
->orderColumn(?string $column)                  // renumbered on a drop — default none
->getLanes(): array
->getGroupBy(): string
->getOrderColumn(): ?string
->getQuery(): Builder
```

`Lane`: `Lane::make(string $name)`, `->label(string|Closure|null)`,
`->color(string|Color|null)` — default `'gray'`.

`WithBoard`: `board(Board $board): Board` (declare), `getBoard(): Board`,
`moveBoardCard(mixed $key, int $position, string $lane): void` (what a drop
calls), `canMoveBoardCard(Model, string): bool`, `boardCardMoved(Model, string,
string): void`, `boardLanesForView(): array`. Views: `wire-sortable::board.board`
(the lanes, given `$lanes`) and `wire-sortable::board.content` (the same, for a
`Page`'s `$view`). Hook names: `board`, `board-lane`, `board-card`.

## Related

- [Pages](../panels/pages.md#a-page-of-your-own) — the page a board usually sits on
- [Row Reordering](row-sorting.md) — ordering a table's rows by drag
