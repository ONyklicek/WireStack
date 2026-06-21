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
    protected int $columns = 2;

    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.grid';
    }
}
