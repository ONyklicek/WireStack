<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

/**
 * Where a table's rows come from — a model class or a prepared builder, plus
 * the caller's own chance to shape the base query.
 *
 * The boundary this trait draws is worth stating, because the two halves are
 * easy to conflate: **this owns where the rows come from**, and
 * {@see TableQueryService} owns **how they are then narrowed** (search, filters,
 * sorting, joins, eager loads, aggregates). Everything here runs before a
 * request is considered at all — {@see getQuery()} answers the same thing on a
 * table nobody has interacted with.
 *
 * A table needs exactly one of the two sources. A builder wins over a model
 * when both are given, and neither is an error rather than an empty table: a
 * table with no data source is a configuration mistake, and a silent empty
 * result would hide it.
 *
 * @phpstan-require-extends Table
 */
trait HasDataSource
{
    protected ?string $model = null;

    /** @var Builder<Model>|null */
    protected ?Builder $query = null;

    protected ?Closure $modifyQueryCallback = null;

    /** Build the table's rows from this Eloquent model. */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * The model class backing this table, when it has one.
     *
     * Null for a table built from a bare `query()`. Used as the invalidation
     * scope of the query cache, which is why it is a class name and not an
     * instance: it has to be nameable without touching the database.
     *
     * @return class-string<Model>|null
     */
    public function getModelClass(): ?string
    {
        return $this->model;
    }

    /**
     * Build the table's rows from a prepared query instead of a model.
     *
     * @param  Builder<Model>  $query
     */
    public function query(Builder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Modify the base query using a callback.
     *
     * This allows you to add custom conditions, joins, eager loading, etc.
     * The callback receives the query builder and should return it.
     *
     * Example:
     * ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true))
     * ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles', 'permissions']))
     * ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orders'))
     *
     * @param  Closure  $callback  Receives Builder, should return Builder
     */
    public function modifyQueryUsing(Closure $callback): static
    {
        $this->modifyQueryCallback = $callback;

        return $this;
    }

    /**
     * Get the callback for modifying the query.
     */
    public function getModifyQueryCallback(): ?Closure
    {
        return $this->modifyQueryCallback;
    }

    /**
     * The table's base query — the source, with the caller's modification
     * applied, before any search, filter or sort.
     *
     * A configured builder is cloned on every call, so a caller can never
     * accumulate constraints onto the table's own instance.
     *
     * @return Builder<Model>
     *
     * @throws TableHasNoDataSourceException when neither a model nor a query was given
     */
    public function getQuery(): Builder
    {
        if ($this->query) {
            $query = clone $this->query;
        } elseif ($this->model) {
            $query = $this->model::query();
        } else {
            throw TableHasNoDataSourceException::make();
        }

        if ($this->modifyQueryCallback) {
            // A callback that mutates in place and returns nothing is as valid
            // as one that returns the builder.
            $query = ($this->modifyQueryCallback)($query) ?? $query;
        }

        return $query;
    }
}
