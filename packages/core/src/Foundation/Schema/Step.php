<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use Closure;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\HasLabel;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * A single step inside a {@see Wizard} layout: a labelled, optionally described
 * and iconed panel holding its own child schema on a column grid.
 *
 * The label defaults to a headline of the step's name ({@see HasLabel}).
 */
class Step extends LayoutComponent
{
    protected string|Closure|null $description = null;

    protected ?string $icon = null;

    protected int $columns = 1;

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

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

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
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
        return 'wire-core::schema.step';
    }
}
