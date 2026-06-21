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
    protected int $columns = 1;

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
        return 'wire-core::schema.fieldset';
    }
}
