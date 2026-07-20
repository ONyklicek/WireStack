<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Exceptions\TableConfigurationException;

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
            throw TableConfigurationException::relationPathNotGroupable($column);
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
     * Group value for a record (raw, used for the header label).
     */
    public function getGroupValue(mixed $record): mixed
    {
        if ($this->groupColumn === null) {
            return null;
        }

        return data_get($record, $this->groupColumn);
    }

    /**
     * Normalised scalar key for grouping a record, used for boundary detection and
     * subtotal partitioning.
     *
     * The raw value must never be compared with `===`: a `date`/`datetime`-cast
     * column yields a fresh Carbon per record, so two equal dates are distinct
     * objects and every row would become its own group. Enum cases are singletons
     * (identity-safe) but are still normalised to their scalar for consistency.
     */
    public function getGroupComparisonKey(mixed $record): string|int|float|bool|null
    {
        $value = $this->getGroupValue($record);

        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.u');
        }

        return method_exists($value, '__toString') ? (string) $value : spl_object_hash($value);
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
