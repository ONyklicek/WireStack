<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components\Layout;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * HTML fieldset layout with legend and column support.
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
        return 'wire-forms::layouts.fieldset';
    }
}
