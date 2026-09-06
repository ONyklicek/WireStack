<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireTable\Columns\Column;

/**
 * Whether the column's header sorts the table, and how.
 *
 * Pure configuration, and the mirror of {@see CanBeSearchable}: the capability is
 * the single source of truth, and `sortUsing()` opts the column in for the same
 * reason — a sort callback on a column no header can click is dead code.
 *
 * The callback is applied by `Services\TableQueryService`; without one the sort
 * is a plain `ORDER BY` on {@see getSortColumn()}. Not to be confused with
 * `Foundation\Concerns\HasSortOrder`, which orders *items in a list* (navigation
 * groups, menu entries) and never touches a query.
 *
 * @phpstan-require-extends Column
 */
trait CanBeSorted
{
    /** @var Closure|null Custom sort query callback: fn(Builder, string) => */
    protected ?Closure $sortCallback = null;

    public function isSortable(): bool
    {
        return $this->hasCapability(Capability::Sortable);
    }

    /**
     * Set whether the column is sortable.
     *
     * Supports custom sort callback:
     *   ->sortable()
     *   ->sortable(query: fn($query, $direction) => ...)
     */
    public function sortable(bool $sortable = true, ?Closure $query = null): static
    {
        $this->capabilities = $sortable
            ? $this->capabilities->add(Capability::Sortable)
            : $this->capabilities->remove(Capability::Sortable);

        if ($query !== null) {
            $this->sortCallback = $query;
        }

        return $this;
    }

    /**
     * Set a custom sort query callback.
     *
     * @param  Closure  $callback  fn(Builder $query, string $direction): Builder
     */
    public function sortUsing(Closure $callback): static
    {
        $this->capabilities = $this->capabilities->add(Capability::Sortable);
        $this->sortCallback = $callback;

        return $this;
    }

    /**
     * Get the custom sort callback.
     */
    public function getSortCallback(): ?Closure
    {
        return $this->sortCallback;
    }

    /**
     * The attribute the header actually orders by.
     *
     * For an ordinary column that is its own name — including a dotted relation
     * path, which the planner resolves. A **composite** column overrides it,
     * because the name it is registered under is a label for the group and not
     * an attribute of anything: `SplitColumn` answers with the first sortable
     * child it holds. (Named in prose rather than `{@see}` on purpose — Pint's
     * `fully_qualified_strict_types` would turn the reference into an import of
     * a subclass, from the trait that subclass inherits the method through.)
     *
     * Null means "sortable, but nothing to order by" and the sort is dropped
     * rather than guessed at.
     */
    public function getSortColumn(): ?string
    {
        return $this->getName();
    }
}
