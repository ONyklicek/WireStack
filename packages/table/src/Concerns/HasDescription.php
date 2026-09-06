<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireTable\Columns\Column;

/**
 * A second line of text under (or over) the cell's value.
 *
 * The closure form receives the record, so the description can be per-row while
 * the column stays one object. Position is held beside the text rather than
 * exposed by a getter: the cell partial reads both at once, and a description
 * without a side is not a thing that can be rendered.
 *
 * Distinct from core's `HasHelperText` / `HasHint`, which annotate an *input* in
 * a form; this annotates a value in a table.
 *
 * @phpstan-require-extends Column
 */
trait HasDescription
{
    /** @var string|Closure|null Additional description text shown below/above the cell content */
    protected string|Closure|null $description = null;

    /** @var string Position of the description relative to the cell content ('below' or 'above') */
    protected string $descriptionPosition = 'below';

    /**
     * Add description text below or above the main content
     */
    public function description(string|Closure $description, string $position = 'below'): static
    {
        $this->description = $description;
        $this->descriptionPosition = $position;

        return $this;
    }

    public function getDescription(): string|Closure|null
    {
        return $this->description;
    }
}
