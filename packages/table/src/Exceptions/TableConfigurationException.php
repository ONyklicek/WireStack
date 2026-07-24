<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a table is handed an argument its definition cannot accept.
 *
 * Extends InvalidArgumentException because every site it replaces threw one —
 * the SPL base is part of the published behaviour, so it is preserved verbatim.
 * A bad table *state* (rather than a bad argument) is
 * {@see TableHasNoDataSourceException}, which stays a RuntimeException for the
 * same reason.
 */
final class TableConfigurationException extends InvalidArgumentException implements WireException
{
    public static function invalidPollInterval(): self
    {
        return new self('Interval must be like "5s", "500ms", "10m" or "1h".');
    }

    public static function relationPathNotGroupable(string $column): self
    {
        return new self(
            "groupBy() only supports direct columns, got [{$column}]. ".
            'Expose the related value as a column on the query (join/select alias) and group by that.'
        );
    }

    /**
     * @param  array<int, string>  $valid
     */
    public static function unknownSummaryType(string $type, array $valid): self
    {
        return new self(
            "Unknown summary type [{$type}]. Valid types: ".implode(', ', $valid).'.'
        );
    }

    public static function recordActionInRowActions(): self
    {
        return new self(
            'A RecordAction cannot be registered in actions(). '.
            'Action::make()->onDoubleClick() returns a RecordAction — pass it to '.
            'recordAction()/recordActions() instead.'
        );
    }

    public static function cannotConfigureReferencedRecordAction(string $method, string $name): self
    {
        return new self(
            "Cannot call {$method}() on the record action referencing [{$name}]: a ".
            'reference by name has no action of its own to configure. Configure it '.
            'where it is declared in actions(), or wrap an Action instead of naming one.'
        );
    }

    public static function subRowRelationMissing(string $relation, string $model): self
    {
        return new self(
            "subRows('{$relation}') expects a relationship method [{$relation}()] on ".
            "[{$model}], but none exists. Check the spelling, or define the relationship."
        );
    }
}
