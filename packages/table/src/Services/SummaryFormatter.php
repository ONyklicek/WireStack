<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Closure;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Support\SummaryFormat;

/**
 * Renders a computed summary value.
 *
 * Extracted from the HasSummary trait, where number formatting lived alongside
 * SQL aggregation and statistics — and was inherited by all 13 column types.
 * Stateless: everything it needs about a column arrives as a
 * {@see SummaryFormat}, so it is testable without one.
 */
final class SummaryFormatter
{
    /**
     * Apply the column's numeric formatting (decimals + prefix/suffix).
     *
     * Non-numeric and already-formatted results (a range, first/last text) pass
     * through untouched.
     */
    public function format(SummaryType|Closure $type, mixed $value, SummaryFormat $format): mixed
    {
        if ($value === null) {
            return null;
        }

        // A range is already a formatted "min – max" string.
        if ($type === SummaryType::Range) {
            return $value;
        }

        // Counts are not money — leave them bare rather than decorate them.
        if ($type instanceof SummaryType && $type->isCount()) {
            return $value;
        }

        if (! is_numeric($value)) {
            return $value;
        }

        // Nothing to apply — preserve the raw int/float untouched.
        if ($format->decimals === null && ! $format->hasDecorations()) {
            return $value;
        }

        return $this->decorate($this->numeric($value, $format), $format);
    }

    /** Format a numeric value using the configured decimals/separators. */
    public function numeric(mixed $value, SummaryFormat $format): string
    {
        if (! is_numeric($value)) {
            // First/Last summaries may surface enum-cast values; render their label.
            return (string) EnumResolver::label($value);
        }

        if ($format->decimals === null) {
            return (string) $value;
        }

        return number_format(
            (float) $value,
            $format->decimals,
            $format->decimalSeparator,
            $format->thousandsSeparator,
        );
    }

    /** A "min – max" pair, each side formatted. */
    public function range(mixed $min, mixed $max, SummaryFormat $format): string
    {
        return $this->numeric($min, $format).' – '.$this->numeric($max, $format);
    }

    /** The label a summary carries when the author did not name one. */
    public function defaultLabel(SummaryType|Closure $type): string
    {
        if ($type instanceof Closure) {
            return Trans::get('wire-table::messages.summary_total');
        }

        return $type->label();
    }

    /** Wrap a formatted number in the column's prefix/suffix. */
    private function decorate(string $formatted, SummaryFormat $format): string
    {
        if ($format->prefix !== null) {
            $formatted = $format->prefix.$formatted;
        }

        if ($format->suffix !== null) {
            $formatted .= $format->suffix;
        }

        return $formatted;
    }
}
