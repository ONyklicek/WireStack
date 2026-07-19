<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Canonical grid layout for arranging child components in columns.
 *
 * Shared by forms and infolists; surface-specific markup lives in each
 * package's Blade view.
 */
class Grid extends LayoutComponent
{
    /** @var int|array<string|int, int|string> */
    protected int|array $columns = 2;

    /**
     * Set the number of columns to arrange children across (default 2).
     *
     * @param  int|array<string|int, int|string>  $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @return int|array<string|int, int|string>
     */
    public function getColumns(): int|array
    {
        return $this->columns;
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.grid';
    }
}
