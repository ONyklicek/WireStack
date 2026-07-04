<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Schema;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Canonical empty state — a centered icon, heading, description and optional
 * action buttons, shown when there is nothing to display.
 *
 * Shared vocabulary consumed by the table's "no records" state, infolists and
 * the standalone <x-wire::empty-state> tag; markup lives in the shared
 * `wire-core::partials.empty-state` view.
 */
class EmptyState extends LayoutComponent
{
    protected ?string $icon = null;

    protected string|Closure|null $heading = null;

    protected string|Closure|null $description = null;

    /** @var array<int, Htmlable|string> */
    protected array $actions = [];

    public function icon(string|Icon|null $icon): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function heading(string|Closure|null $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function description(string|Closure|null $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Action buttons rendered below the description.
     *
     * @param  array<int, Htmlable|string>  $actions
     */
    public function actions(array $actions): static
    {
        $this->actions = $actions;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getHeading(): ?string
    {
        return $this->evaluate($this->heading);
    }

    public function getDescription(): ?string
    {
        return $this->evaluate($this->description);
    }

    /**
     * @return array<int, string>
     */
    public function getActionsHtml(): array
    {
        return array_map(static fn (Htmlable|string $action): string => (string) $action, $this->actions);
    }

    protected function viewName(): string
    {
        return 'wire-core::schema.empty-state';
    }
}
