<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\TableIntrospector;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

/*
 * What a table is made of, as data. These answers used to be ~250 lines inlined
 * on Table and were reachable only by calling the dump/dd sugar, which is why
 * none of them had ever been covered.
 */

class TinsProduct extends Model
{
    protected $table = 'tins_products';

    protected $guarded = [];

    public $timestamps = false;
}

function tinsTable(): Table
{
    return Table::make()
        ->model(TinsProduct::class)
        ->columns([
            Column::make('name')->sortable()->searchable(),
            TextInputColumn::make('sku'),
        ])
        ->filters([TextFilter::make('name')])
        ->defaultSort('name', 'desc')
        ->perPage(25);
}

beforeEach(function () {
    Schema::create('tins_products', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('sku');
        $t->integer('stock');
    });

    $this->introspector = new TableIntrospector;
});

it('reports every declared column with the capabilities it carries', function () {
    $columns = $this->introspector->columns(tinsTable());

    expect($columns)->toHaveKeys(['name', 'sku'])
        ->and($columns['name']['label'])->toBe('Name')
        ->and($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['searchable'])->toBeTrue()
        ->and($columns['name']['editable'])->toBeFalse()
        ->and($columns['name']['type'])->toBe('Column')
        ->and($columns['sku']['editable'])->toBeTrue()
        ->and($columns['sku']['type'])->toBe('TextInputColumn');
});

it('answers with an empty map for a table with no columns', function () {
    expect($this->introspector->columns(Table::make()->model(TinsProduct::class)))->toBe([]);
});

it('reads the real column listing from the schema, not the declared columns', function () {
    // `stock` is in the database and in no column; `sku` is in both. The point
    // of the pairing is spotting exactly that gap.
    expect($this->introspector->databaseColumns(tinsTable()))
        ->toContain('id', 'name', 'sku', 'stock');
});

it('pairs each database column with its type', function () {
    $types = $this->introspector->databaseColumnTypes(tinsTable());

    expect($types)->toHaveKeys(['id', 'name', 'sku', 'stock'])
        ->and($types['name']['name'])->toBe('name')
        ->and($types['name']['type'])->toBeString()
        ->and($types['stock']['type'])->toBeString();
});

it('puts the configuration and the query it produces side by side', function () {
    $debug = $this->introspector->configuration(tinsTable());

    expect($debug['model'])->toBe(TinsProduct::class)
        ->and($debug['sql'])->toContain('select * from')
        ->and($debug['raw_sql'])->toContain('tins_products')
        ->and($debug['bindings'])->toBe([])
        ->and($debug['columns'])->toHaveKeys(['name', 'sku'])
        ->and($debug['database_columns'])->toContain('stock')
        ->and($debug['filters'])->toBe(['name'])
        ->and($debug['searchable'])->toBeTrue()
        ->and($debug['sortable'])->toBeTrue()
        ->and($debug['paginated'])->toBeTrue()
        ->and($debug['per_page'])->toBe(25)
        ->and($debug['default_sort'])->toBe('name')
        ->and($debug['default_sort_direction'])->toBe('desc');
});

it('plans a simulated request without one having happened', function () {
    $plan = $this->introspector->queryPlan(
        tinsTable(),
        search: 'bolt',
        filterValues: ['name' => 'nut'],
        sortColumn: 'name',
        sortDirection: 'asc',
    );

    expect($plan)->toHaveKeys(['query_plan', 'final_sql', 'raw_sql', 'bindings'])
        ->and($plan['query_plan'])->toHaveKeys([
            'joins', 'eager_loads', 'aggregates', 'filters',
            'search_clauses', 'sort_clauses', 'scopes', 'with_soft_deletes',
        ])
        ->and($plan['query_plan']['sort_clauses'][0]['column'])->toBe('name')
        ->and($plan['query_plan']['sort_clauses'][0]['direction'])->toBe('asc')
        ->and($plan['query_plan']['search_clauses'][0]['column'])->toBe('name');
});

it('falls back to the table\'s default sort column but not its direction', function () {
    // Preserved as it was: only the column falls back to defaultSort(), while
    // the direction takes this method's own 'asc' default. So a table declaring
    // defaultSort('name', 'desc') is planned ascending unless the caller says
    // otherwise — a quirk of the debug helper, not of the table.
    $plan = $this->introspector->queryPlan(tinsTable());

    expect($plan['query_plan']['sort_clauses'][0]['column'])->toBe('name')
        ->and($plan['query_plan']['sort_clauses'][0]['direction'])->toBe('asc');
});

it('says so rather than fataling when no plan came back', function () {
    // buildQuery() assigns the plan unconditionally, so this branch is
    // unreachable through the real service — it guards the nullable return of
    // getLastPlan(), and a stub is the only way to walk it.
    app()->bind(TableQueryService::class, fn () => new class
    {
        public function buildQuery(mixed ...$args): Builder
        {
            return TinsProduct::query();
        }

        public function getLastPlan(): ?QueryPlan
        {
            return null;
        }
    });

    expect($this->introspector->queryPlan(tinsTable()))->toBe(['error' => 'No QueryPlan generated']);
});

it('interpolates the bindings into the final sql', function () {
    $plan = $this->introspector->queryPlan(tinsTable(), search: 'bolt');

    expect($plan['final_sql'])->toContain('bolt')
        ->and($plan['raw_sql'])->toContain('?')
        ->and($plan['bindings'])->not->toBe([]);
});

it('stays reachable through the table itself', function () {
    // The public surface is unchanged; these five are one-line delegations now.
    $table = tinsTable();

    expect($table->getColumnsInfo())->toBe($this->introspector->columns($table))
        ->and($table->getDatabaseColumns())->toBe($this->introspector->databaseColumns($table))
        ->and($table->getDatabaseColumnsInfo())->toBe($this->introspector->databaseColumnTypes($table))
        ->and($table->debug()['per_page'])->toBe(25)
        ->and($table->debugQueryPlan(sortColumn: 'name')['query_plan']['sort_clauses'][0]['column'])
        ->toBe('name');
});
