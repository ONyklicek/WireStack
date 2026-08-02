<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Concerns\HasSize;
use NyonCode\WireForms\Concerns\CanBeSearchable;
use NyonCode\WireForms\Concerns\HasChoiceVariants;
use NyonCode\WireForms\Concerns\HasOptions;

/**
 * Multiple checkbox list with search, bulk toggle, grouped options, and column layout.
 *
 * {@see segmented()} and {@see buttons()} render the same chrome as the matching
 * Radio variants — the multiple-choice half of that shared vocabulary — where the
 * list is short enough to read as a row of toggle buttons. Those variants show the
 * options alone: search, bulk toggle, grouping and columns are list chrome and do
 * not apply.
 */
class CheckboxList extends Field
{
    use CanBeSearchable;

    // segmented()/buttons()/inline()/icons()/color()/colors() are the choice
    // vocabulary shared with Radio.
    use HasChoiceVariants;
    use HasOptions;
    use HasSize;

    /** @var int|array<string|int, int|string> */
    protected int|array $columns = 1;

    protected bool $bulkToggleable = false;

    protected ?string $selectAllLabel = null;

    protected ?string $deselectAllLabel = null;

    protected bool $grouped = false;

    /** @var array<string, array<string, string>>|Closure */
    protected array|Closure $groups = [];

    /**
     * Number of option columns. Pass an int for a mobile-first reflow, or a
     * Filament-style per-breakpoint map, e.g. ['default' => 1, 'md' => 2, 'lg' => 3].
     *
     * @param  int|array<string|int, int|string>  $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /** Add "select all / deselect all" toggles above the list. */
    public function bulkToggleable(bool $condition = true): static
    {
        $this->bulkToggleable = $condition;

        return $this;
    }

    /** Set the label for the "select all" toggle. */
    public function selectAllLabel(?string $label): static
    {
        $this->selectAllLabel = $label;

        return $this;
    }

    /** Set the label for the "deselect all" toggle. */
    public function deselectAllLabel(?string $label): static
    {
        $this->deselectAllLabel = $label;

        return $this;
    }

    /** Render the options grouped under headings. */
    public function grouped(bool $condition = true): static
    {
        $this->grouped = $condition;

        return $this;
    }

    /**
     * Set the grouped options as a heading => [value => label] map (implies {@see grouped()}).
     *
     * @param  array<string, array<string, string>>|Closure  $groups
     */
    public function groups(array|Closure $groups): static
    {
        $this->groups = $groups;
        $this->grouped = true;

        return $this;
    }

    /**
     * @return int|array<string|int, int|string>
     */
    public function getColumns(): int|array
    {
        return $this->columns;
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

    public function getStateType(): string
    {
        // A checkbox list always holds an array of selected option keys.
        return 'array';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.checkbox-list';
    }
}
