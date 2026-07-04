<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use Closure;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Concerns\HasActions;
use NyonCode\WireCore\Foundation\Contracts\HasFieldActions;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Canonical section layout: heading, description, icon, collapsible behavior,
 * column grid and aside mode.
 *
 * Shared schema vocabulary consumed by forms (editing) and infolists
 * (read-only display). Surface-specific chrome lives in each package's Blade
 * view; this class owns only the resolved configuration.
 */
class Section extends LayoutComponent implements HasFieldActions
{
    use HasActions;

    protected string|Closure|null $description = null;

    protected ?string $icon = null;

    /** @var int|array<string|int, int|string> */
    protected int|array $columns = 1;

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

    /**
     * @param  int|array<string|int, int|string>  $columns
     */
    public function columns(int|array $columns): static
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

    /**
     * Interactive actions rendered in the section header (Filament-style).
     * Alias for {@see HasActions::actions()} with header-slot semantics.
     *
     * @param  array<int, Action>  $actions
     */
    public function headerActions(array $actions): static
    {
        return $this->actions($actions);
    }

    /**
     * The visible header actions, in declaration order.
     *
     * @return array<int, Action>
     */
    public function getHeaderActions(): array
    {
        return $this->getActions();
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @return int|array<string|int, int|string>
     */
    public function getColumns(): int|array
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
