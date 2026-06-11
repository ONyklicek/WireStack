<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Support\Str;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Livewire\TableUrl;
use NyonCode\WireTable\Table;

/**
 * Query-string persistence for table state (Table::queryString()).
 *
 * Two halves, both running from mountWithTable():
 *
 * 1. Seeding — URL parameters are validated and written into the
 *    StateContainer so a shared URL reproduces the same table view.
 *    Values are only accepted when they map to something the table
 *    actually exposes (sortable column, configured per-page option,
 *    viewable filter), so the URL cannot inject arbitrary state.
 *
 * 2. Tracking — a TableUrl attribute is registered per parameter so
 *    Livewire's JS keeps the URL in sync as the user interacts.
 *    The current page is already tracked by WithPagination.
 *
 * Filters whose names contain dots (relation filters) are skipped:
 * their state lives under a flat "author.name" key, which the JS-side
 * dot-notation watcher cannot address.
 */
trait WithTableQueryString
{
    /**
     * Seed state from the URL and register URL-tracking attributes.
     *
     * Must run after table defaults are applied so URL values win.
     */
    protected function initializeTableQueryString(Table $table): void
    {
        if (! $table->hasQueryString()) {
            return;
        }

        $prefix = $table->getQueryStringPrefix();

        $this->seedTableStateFromQueryString($table, $prefix);

        foreach ($this->tableQueryStringEntries($table, $prefix) as $entry) {
            $this->setPropertyAttribute(
                'tableState.'.$entry['path'],
                new TableUrl(as: $entry['param'], except: $entry['except']),
            );
        }
    }

    /**
     * URL-tracked state paths with their parameter names and "except"
     * values (the value at which the parameter is dropped from the URL).
     *
     * @return array<int, array{path: string, param: string, except: mixed}>
     */
    protected function tableQueryStringEntries(Table $table, string $prefix): array
    {
        $entries = [];

        if ($table->isSearchable()) {
            $entries[] = [
                'path' => 'search',
                'param' => $prefix.'search',
                'except' => '',
            ];
        }

        if ($table->getSortableColumns() !== []) {
            $entries[] = [
                'path' => 'sort.column',
                'param' => $prefix.'sort',
                'except' => $table->getDefaultSort() ?? '',
            ];
            $entries[] = [
                'path' => 'sort.direction',
                'param' => $prefix.'direction',
                'except' => $table->getDefaultSort() ? $table->getDefaultSortDirection() : 'asc',
            ];
        }

        $entries[] = [
            'path' => 'pagination.perPage',
            'param' => $prefix.'per_page',
            'except' => $table->getPerPage(),
        ];

        foreach ($this->queryStringableFilters($table) as $filter) {
            foreach ($filter->getQueryStringFields() as $field => $suffix) {
                $entries[] = [
                    'path' => "filters.{$filter->getName()}.{$field}",
                    'param' => $prefix.'filter_'.$filter->getName().$suffix,
                    'except' => '',
                ];
            }
        }

        return $entries;
    }

    /**
     * Validate URL parameters and write the accepted ones into tableState.
     */
    protected function seedTableStateFromQueryString(Table $table, string $prefix): void
    {
        if ($table->isSearchable()) {
            $search = $this->tableQueryStringParam($prefix.'search');
            if (is_string($search) && $search !== '') {
                $this->tableState->set('search', $search);
            }
        }

        $sort = $this->tableQueryStringParam($prefix.'sort');
        $sortableNames = array_map(fn ($column) => $column->getName(), $table->getSortableColumns());
        if (is_string($sort) && in_array($sort, $sortableNames, true)) {
            $this->tableState->set('sort.column', $sort);
            $direction = $this->tableQueryStringParam($prefix.'direction');
            $this->tableState->set('sort.direction', $direction === 'desc' ? 'desc' : 'asc');
        }

        $perPage = $this->tableQueryStringParam($prefix.'per_page');
        if (is_numeric($perPage) && in_array((int) $perPage, $table->getPerPageOptions(), true)) {
            $this->tableState->set('pagination.perPage', (int) $perPage);
        }

        $this->seedFiltersFromQueryString($table, $prefix);
    }

    protected function seedFiltersFromQueryString(Table $table, string $prefix): void
    {
        // Filter state is keyed flat by full filter name — read/modify/write
        // the whole array instead of dot-path sets, which would nest the key.
        $filters = $this->tableState->get('filters', []);
        $changed = false;

        foreach ($this->queryStringableFilters($table) as $filter) {
            foreach ($filter->getQueryStringFields() as $field => $suffix) {
                $value = $this->tableQueryStringParam($prefix.'filter_'.$filter->getName().$suffix);

                if (is_array($value)) {
                    if (! $filter->isMultiple()) {
                        continue;
                    }
                    $value = array_values(array_filter($value, 'is_scalar'));
                    if ($value === []) {
                        continue;
                    }
                } elseif (! is_string($value) || $value === '') {
                    continue;
                }

                $filters[$filter->getName()][$field] = $value;
                $changed = true;
            }
        }

        if ($changed) {
            $this->tableState->set('filters', $filters);
        }
    }

    /**
     * Filters eligible for query-string persistence: viewable, and without
     * dots in the name (flat state key vs. JS dot-path mismatch).
     *
     * @return array<int, Filter>
     */
    protected function queryStringableFilters(Table $table): array
    {
        return array_values(array_filter(
            $table->getFilters(),
            fn (Filter $filter) => $filter->canView() && ! Str::contains($filter->getName(), '.'),
        ));
    }

    /**
     * Read a parameter from the page URL.
     *
     * On the initial page load that is the current request; when mount runs
     * during a Livewire request (lazy tables), the page URL only exists in
     * the Referer header — mirrors BaseUrl::getFromUrlQueryString().
     */
    protected function tableQueryStringParam(string $key): mixed
    {
        if (! app('livewire')->isLivewireRequest()) {
            return request()->query($key);
        }

        $query = [];
        parse_str(parse_url((string) request()->header('Referer'), PHP_URL_QUERY) ?: '', $query);

        return $query[$key] ?? null;
    }
}
