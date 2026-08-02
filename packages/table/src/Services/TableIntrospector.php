<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Connection;
use NyonCode\WireTable\Concerns\HasSqlDebug;
use NyonCode\WireTable\Table;

/**
 * What a table is made of, as data — for a developer looking at it, never for
 * the render path.
 *
 * Two different questions, deliberately in one place because every answer needs
 * the same two things (the table's configuration and its base query):
 *
 *  - **what the table declares** — its columns and their capabilities, its
 *    filters, its search/sort/pagination settings;
 *  - **what the database holds underneath** — the real column listing and
 *    types, read from the schema builder, plus the query the planner would
 *    actually run.
 *
 * Stateless: the table is a parameter, so every answer can be exercised without
 * a Livewire host — the same shape as {@see SubRowFilters}. The `dump()` /
 * `dd()` sugar stays on {@see Table}, where it is one call away from the
 * developer typing it.
 */
final class TableIntrospector
{
    use HasSqlDebug;

    /**
     * Every declared column with the capabilities it carries.
     *
     * @return array<string, array<string, mixed>>
     */
    public function columns(Table $table): array
    {
        $info = [];

        foreach ($table->getColumns() as $column) {
            $info[$column->getName()] = [
                'name' => $column->getName(),
                'label' => $column->getLabel(),
                'sortable' => $column->isSortable(),
                'searchable' => $column->isSearchable(),
                'toggleable' => $column->isToggleable(),
                'visible' => $column->canView(),
                'editable' => $column->isEditable(),
                'type' => class_basename($column),
            ];
        }

        return $info;
    }

    /**
     * The column names the underlying database table actually has.
     *
     * @return array<int, string>
     */
    public function databaseColumns(Table $table): array
    {
        $query = $table->getQuery();
        /** @var Connection $connection */
        $connection = $query->getConnection();

        return $connection->getSchemaBuilder()->getColumnListing($query->getModel()->getTable());
    }

    /**
     * The same listing with each column's declared type.
     *
     * @return array<string, array{name: string, type: string}>
     */
    public function databaseColumnTypes(Table $table): array
    {
        $query = $table->getQuery();
        $tableName = $query->getModel()->getTable();
        /** @var Connection $connection */
        $connection = $query->getConnection();

        $schema = $connection->getSchemaBuilder();

        $columns = [];

        foreach ($schema->getColumnListing($tableName) as $columnName) {
            $columns[$columnName] = [
                'name' => $columnName,
                'type' => $schema->getColumnType($tableName, $columnName),
            ];
        }

        return $columns;
    }

    /**
     * The table's configuration and its base query, side by side.
     *
     * @return array<string, mixed>
     */
    public function configuration(Table $table): array
    {
        $query = $table->getQuery();

        return [
            'model' => $table->getModelClass(),
            'sql' => $table->toSql(),
            'raw_sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'columns' => $this->columns($table),
            'database_columns' => $this->databaseColumns($table),
            'filters' => array_map(fn ($filter) => $filter->getName(), $table->getFilters()),
            'searchable' => $table->isSearchable(),
            'sortable' => $table->isSortable(),
            'paginated' => $table->isPaginated(),
            'per_page' => $table->getPerPage(),
            'default_sort' => $table->getDefaultSort(),
            'default_sort_direction' => $table->getDefaultSortDirection(),
        ];
    }

    /**
     * The QueryPlan this table would produce — the planned joins, filters,
     * search, sorting, eager loads and aggregates — plus the SQL it compiles to.
     *
     * A development aid: the arguments simulate a request (a search term, filter
     * values, a sort) without one having happened.
     *
     * @param  array<string, mixed>  $filterValues
     * @return array<string, mixed>
     */
    public function queryPlan(
        Table $table,
        ?string $search = null,
        array $filterValues = [],
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
    ): array {
        $service = app(TableQueryService::class);

        $modifiedQuery = $service->buildQuery(
            baseQuery: $table->getQuery(),
            table: $table,
            search: $search,
            filterValues: $filterValues,
            sortColumn: $sortColumn ?? $table->getDefaultSort(),
            sortDirection: $sortDirection,
        );

        $plan = $service->getLastPlan();

        if ($plan === null) {
            return ['error' => 'No QueryPlan generated'];
        }

        return [
            'query_plan' => [
                'joins' => array_map(fn ($join) => [
                    'table' => $join->table,
                    'alias' => $join->alias,
                    'type' => $join->type,
                    'first' => $join->firstColumn,
                    'operator' => $join->operator,
                    'second' => $join->secondColumn,
                ], $plan->joins),
                'eager_loads' => $plan->eagerLoads,
                'aggregates' => array_map(fn ($aggregate) => [
                    'relation' => $aggregate->relation,
                    'function' => $aggregate->function,
                    'column' => $aggregate->column,
                ], $plan->aggregates),
                'filters' => array_map(fn ($filter) => [
                    'column' => $filter->column,
                    'operator' => $filter->operator,
                    'value' => $filter->value,
                    'table_alias' => $filter->tableAlias ?? null,
                    'is_relation' => $filter->isRelation,
                ], $plan->filters),
                'search_clauses' => array_map(fn ($clause) => [
                    'column' => $clause->column,
                    'table_alias' => $clause->tableAlias,
                    'is_relation' => $clause->isRelation,
                ], $plan->searchClauses),
                'sort_clauses' => array_map(fn ($clause) => [
                    'column' => $clause->column,
                    'direction' => $clause->direction,
                    'table_alias' => $clause->tableAlias ?? null,
                    'is_relation' => $clause->isRelation,
                ], $plan->sortClauses),
                'scopes' => $plan->scopes,
                'with_soft_deletes' => $plan->withSoftDeletes,
            ],
            'final_sql' => self::builderToSql($modifiedQuery),
            'raw_sql' => $modifiedQuery->toSql(),
            'bindings' => $modifiedQuery->getBindings(),
        ];
    }
}
