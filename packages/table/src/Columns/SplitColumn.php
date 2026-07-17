<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\HasSearchColumns;

class SplitColumn extends Column implements HasSearchColumns
{
    /** @var array<int, Column> */
    protected array $columns = [];

    protected string $layout = 'horizontal'; // horizontal, vertical

    protected string $gap = '3';

    protected bool $alignCenter = true;

    /**
     * Create with array of columns
     */
    public static function make(string $name = 'split'): static
    {
        return new static($name);
    }

    /**
     * Shorthand for creating with columns
     *
     * @param  array<int, Column>  $columns
     */
    public static function split(array $columns, string $name = 'split'): static
    {
        $instance = new static($name);
        $instance->columns = $columns;

        return $instance;
    }

    /**
     * Set columns to split
     *
     * @param  array<int, Column>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Set layout direction
     */
    public function vertical(): static
    {
        $this->layout = 'vertical';

        return $this;
    }

    /**
     * Set layout direction
     */
    public function horizontal(): static
    {
        $this->layout = 'horizontal';

        return $this;
    }

    /**
     * Set gap between items
     */
    public function gap(string $gap): static
    {
        $this->gap = $gap;

        return $this;
    }

    /**
     * Align items to center (default)
     */
    public function alignCenter(bool $align = true): static
    {
        $this->alignCenter = $align;

        return $this;
    }

    /**
     * Align items to start
     */
    public function alignStart(): static
    {
        $this->alignCenter = false;

        return $this;
    }

    /**
     * Get columns
     *
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get searchable column names
     */
    public function getSearchColumns(): array
    {
        $searchable = [];
        foreach ($this->columns as $column) {
            if ($column->isSearchable()) {
                $searchable[] = $column->getName();
            }
        }

        return $searchable;
    }

    /**
     * Check if any column is searchable
     */
    public function isSearchable(): bool
    {
        foreach ($this->columns as $column) {
            if ($column->isSearchable()) {
                return true;
            }
        }

        return parent::isSearchable();
    }

    /**
     * Get first sortable column name
     */
    public function getSortColumn(): ?string
    {
        foreach ($this->columns as $column) {
            if ($column->isSortable()) {
                return $column->getName();
            }
        }

        return null;
    }

    /**
     * Check if sortable
     */
    public function isSortable(): bool
    {
        foreach ($this->columns as $column) {
            if ($column->isSortable()) {
                return true;
            }
        }

        return parent::isSortable();
    }

    /**
     * Render the cell
     */
    public function renderCell(Model $record): string
    {
        if (! $this->canView() || ! $this->isVisibleForRecord($record)) {
            return '';
        }

        $imageHtml = '';
        $textColumns = [];

        foreach ($this->columns as $column) {
            if (! $column->canView() || ! $column->isVisibleForRecord($record)) {
                continue;
            }

            // Check if it's an image column (render separately for layout)
            if ($column instanceof ImageColumn) {
                $imageHtml = $column->renderCell($record);
            } else {
                $textColumns[] = $column->renderCell($record);
            }
        }

        // State/layout decisions stay here; markup lives in the partial.
        return $this->renderView('tables.columns.split', [
            'layout' => $this->layout,
            'alignClass' => $this->alignCenter ? 'items-center' : 'items-start',
            'gapClass' => "gap-$this->gap",
            'imageHtml' => $imageHtml,
            'textColumns' => $textColumns,
        ]);
    }
}
