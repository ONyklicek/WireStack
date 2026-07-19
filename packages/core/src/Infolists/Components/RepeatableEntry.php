<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Repeatable entry — renders a nested entry schema once per item of an
 * iterable state (e.g. a `hasMany` relation or an array of rows).
 *
 * Actions declared via the inherited `actions()` render once per row and, when
 * dispatched, receive that row's item as `$record` / `$state` — the host
 * resolves the row by its zero-based index (see the host's
 * callInfolistAction()).
 *
 * When the rows are Eloquent models whose child entries read nested relation
 * paths (e.g. `product.name`), declare those relations with {@see with()} to
 * eager-load them once instead of lazily per row (N+1).
 */
class RepeatableEntry extends Entry
{
    /** @var array<int, Entry> */
    protected array $schema = [];

    protected int $columns = 1;

    protected bool $contained = true;

    /** @var array<int, string> */
    protected array $with = [];

    /**
     * Set the entry schema rendered once per item.
     *
     * @param  array<int, Entry>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * @return array<int, Entry>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /** Set the number of columns each item's entries lay out across (default 1). */
    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    /** Wrap each item in a bordered card (default true). */
    public function contained(bool $condition = true): static
    {
        $this->contained = $condition;

        return $this;
    }

    public function isContained(): bool
    {
        return $this->contained;
    }

    /**
     * Relations to eager-load on the row items before rendering, preventing N+1
     * queries when child entries read nested relation paths. Accepts a single
     * relation or a list; repeated calls merge.
     *
     * @param  array<int, string>|string  $relations
     */
    public function with(array|string $relations): static
    {
        $this->with = array_values(array_unique(array_merge(
            $this->with,
            is_array($relations) ? $relations : [$relations],
        )));

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getWith(): array
    {
        return $this->with;
    }

    /**
     * Deep-clone the child schema. A shallow clone would share child entry
     * instances with the original and every other clone — the same pattern that
     * leaked per-item state paths across Repeater items in forms. Matters as
     * soon as a child is itself a RepeatableEntry (nested rows), where the
     * per-row clone in getRows() must not share the inner schema.
     */
    public function __clone(): void
    {
        $this->schema = array_map(
            static fn (Entry $entry): Entry => clone $entry,
            $this->schema,
        );
    }

    /**
     * The raw row items resolved from the state, re-indexed from zero so a row's
     * position is a stable key for per-row action dispatch.
     *
     * @return array<int, mixed>
     */
    public function getRowItems(): array
    {
        $items = $this->getState();

        if (is_iterable($items) && ! is_array($items)) {
            $items = iterator_to_array($items);
        }

        if (! is_array($items)) {
            return [];
        }

        $items = array_values($items);

        $this->eagerLoadItems($items);

        return $items;
    }

    /**
     * Eager-load the declared relations across every model row in one query per
     * relation. Rows are objects, so loading them onto a temporary collection
     * populates the very instances the child entries render from; `loadMissing`
     * skips relations already present.
     *
     * @param  array<int, mixed>  $items
     */
    protected function eagerLoadItems(array $items): void
    {
        if ($this->with === [] || $items === []) {
            return;
        }

        $models = array_values(array_filter(
            $items,
            static fn ($item): bool => $item instanceof Model,
        ));

        if ($models === []) {
            return;
        }

        (new EloquentCollection($models))->loadMissing($this->with);
    }

    /**
     * One row per state item, each a fresh clone of the schema bound to the
     * item record. Row order matches {@see getRowItems()} (zero-based).
     *
     * @return array<int, array<int, Entry>>
     */
    public function getRows(): array
    {
        $rows = [];

        foreach ($this->getRowItems() as $item) {
            $entries = [];

            foreach ($this->schema as $entry) {
                $clone = clone $entry;
                $clone->record($item);
                $entries[] = $clone;
            }

            $rows[] = $entries;
        }

        return $rows;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.repeatable';
    }
}
