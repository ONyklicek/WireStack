<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * Canonical fieldset layout with a legend and column support.
 *
 * Shared by forms and infolists; surface-specific markup lives in each
 * package's Blade view.
 */
class Fieldset extends LayoutComponent
{
    /** @var int|array<string|int, int|string> */
    protected int|array $columns = 1;

    /**
     * Set the column grid the fieldset lays its children out in (default 1).
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
        return 'wire-core::schema.fieldset';
    }
}
