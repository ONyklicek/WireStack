<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Layout;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Grid layout component for arranging fields in columns.
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
        return 'wire-forms::layouts.grid';
    }
}
