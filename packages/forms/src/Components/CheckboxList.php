<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Multiple checkbox list with search, bulk toggle, grouped options, and column layout.
 */
class CheckboxList extends Field
{
    /** @var array<string, string>|Closure */
    protected array|Closure $options = [];

    protected int $columns = 1;

    protected bool $searchable = false;

    protected ?string $searchPrompt = null;

    protected bool $bulkToggleable = false;

    protected ?string $selectAllLabel = null;

    protected ?string $deselectAllLabel = null;

    protected bool $grouped = false;

    /** @var array<string, array<string, string>>|Closure */
    protected array|Closure $groups = [];

    /**
     * @param  array<string, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function searchPrompt(?string $prompt): static
    {
        $this->searchPrompt = $prompt;

        return $this;
    }

    public function bulkToggleable(bool $condition = true): static
    {
        $this->bulkToggleable = $condition;

        return $this;
    }

    public function selectAllLabel(?string $label): static
    {
        $this->selectAllLabel = $label;

        return $this;
    }

    public function deselectAllLabel(?string $label): static
    {
        $this->deselectAllLabel = $label;

        return $this;
    }

    public function grouped(bool $condition = true): static
    {
        $this->grouped = $condition;

        return $this;
    }

    /**
     * @param  array<string, array<string, string>>|Closure  $groups
     */
    public function groups(array|Closure $groups): static
    {
        $this->groups = $groups;
        $this->grouped = true;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->evaluate($this->options);
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getSearchPrompt(): string
    {
        return $this->searchPrompt ?? trans('wire-forms::fields.search');
    }

    public function isBulkToggleable(): bool
    {
        return $this->bulkToggleable;
    }

    public function getSelectAllLabel(): string
    {
        return $this->selectAllLabel ?? trans('wire-forms::fields.select_all');
    }

    public function getDeselectAllLabel(): string
    {
        return $this->deselectAllLabel ?? trans('wire-forms::fields.deselect_all');
    }

    public function isGrouped(): bool
    {
        return $this->grouped;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getGroups(): array
    {
        return $this->evaluate($this->groups);
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.checkbox-list';
    }
}
