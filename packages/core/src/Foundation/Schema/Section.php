<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use Closure;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Canonical section layout: heading, description, icon, collapsible behavior,
 * column grid and aside mode.
 *
 * Shared schema vocabulary consumed by forms (editing) and infolists
 * (read-only display). Surface-specific chrome lives in each package's Blade
 * view; this class owns only the resolved configuration.
 */
class Section extends LayoutComponent
{
    protected string|Closure|null $description = null;

    protected ?string $icon = null;

    protected int $columns = 1;

    protected bool $collapsible = false;

    protected bool $collapsed = false;

    protected bool $compact = false;

    protected bool $aside = false;

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

    public function collapsible(bool $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    public function collapsed(bool $condition = true): static
    {
        $this->collapsed = $condition;

        if ($condition) {
            $this->collapsible = true;
        }

        return $this;
    }

    public function compact(bool $condition = true): static
    {
        $this->compact = $condition;

        return $this;
    }

    public function aside(bool $condition = true): static
    {
        $this->aside = $condition;

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

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    public function isCompact(): bool
    {
        return $this->compact;
    }

    public function isAside(): bool
    {
        return $this->aside;
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.section';
    }
}
