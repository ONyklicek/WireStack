<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Query\Search\SearchValueType;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Exceptions\TableConfigurationException;

/**
 * Whether the table's search box reaches this column, and how.
 *
 * Pure configuration: the capability is the single source of truth for "is this
 * column searched" ({@see Column} declares no `$searchable` boolean), and the
 * three settings below narrow *what* is searched. Turning any of them into SQL
 * belongs to the query seam — `Services\TableQueryService` reads
 * {@see getSearchColumns()} and {@see getSearchCallback()}, and the comparison
 * syntax reads {@see getSearchValueType()}.
 *
 * `searchUsing()` opts the column in as a side effect, which is deliberate: a
 * column handed a search callback and left un-searchable would silently never
 * run it.
 *
 * @phpstan-require-extends Column
 */
trait CanBeSearchable
{
    /** @var array<int, string> Explicit DB columns to search (Filament-style: searchable(['first_name', 'last_name'])) */
    protected array $searchColumns = [];

    /** What the column holds for search purposes (null = infer from the model's casts). */
    protected ?SearchValueType $searchValueType = null;

    /** @var Closure|null Custom search query callback: fn(Builder, string) => */
    protected ?Closure $searchCallback = null;

    /**
     * Set whether the column is searchable.
     *
     * Filament-style API:
     *   ->searchable()                                    // auto-detect
     *   ->searchable(['first_name', 'last_name'])         // explicit DB columns
     *   ->searchable(query: fn($query, $search) => ...)   // custom query callback
     *
     * @param  bool|array<int, string>  $searchable
     */
    public function searchable(bool|array $searchable = true, ?Closure $query = null): static
    {
        if (is_array($searchable)) {
            $this->searchColumns = $searchable;
            $this->capabilities = $this->capabilities->add(Capability::Searchable);
        } else {
            $this->capabilities = $searchable
                ? $this->capabilities->add(Capability::Searchable)
                : $this->capabilities->remove(Capability::Searchable);
        }

        if ($query !== null) {
            $this->searchCallback = $query;
        }

        return $this;
    }

    /**
     * Check if the column is searchable.
     */
    public function isSearchable(): bool
    {
        return $this->hasCapability(Capability::Searchable);
    }

    /**
     * Get explicit search columns (empty = use auto-detection).
     *
     * @return array<int, string>
     */
    public function getSearchColumns(): array
    {
        return $this->searchColumns;
    }

    /**
     * Declare what this column holds for search purposes.
     *
     * Only needed when nothing can be inferred — no cast on the model and no
     * usable database type — and only matters once the table opts into
     * comparison syntax.
     */
    public function searchAs(SearchValueType|string $type): static
    {
        $this->searchValueType = $type instanceof SearchValueType
            ? $type
            : SearchValueType::tryFrom($type) ?? throw TableConfigurationException::unknownSearchValueType(
                $type,
                array_map(static fn (SearchValueType $c): string => $c->value, SearchValueType::cases()),
            );

        return $this;
    }

    public function getSearchValueType(): ?SearchValueType
    {
        return $this->searchValueType;
    }

    /**
     * Set a custom search query callback.
     *
     * @param  Closure  $callback  fn(Builder $query, string $search): Builder
     */
    public function searchUsing(Closure $callback): static
    {
        $this->capabilities = $this->capabilities->add(Capability::Searchable);
        $this->searchCallback = $callback;

        return $this;
    }

    /**
     * Get the custom search callback.
     */
    public function getSearchCallback(): ?Closure
    {
        return $this->searchCallback;
    }
}
