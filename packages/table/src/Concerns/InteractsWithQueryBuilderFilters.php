<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Filters\QueryBuilderFilter;
use NyonCode\WireTable\Support\QueryBuilderState;

/**
 * The buttons of a {@see QueryBuilderFilter}: add a condition, add a group,
 * remove one. Each finds the filter by name, hands its state to
 * {@see QueryBuilderState} and writes back what comes out — the edits
 * themselves live there, not here.
 */
trait InteractsWithQueryBuilderFilters
{
    /** Add a condition to query-builder filter `$filter`, at the top level or inside group `$group`. */
    public function queryBuilderAddRule(string $filter, ?int $group = null): void
    {
        $builder = $this->findQueryBuilderFilter($filter);

        if ($builder !== null) {
            $first = $builder->getFirstConstraint();
            $this->writeQueryBuilderState($filter, QueryBuilderState::addRule($this->queryBuilderState($filter), $first?->getName(), $first?->getDefaultOperator()->value, $group));
        }
    }

    /** Add a group of conditions — "any of" inside "all of", or the other way round. */
    public function queryBuilderAddGroup(string $filter): void
    {
        $builder = $this->findQueryBuilderFilter($filter);

        if ($builder !== null) {
            $first = $builder->getFirstConstraint();
            $this->writeQueryBuilderState($filter, QueryBuilderState::addGroup($this->queryBuilderState($filter), $first?->getName(), $first?->getDefaultOperator()->value));
        }
    }

    /** Remove condition `$index` (inside group `$group` when given). */
    public function queryBuilderRemove(string $filter, int $index, ?int $group = null): void
    {
        if ($this->findQueryBuilderFilter($filter) !== null) {
            $this->writeQueryBuilderState($filter, QueryBuilderState::remove($this->queryBuilderState($filter), $index, $group));
        }
    }

    protected function findQueryBuilderFilter(string $name): ?QueryBuilderFilter
    {
        foreach ($this->getTable()->getFilters() as $filter) {
            if ($filter instanceof QueryBuilderFilter && $filter->getName() === $name && $filter->canView()) {
                return $filter;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function queryBuilderState(string $filter): array
    {
        return QueryBuilderState::normalize($this->tableState->get('filters', [])[$filter] ?? null);
    }

    /** @param  array<string, mixed>  $state */
    protected function writeQueryBuilderState(string $filter, array $state): void
    {
        $filters = $this->tableState->get('filters', []);
        $filters[$filter] = $state;
        $this->tableState->set('filters', $filters);
        $this->resetPage();
    }
}
