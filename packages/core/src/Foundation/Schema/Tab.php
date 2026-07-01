<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * A single tab inside a {@see Tabs} layout: a labelled, optionally iconed panel
 * holding its own child schema laid out on a column grid.
 *
 * The label defaults to a headline of the tab's name ({@see HasLabel}).
 */
class Tab extends LayoutComponent
{
    protected ?string $icon = null;

    protected int $columns = 1;

    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.tab';
    }
}
