<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\View\Palette;
use NyonCode\WireSortable\Board\Actions\MoveBoardCard;
use NyonCode\WireSortable\Board\Board;

/**
 * A Livewire component that hosts a board: it draws the lanes and answers a drop.
 *
 *   final class TaskBoard extends Page      // or any Livewire component
 *   {
 *       use WithBoard;
 *
 *       protected static string $view = 'wire-sortable::board.content';
 *
 *       public function board(Board $board): Board
 *       {
 *           return $board->model(Task::class)->groupBy('status')->lanes(TaskStatus::class);
 *       }
 *   }
 *
 * The drag is Livewire's own `wire:sort` with a group across the lanes, so no
 * script of this package is involved: a drop calls {@see MoveBoardCard()} with
 * the card's key, its position and the lane it landed in. The record is looked
 * up through the board's own query, so a card the board would not show cannot
 * be moved by naming its key, and the lane must be one the board declares.
 */
trait WithBoard
{
    /** Per request, never in the snapshot. */
    private ?Board $resolvedBoard = null;

    /** Declare the board. */
    abstract public function board(Board $board): Board;

    public function getBoard(): Board
    {
        return $this->resolvedBoard ??= $this->board(Board::make());
    }

    /**
     * Where a drop lands: `wire:sort` calls this with the card, its position and the lane.
     */
    public function moveBoardCard(mixed $key, int $position, string $lane): void
    {
        $board = $this->getBoard();

        if (! array_key_exists($lane, $board->getLanes())) {
            return;
        }

        $record = $board->getQuery()->find($key);

        if (! $record instanceof Model || ! $this->canMoveBoardCard($record, $lane)) {
            return;
        }

        $from = $board->laneOf($record);

        app(MoveBoardCard::class)->execute($board, $record, $lane, $position);

        $this->resolvedBoard = null;
        $this->boardCardMoved($record, $from, $lane);
    }

    /** Whether this card may go to this lane. Override to guard a transition. */
    protected function canMoveBoardCard(Model $record, string $lane): bool
    {
        return true;
    }

    /** Called after a card moved — to notify, to log. */
    protected function boardCardMoved(Model $record, string $from, string $to): void {}

    /**
     * The lanes, each with its cards, as the view draws them.
     *
     * One query for the whole board, sorted into lanes here; a record whose
     * value matches no lane is not drawn, because there is nowhere to put it.
     *
     * @return array<int, array{key: string, label: string, markerClasses: string, cards: array<int, array{key: string, title: string, description: string|null, url: string|null}>}>
     */
    public function boardLanesForView(): array
    {
        $board = $this->getBoard();
        $cards = array_fill_keys(array_keys($board->getLanes()), []);

        foreach ($board->getQuery()->get() as $record) {
            $lane = $board->laneOf($record);

            if (array_key_exists($lane, $cards)) {
                $cards[$lane][] = [
                    'key' => (string) $record->getKey(),
                    'title' => $board->titleOf($record),
                    'description' => $board->descriptionOf($record),
                    'url' => $board->urlOf($record),
                ];
            }
        }

        $lanes = [];

        foreach ($board->getLanes() as $key => $lane) {
            $lanes[] = [
                'key' => (string) $key,
                'label' => (string) $lane->getLabel(),
                'markerClasses' => Palette::getBadgeColorClasses($lane->getColor()),
                'cards' => $cards[$key],
            ];
        }

        return $lanes;
    }
}
