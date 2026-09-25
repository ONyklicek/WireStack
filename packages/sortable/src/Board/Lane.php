<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Board;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Concerns\HasName;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * One lane of a board: the value of the board's column it holds, and what it
 * says above its cards.
 *
 *   Lane::make('todo')->label('To do'),
 *   Lane::make('done')->color('success'),
 *
 * The name is the column's value as stored — for an enum-cast column, the
 * case's backing value. A lane that names no label is its name, humanised,
 * which is `HasLabel`'s rule, not one of its own.
 */
final class Lane
{
    use EvaluatesClosures;
    use HasLabel;
    use HasName;

    private ?string $color = null;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    /** A lane for this value of the board's column. */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /** The colour of the lane's marker — any canonical colour name. */
    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color ?? 'gray';
    }
}
