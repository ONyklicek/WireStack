<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Columns\Column;

/**
 * Shared value normalisation for exporters.
 *
 * The column owns the raw-value semantics (rollup attribute, relation walk,
 * direct attribute) via {@see Column::getRawState()}; this trait adds the
 * export-facing display step every format shares. Format-specific casting stays
 * in each exporter.
 */
trait ResolvesExportValue
{
    protected function resolveRawExportValue(Column $column, Model $record): mixed
    {
        // Enum- and array/JSON-cast attributes export as their display value (label / compact
        // JSON), matching the on-screen table; scalar values pass through untouched.
        return EnumResolver::display($column->getRawState($record));
    }

    /**
     * Neutralise CSV/spreadsheet formula injection. A string a spreadsheet would
     * evaluate — leading `=`, `@`, tab or CR, or a leading `+`/`-` that is not a
     * plain number — is prefixed with a single quote so Excel/Sheets treat it as
     * literal text (OpenSpout also turns a leading-`=` string into a live
     * formula cell). A leading `+`/`-` on a numeric value is left alone so numeric
     * exports stay numeric.
     */
    protected function escapeFormula(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $risky = in_array($first, ['=', '@', "\t", "\r"], true)
            || (in_array($first, ['+', '-'], true) && ! is_numeric($value));

        return $risky ? "'".$value : $value;
    }
}
