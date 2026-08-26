<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Data\DataSource;
use NyonCode\WireTable\Data\EloquentDataSource;
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

    /**
     * Memoised once resolved, so a table asked twice does not build two sources
     * over two clones of the base query.
     */
    protected ?DataSource $dataSource = null;

    /**
     * Tracked separately from `$dataSource` because that field is also the
     * memoisation slot for the default — once `getDataSource()` has run, "is
     * set" no longer means "was given".
     */
    protected bool $customDataSource = false;

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

    /**
     * Hand the table a data source of its own instead of letting it build one.
     *
     * The opt-in half of V2.0: a table fed by a read model, a DTO collection or
     * an API declares its source here, and the engine asks that source what it
     * can do rather than assuming Eloquent.
     *
     * @internal Until V2.0.c settles the public surface — the contract still
     *           grows record resolution in V2.0.b, and a consumer who
     *           implemented it now would be broken by that.
     */
    public function dataSource(DataSource $source): static
    {
        $this->dataSource = $source;
        $this->customDataSource = true;

        return $this;
    }

    /**
     * The table's data source: the one it was given, or an Eloquent source over
     * {@see getQuery()}.
     *
     * The fallback is what keeps every existing table working unchanged — a
     * table that never heard of `DataSource` still has one, and it is the source
     * whose behaviour is the V1 anchor.
     *
     * Note what the default is built from: the *base* query, before search,
     * filters and sorting. `TableQueryService::buildQuery()` is still what
     * narrows it, so this is not yet the whole read path — see the V2.0.a
     * scope in `architecture/plans/v2.0-datasource-implementation.md`.
     *
     * @internal See {@see DataSource()}.
     *
     * @throws TableHasNoDataSourceException when neither a source, a model nor a query was given
     */
    public function getDataSource(): DataSource
    {
        return $this->dataSource ??= new EloquentDataSource($this->getQuery());
    }

    /**
     * Whether a source was handed in rather than derived from a model or query.
     *
     * Asked by the engine before it assumes Eloquent-shaped behaviour it cannot
     * express through the contract.
     */
    public function hasCustomDataSource(): bool
    {
        return $this->customDataSource;
    }
}
