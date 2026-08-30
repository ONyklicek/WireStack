<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Core\Query\Search\SearchValueType;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireTable\Resources\Contracts\DescribesResource;

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

    public static function invalidPerPageOption(string $option): self
    {
        return new self(
            "Page size [{$option}] is not a number, and the only word a page size may be is \"all\"."
        );
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
    public static function unknownSearchValueType(string $type, array $valid): self
    {
        return new self(
            "Unknown search value type [{$type}]. Valid types: ".implode(', ', $valid).'.'
        );
    }

    /**
     * A declared search value type the table's search box can never ask for.
     *
     * The series of a structured code arrives as its own word — `8866 01..08`
     * is the word `8866` and the range `01..08` — so a code column is only
     * whole once the term is split as well, and the suggested line says so.
     */
    public static function searchValueTypeNeedsRanges(string $column, SearchValueType $type): self
    {
        $call = $type === SearchValueType::Code
            ? '->search(fn (SearchConfig $s) => $s->tokenize()->ranges())'
            : '->search(fn (SearchConfig $s) => $s->ranges())';

        return new self(
            "Column [{$column}] declares searchAs('{$type->value}'), but this table's search does not read ".
            'ranges, so the comparison it allows can never be typed into the box — the term is matched as '.
            "literal text and the table comes back empty. Add {$call} to the table, or drop the declaration."
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

    /**
     * @param  array<int, string>  $valid
     */
    public static function unknownGesture(string $capability, array $valid): self
    {
        return new self(
            "Unknown table gesture [{$capability}]. Valid gestures: ".implode(', ', $valid).'.'
        );
    }

    public static function notAResource(string $class): self
    {
        return new self(
            "[{$class}] cannot be registered as a resource: it does not implement ".
            DescribesResource::class.'. A resource declares its key, model and labels '.
            'through that contract, which is what the registry routes on.'
        );
    }

    public static function duplicateResourceKey(string $key, string $existing, string $incoming): self
    {
        return new self(
            "Two resources claim the key [{$key}]: [{$existing}] and [{$incoming}]. ".
            'A key is the config handle, the route segment and the introspection '.
            'name, so the second would silently take over routing for the first. '.
            'Override key() on one of them.'
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

    /**
     * @param  array<int, string>  $reserved
     */
    public static function reservedRecordActionKey(string $key, array $reserved): self
    {
        return new self(
            "onKey('{$key}') collides with the table's built-in keyboard navigation — ".
            'the key is reserved and the shortcut could never fire. Reserved keys: '.
            implode(', ', array_filter($reserved)).'. Bind a different key instead.'
        );
    }

    public static function genericEditorNotRendered(string $column): self
    {
        return new self(
            "Column [{$column}]: editable() cannot choose an editor — an ordinary ".
            'column renders no editor at all, whatever type is named. Use the column '.
            'that renders the one you want: TextInputColumn, SelectColumn, '.
            'ToggleColumn or CheckboxColumn. editable(bool) still switches editing '.
            'on and off for those.'
        );
    }

    public static function fixedFilterOptions(string $filter): self
    {
        return new self(
            "TrashedFilter::make('{$filter}')->options() cannot be set: the filter ".
            'switches the soft-delete scope rather than matching values, so its two '.
            'options are fixed. Rename them with withTrashedLabel() / onlyTrashedLabel().'
        );
    }

    public static function modelIsNotSoftDeletable(string $filter, string $model): self
    {
        return new self(
            "TrashedFilter::make('{$filter}') needs soft deletes, but [{$model}] does not ".
            'use the SoftDeletes trait. Add the trait (and a deleted_at column), or drop '.
            'the filter from the table.'
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
