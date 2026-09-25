<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Board\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use NyonCode\WireSortable\Board\Board;

/**
 * Put a card in a lane, at a position.
 *
 * The lane is the record's column; the position is only kept when the board
 * has an order column, and then the whole lane it lands in is renumbered from
 * zero — the card at its drop, the others in the order they had — writing only
 * the rows whose number changed. One transaction, so a lane is never read
 * half-renumbered.
 */
final class MoveBoardCard
{
    public function execute(Board $board, Model $record, string $lane, int $position): void
    {
        DB::connection($record->getConnectionName())->transaction(function () use ($board, $record, $lane, $position): void {
            $record->setAttribute($board->getGroupBy(), $lane);

            $column = $board->getOrderColumn();

            if ($column === null) {
                $record->save();

                return;
            }

            $siblings = $board->getQuery()
                ->where($board->getGroupBy(), $lane)
                ->whereKeyNot($record->getKey())
                ->get()
                ->all();

            array_splice($siblings, max(0, min($position, count($siblings))), 0, [$record]);

            foreach ($siblings as $index => $sibling) {
                if ($sibling === $record) {
                    $record->setAttribute($column, $index);
                    $record->save();

                    continue;
                }

                if ((int) $sibling->getAttribute($column) !== $index) {
                    $sibling->setAttribute($column, $index);
                    $sibling->save();
                }
            }
        });
    }
}
