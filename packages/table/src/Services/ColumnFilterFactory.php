<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;

/**
 * Builds the canonical {@see Filter} behind a column's header filter.
 *
 * Which filter class backs `filterAsSelect()`, and how it is configured, is a
 * vocabulary — and it was spelled out inside Column, which had to import all five
 * concrete filter types to say it. Column now asks for a filter and gets one; this
 * is the only place that knows the mapping.
 *
 * Not to be confused with a base class knowing its subclasses: `Column extends
 * DataComponent` and `Filter` is its own root, so these were never siblings. The
 * problem was a factory living inside a component, not an inheritance cycle.
 */
final class ColumnFilterFactory
{
    public function text(string $column): Filter
    {
        return TextFilter::make($column);
    }

    /**
     * @param  array<string, string>|class-string  $options
     */
    public function select(
        string $column,
        array|string $options,
        ?string $placeholder = null,
        bool $multiple = false,
    ): Filter {
        $filter = SelectFilter::make($column);

        if ($multiple) {
            $filter->multiple();
        }

        $filter->options($options)->searchable();

        if ($placeholder) {
            $filter->placeholder($placeholder);
        }

        return $filter;
    }

    public function date(string $column, ?string $minDate = null, ?string $maxDate = null, bool $range = false): Filter
    {
        $filter = DateFilter::make($column);

        if ($range) {
            $filter->range();
        }

        return $filter->minDate($minDate)->maxDate($maxDate);
    }

    public function numberRange(string $column, ?float $min = null, ?float $max = null, ?float $step = null): Filter
    {
        return NumberRangeFilter::make($column)->min($min)->max($max)->step($step);
    }

    public function boolean(string $column, ?string $trueLabel = null, ?string $falseLabel = null): Filter
    {
        return TernaryFilter::make($column)->nullable()->trueLabel($trueLabel)->falseLabel($falseLabel);
    }

    /**
     * The legacy `filterable(type: 'select')` vocabulary. Kept for the string
     * form; the fluent filterAs*() helpers are the current way in.
     *
     * @param  array<string, string>|class-string  $options
     */
    public function ofType(string $type, string $column, array|string $options = []): Filter
    {
        return match ($type) {
            'select' => $this->select($column, $options),
            'multi_select' => $this->select($column, $options, multiple: true),
            'date' => $this->date($column),
            'date_range' => $this->date($column, range: true),
            'number_range' => $this->numberRange($column),
            'boolean' => $this->boolean($column),
            default => $this->text($column),
        };
    }
}
