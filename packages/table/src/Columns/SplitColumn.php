<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\HasSearchColumns;
use NyonCode\WireCore\Foundation\Support\GapScale;

class SplitColumn extends Column implements HasSearchColumns
{
    /** A split cell sits tighter than a page-level Flex, whose default is 4. */
    private const DEFAULT_GAP = 3;

    /** @var array<int, Column> */
    protected array $columns = [];

    protected string $layout = 'horizontal'; // horizontal, vertical

    /** Step on the shared gap scale; a name ('sm') or a number, resolved by GapScale. */
    protected string|int $gap = self::DEFAULT_GAP;

    /**
     * Cross-axis alignment, or null while the author has not asked for one.
     *
     * Null is not "centre" — it is *unset*, and the two render differently in a
     * column. See {@see getAlignClass()}.
     */
    protected ?bool $alignCenter = null;

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
     * Space between the split's children.
     *
     * Takes a design-system name ('none', 'xs', 'sm', 'md', 'lg', 'xl') or a step
     * on Tailwind's 0–12 gap scale, either as an int or as a numeric string.
     * {@see GapScale} resolves all three to a literal utility — the class used to
     * be built by interpolation here, which Tailwind's scanner cannot see, so
     * every value but the one another file happened to spell out rendered with
     * no gap at all. The names the docs have always taught ('sm') work for the
     * first time.
     */
    public function gap(string|int $gap): static
    {
        $this->gap = $gap;

        return $this;
    }

    /** The literal gap utility for the configured spacing. */
    public function getGapClass(): string
    {
        return GapScale::classFor($this->gap, self::DEFAULT_GAP);
    }

    /**
     * Align the children on the cross axis — centred.
     *
     * Which direction that is depends on the layout, because that is what the
     * cross axis means: in the default row it centres them vertically, in a
     * `vertical()` column it centres them horizontally.
     */
    public function alignCenter(bool $align = true): static
    {
        $this->alignCenter = $align;

        return $this;
    }

    /**
     * Align the children at the start of the cross axis — top in a row, leading
     * edge in a column.
     */
    public function alignStart(): static
    {
        $this->alignCenter = false;

        return $this;
    }

    /**
     * The literal cross-axis utility, or '' when the author never asked.
     *
     * The distinction is load-bearing in a column and invisible in a row. A row
     * has always centred by default, so an unset alignment resolves to
     * `items-center` there. A column has always *stretched* — the vertical branch
     * of the partial simply never printed this class, so `alignStart()` was inert
     * after `vertical()` — and printing the row's default into it would have
     * re-laid-out every existing vertical split. Unset therefore stays stretch,
     * and only an explicit call emits anything.
     */
    public function getAlignClass(): string
    {
        if ($this->alignCenter === null) {
            return $this->layout === 'vertical' ? '' : 'items-center';
        }

        return $this->alignCenter ? 'items-center' : 'items-start';
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
     * The attribute the split header orders by: the first sortable child.
     *
     * Falls back to the column's own name, mirroring {@see isSortable()} — a
     * split registered under a real attribute and marked `->sortable()` itself
     * still sorts by it. Until 2.0 nothing called this method, so the header
     * ordered by the split's own name; where that name was a label rather than
     * an attribute (`SplitColumn::split([...], 'identity')`, the documented
     * form) clicking it raised `no such column`.
     */
    public function getSortColumn(): ?string
    {
        foreach ($this->columns as $column) {
            if ($column->isSortable()) {
                return $column->getSortColumn();
            }
        }

        return parent::getSortColumn();
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
            'alignClass' => $this->getAlignClass(),
            'gapClass' => $this->getGapClass(),
            'imageHtml' => $imageHtml,
            'textColumns' => $textColumns,
        ]);
    }
}
