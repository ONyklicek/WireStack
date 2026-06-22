<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Columns\Column;

/**
 * Shared raw value resolution for exporters.
 *
 * Owns the column-name semantics every export format needs: rollup columns
 * read their computed withCount/withSum attribute (e.g. items_sum_total),
 * dotted names walk relations, everything else is a direct attribute.
 * Format-specific casting stays in each exporter.
 */
trait ResolvesExportValue
{
    protected function resolveRawExportValue(Column $column, Model $record): mixed
    {
        $name = $column->getName();

        // Rollup columns expose their value as a computed aggregate attribute,
        // not under the column name (unless the names happen to match).
        if ($column->isAggregate()) {
            $attribute = $column->getAggregateAttribute() ?? $name;

            $value = $record->getAttribute($attribute) ?? $record->getAttribute($name);
        } elseif (str_contains($name, '.')) {
            // Relationship columns (e.g. "user.name")
            $value = data_get($record, $name);
        } else {
            $value = $record->getAttribute($name);
        }

        // Enum- and array/JSON-cast attributes export as their display value (label / compact
        // JSON), matching the on-screen table; scalar values pass through untouched.
        return EnumResolver::display($value);
    }
}
