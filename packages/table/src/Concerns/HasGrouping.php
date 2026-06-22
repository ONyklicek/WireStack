<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Trait HasGrouping
 *
 * Groups table rows by a column value: rows are ordered so groups stay
 * contiguous, each group gets a header row, and columns with summaries get a
 * per-group subtotal row (plus the usual grand-total footer).
 *
 * Usage on Table:
 *
 *   $table
 *       ->groupBy('customer')                              // group rows by value
 *       ->groupLabel(fn ($value, $record) => "🧾 $value")  // optional header label
 *       ->groupSummaries(false)                            // disable subtotal rows
 *
 * Grouping is page-scoped: subtotals cover the group's rows on the current
 * page. Groups spanning a page boundary show partial subtotals per page —
 * disable pagination (or raise perPage) for strict accounting reports.
 */
trait HasGrouping
{
    protected ?string $groupColumn = null;

    protected string|Closure|null $groupLabel = null;

    protected bool $groupSummaries = true;

    /**
     * Group rows by a column on the table's model. Only direct database
     * columns are supported — relationship paths cannot be ordered without
     * a join, which grouping requires to keep groups contiguous.
     */
    public function groupBy(string $column): static
    {
        if (str_contains($column, '.')) {
            throw new InvalidArgumentException(
                "groupBy() only supports direct columns, got [{$column}]. ".
                'Expose the related value as a column on the query (join/select alias) and group by that.',
            );
        }

        $this->groupColumn = $column;

        return $this;
    }

    /**
     * Customize the group header label.
     *
     * String: used as a prefix ("Customer: ACME"). Closure: receives the group
     * value and the group's first record, returns the full label.
     */
    public function groupLabel(string|Closure $label): static
    {
        $this->groupLabel = $label;

        return $this;
    }

    /**
     * Toggle per-group subtotal rows for columns with summaries.
     */
    public function groupSummaries(bool $enabled = true): static
    {
        $this->groupSummaries = $enabled;

        return $this;
    }

    public function hasGrouping(): bool
    {
        return $this->groupColumn !== null;
    }

    public function getGroupColumn(): ?string
    {
        return $this->groupColumn;
    }

    public function hasGroupSummaries(): bool
    {
        return $this->groupSummaries;
    }

    /**
     * Group value for a record (raw, used for boundary comparison).
     */
    public function getGroupValue(mixed $record): mixed
    {
        if ($this->groupColumn === null) {
            return null;
        }

        return data_get($record, $this->groupColumn);
    }

    /**
     * Human label for a record's group header row.
     */
    public function resolveGroupLabel(mixed $record): string
    {
        $value = $this->getGroupValue($record);

        if ($this->groupLabel instanceof Closure) {
            return (string) call_user_func($this->groupLabel, $value, $record);
        }

        // Group values may be enum-cast attributes; render their display label.
        $value = EnumResolver::label($value);
        $label = $value === null || $value === '' ? '—' : (string) $value;

        if (is_string($this->groupLabel)) {
            return $this->groupLabel.': '.$label;
        }

        return $label;
    }
}
