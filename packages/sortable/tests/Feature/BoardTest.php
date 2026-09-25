<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireSortable\Board\Board;
use NyonCode\WireSortable\Board\Lane;
use NyonCode\WireSortable\Concerns\WithBoard;
use NyonCode\WireSortable\Exceptions\BoardConfigurationException;

/*
 * A board: records in lanes, cards dragged between them.
 *
 * The browser half is Livewire's own `wire:sort`, so what is proven here is the
 * server's: the lanes hold the right cards in the right order, a drop writes
 * the lane and renumbers the lane it lands in, and a drop the board would not
 * allow — an unknown lane, a card outside the board, a guarded transition —
 * changes nothing.
 */
enum BdStatus: string implements HasColor, HasLabel
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Todo => 'To do',
            self::Doing => 'In progress',
            self::Done => 'Done',
        };
    }

    public function getColor(): ?string
    {
        return $this === self::Done ? 'success' : null;
    }
}

class BdTask extends Model
{
    protected $table = 'bd_tasks';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['status' => BdStatus::class];
}

class BdBoard extends Component
{
    use WithBoard;

    public static bool $ordered = true;

    public array $moves = [];

    public function board(Board $board): Board
    {
        return $board
            ->model(BdTask::class)
            ->query(fn ($query) => $query->where('archived', false))
            ->groupBy('status')
            ->lanes(BdStatus::class)
            ->cardTitle('title')
            ->cardDescription(fn (BdTask $task) => $task->getAttribute('owner'))
            ->cardUrl(fn (BdTask $task) => '/tasks/'.$task->getKey())
            ->orderColumn(static::$ordered ? 'position' : null);
    }

    /** Nothing leaves Done: a finished task is reopened by hand, not dragged back. */
    protected function canMoveBoardCard(Model $record, string $lane): bool
    {
        return $record->getAttribute('status') !== BdStatus::Done;
    }

    protected function boardCardMoved(Model $record, string $from, string $to): void
    {
        $this->moves[] = "{$record->getKey()}:{$from}>{$to}";
    }

    public function render(): string
    {
        return '<div>@include(\'wire-sortable::board.content\')</div>';
    }
}

function bdStatusOf(int $id): string
{
    return BdTask::query()->find($id)->getAttribute('status')->value;
}

function bdPositions(string $status): array
{
    return BdTask::query()->where('status', $status)->orderBy('position')->pluck('title')->all();
}

afterEach(function () {
    // On a real server the table outlives the test; the next one creates it again.
    Schema::dropIfExists('bd_tasks');
});

beforeEach(function () {
    Schema::create('bd_tasks', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status');
        $table->string('owner')->nullable();
        $table->unsignedInteger('position')->default(0);
        $table->boolean('archived')->default(false);
    });

    BdTask::query()->create(['title' => 'Write spec', 'status' => 'todo', 'position' => 1, 'owner' => 'Ada']);
    BdTask::query()->create(['title' => 'Review', 'status' => 'todo', 'position' => 0]);
    BdTask::query()->create(['title' => 'Build', 'status' => 'doing', 'position' => 0]);
    BdTask::query()->create(['title' => 'Ship', 'status' => 'done', 'position' => 0]);
    BdTask::query()->create(['title' => 'Old idea', 'status' => 'todo', 'position' => 9, 'archived' => true]);

    BdBoard::$ordered = true;
});

it('draws a lane per enum case, labelled and ordered as the enum says', function () {
    $html = Livewire::test(BdBoard::class)->html();

    expect($html)->toContain('data-testid="board-lane-todo"')
        ->toContain('To do')->toContain('In progress')
        ->and(strpos($html, 'board-lane-todo'))->toBeLessThan(strpos($html, 'board-lane-doing'));
});

it('puts each card in its lane, in its order, and leaves out what the query does', function () {
    $lanes = Livewire::test(BdBoard::class)->instance()->boardLanesForView();

    expect(array_column($lanes[0]['cards'], 'title'))->toBe(['Review', 'Write spec'])
        ->and(array_column($lanes[1]['cards'], 'title'))->toBe(['Build'])
        ->and($lanes[0]['cards'][1]['description'])->toBe('Ada')
        ->and($lanes[0]['cards'][1]['url'])->toBe('/tasks/1');
});

it('wires every lane to one sort group, with the lane as its id', function () {
    $html = Livewire::test(BdBoard::class)->html();

    expect($html)->toContain('wire:sort="moveBoardCard"')
        ->toContain('wire:sort:group="wire-board"')
        ->toContain('wire:sort:group-id="doing"')
        ->toContain('wire:sort:item="3"');
});

it('moves a card to another lane at the position it was dropped', function () {
    $component = Livewire::test(BdBoard::class)->call('moveBoardCard', 3, 1, 'todo');

    expect(bdStatusOf(3))->toBe('todo')
        ->and(bdPositions('todo'))->toBe(['Review', 'Build', 'Write spec', 'Old idea'])
        ->and($component->get('moves'))->toBe(['3:doing>todo']);
});

it('reorders inside a lane', function () {
    Livewire::test(BdBoard::class)->call('moveBoardCard', 1, 0, 'todo');

    expect(bdPositions('todo'))->toBe(['Write spec', 'Review', 'Old idea']);
});

it('only changes the lane on a board with no order column', function () {
    BdBoard::$ordered = false;

    Livewire::test(BdBoard::class)->call('moveBoardCard', 3, 0, 'todo');

    expect(bdStatusOf(3))->toBe('todo')
        ->and(BdTask::query()->find(3)->getAttribute('position'))->toBe(0);
});

it('ignores a drop into a lane the board does not have', function () {
    Livewire::test(BdBoard::class)->call('moveBoardCard', 3, 0, 'nowhere');

    expect(bdStatusOf(3))->toBe('doing');
});

it('ignores a card the board would not show', function () {
    Livewire::test(BdBoard::class)->call('moveBoardCard', 5, 0, 'doing');

    expect(bdStatusOf(5))->toBe('todo');
});

it('asks the host whether a card may make the move', function () {
    Livewire::test(BdBoard::class)->call('moveBoardCard', 4, 0, 'todo');

    expect(bdStatusOf(4))->toBe('done');
});

it('takes lanes as Lane objects or value => label pairs', function () {
    $board = Board::make()->lanes([Lane::make('a')->label('First')->color('danger'), 'b' => 'Second']);

    expect(array_keys($board->getLanes()))->toBe(['a', 'b'])
        ->and($board->getLanes()['a']->getColor())->toBe('danger')
        ->and($board->getLanes()['b']->getLabel())->toBe('Second')
        ->and($board->getLanes()['b']->getColor())->toBe('gray');
});

it('colours a lane from an enum that says so', function () {
    $lanes = Board::make()->lanes(BdStatus::class)->getLanes();

    expect($lanes['done']->getColor())->toBe('success')
        ->and($lanes['todo']->getColor())->toBe('gray');
});

it('refuses a board without a model, a column or a real enum', function () {
    expect(fn () => Board::make()->getQuery())->toThrow(BoardConfigurationException::class, 'names no model')
        ->and(fn () => Board::make()->getGroupBy())->toThrow(BoardConfigurationException::class, 'no column')
        ->and(fn () => Board::make()->lanes(stdClass::class))->toThrow(BoardConfigurationException::class, 'not a backed enum');
});
