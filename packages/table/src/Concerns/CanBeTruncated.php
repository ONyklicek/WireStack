<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;

/**
 * What a cell does when its text does not fit: wrap onto more lines, or cut.
 *
 * The two settings are one decision surface and belong together — `wrap()`
 * decides whether the cell is allowed to grow taller, `limit()` cuts the value
 * to a character count before it is ever formatted. A wrapping column with a
 * limit still cuts; the limit is about the value, the wrap about the box.
 *
 * @phpstan-require-extends Column
 */
trait CanBeTruncated
{
    /** @var bool Whether to wrap text in the cell */
    protected bool $wrap = false;

    /** @var int|null Maximum number of characters to show before truncating */
    protected ?int $limit = null;

    /** Let the cell text wrap onto multiple lines instead of truncating to one. */
    public function wrap(bool $wrap = true): static
    {
        $this->wrap = $wrap;

        return $this;
    }

    public function shouldWrap(): bool
    {
        return $this->wrap;
    }

    /** Truncate the displayed text to at most N characters (adds an ellipsis); null removes the limit. */
    public function limit(?int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
