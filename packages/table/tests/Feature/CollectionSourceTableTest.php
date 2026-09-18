<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Data\CollectionDataSource;
use NyonCode\WireTable\Data\CollectionRow;
use NyonCode\WireTable\Exceptions\CustomDataSourceException;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

/*
 * A table over rows that have no model: `->dataSource(new CollectionDataSource(…))`
 * with no `->model()` and no `->query()`, rendered by a real component.
 *
 * Documented from the start as a working table — "search, filters, sorting and
 * pagination all work" — and it never drew: WithTable planned an Eloquent query
 * for every fetch, and `Table::getQuery()` threw "No model or query defined for
 * table." The source's own tests only ever built the Table object, never a
 * component that renders it, which is how that went unnoticed.
 */

class CstPeople
{
    /** @var list<array<string, mixed>> */
    public static array $rows = [
        ['id' => 1, 'name' => 'Ada Lovelace', 'team' => 'math', 'score' => 90],
        ['id' => 2, 'name' => 'Grace Hopper', 'team' => 'navy', 'score' => 70],
        ['id' => 3, 'name' => 'Alan Turing', 'team' => 'math', 'score' => 80],
        ['id' => 4, 'name' => 'Edsger Dijkstra', 'team' => 'algo', 'score' => 60],
    ];

    /** @var list<string> */
    public static array $touched = [];

    /** @var array<int, mixed> */
    public static array $extraFilters = [];

    /** @var array<int, mixed> */
    public static array $extraColumns = [];

    public static ?string $mode = null;

    public static bool $paginated = false;
}

class CollectionSourceTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        $table = $table
            ->dataSource(new CollectionDataSource(CstPeople::$rows))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('team'),
                TextColumn::make('score')->sortable()->summarizeSum('Total'),
                ...CstPeople::$extraColumns,
            ])
            ->filters([
                SelectFilter::make('team')->options(['math' => 'Math', 'navy' => 'Navy', 'algo' => 'Algo']),
                TextFilter::make('name'),
                NumberRangeFilter::make('score'),
                ...CstPeople::$extraFilters,
            ])
            ->selectable()
            ->bulkActions([
                BulkAction::make('touch')->action(function (Collection $records): void {
                    CstPeople::$touched = $records->map(fn ($record) => $record->name)->sort()->values()->all();
                }),
            ])
            ->defaultSort('name');

        $table = $table->paginated(CstPeople::$paginated)->perPageOptions([2, 10])->perPage(2);

        return match (CstPeople::$mode) {
            'simple' => $table->simplePagination(),
            'cursor' => $table->cursorPagination(),
            default => $table,
        };
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    CstPeople::$touched = [];
    CstPeople::$extraFilters = [];
    CstPeople::$extraColumns = [];
    CstPeople::$paginated = false;
    CstPeople::$mode = null;
});

/** The names drawn, in the order drawn. */
function cstNames($component): array
{
    return $component->instance()->getTableRecords()
        ->map(fn ($record) => $record->name)
        ->values()
        ->all();
}

it('draws a table that has no model and no query', function () {
    $component = Livewire::test(CollectionSourceTable::class)->assertOk()->assertSee('Grace Hopper');

    expect(cstNames($component))->toBe(['Ada Lovelace', 'Alan Turing', 'Edsger Dijkstra', 'Grace Hopper'])
        // Each row reaches the columns as a model, keyed as the source keys it.
        ->and($component->instance()->getTableRecords()->first())->toBeInstanceOf(CollectionRow::class)
        ->and($component->instance()->getTableRecords()->first()->getKey())->toBe(1);
});

it('searches the searchable columns, whatever the case', function () {
    $component = Livewire::test(CollectionSourceTable::class)->set('tableState.search', 'HOPPER');

    expect(cstNames($component))->toBe(['Grace Hopper']);
});

it('filters through the filters a source can evaluate', function () {
    $select = Livewire::test(CollectionSourceTable::class)->set('tableState.filters.team', ['value' => 'math']);
    expect(cstNames($select))->toBe(['Ada Lovelace', 'Alan Turing']);

    // TextFilter plans `LIKE %…%` in capitals; the source used to know only `like`.
    $text = Livewire::test(CollectionSourceTable::class)->set('tableState.filters.name', ['value' => 'ing']);
    expect(cstNames($text))->toBe(['Alan Turing']);

    // NumberRangeFilter plans `BETWEEN` with an open end.
    $range = Livewire::test(CollectionSourceTable::class)->set('tableState.filters.score', ['min' => 75, 'max' => '']);
    expect(cstNames($range))->toBe(['Ada Lovelace', 'Alan Turing']);
});

it('sorts by the column clicked, both ways', function () {
    $component = Livewire::test(CollectionSourceTable::class)->call('sortTable', 'score');
    expect(cstNames($component))->toBe(['Edsger Dijkstra', 'Grace Hopper', 'Alan Turing', 'Ada Lovelace']);

    $component->call('sortTable', 'score');
    expect(cstNames($component))->toBe(['Ada Lovelace', 'Alan Turing', 'Grace Hopper', 'Edsger Dijkstra']);
});

it('pages through the source, and knows the total', function () {
    CstPeople::$paginated = true;

    $component = Livewire::test(CollectionSourceTable::class);
    $page = $component->instance()->getTableRecords();

    expect($page->total())->toBe(4)
        ->and(collect($page->items())->pluck('name')->all())->toBe(['Ada Lovelace', 'Alan Turing']);

    $component->call('gotoPage', 2);

    expect(collect($component->instance()->getTableRecords()->items())->pluck('name')->all())
        ->toBe(['Edsger Dijkstra', 'Grace Hopper']);
});

it('totals the footer over everything the filter matches, not only the page', function () {
    CstPeople::$paginated = true;

    $component = Livewire::test(CollectionSourceTable::class)
        ->set('tableState.filters.team', ['value' => 'math']);

    $summaries = $component->instance()->computeTableSummaries('query');

    expect(json_encode($summaries))->toContain('170');
});

it('selects everything the filter matches and hands it to a bulk action', function () {
    $component = Livewire::test(CollectionSourceTable::class)
        ->set('tableState.filters.team', ['value' => 'math'])
        ->call('selectAllMatchingRecords');

    expect($component->instance()->getSelectedRecordsCount())->toBe(2);

    $component->call('executeBulkAction', 'touch', true);

    expect(CstPeople::$touched)->toBe(['Ada Lovelace', 'Alan Turing']);
});

it('hands a bulk action exactly the rows that were ticked', function () {
    Livewire::test(CollectionSourceTable::class)
        ->call('toggleRecordSelection', '2')
        ->call('toggleRecordSelection', '4')
        ->call('executeBulkAction', 'touch', true);

    expect(CstPeople::$touched)->toBe(['Edsger Dijkstra', 'Grace Hopper']);
});

it('says which filter needs a query instead of dying on a missing model', function () {
    // DateFilter applies itself as an Eloquent query and has no clause to give
    // a source. Unused, it costs nothing; the moment it is set, it is named.
    CstPeople::$extraFilters = [DateFilter::make('born')];

    $component = Livewire::test(CollectionSourceTable::class)->assertOk();

    // Livewire wraps what the render threw; the message is what reaches a developer.
    expect(fn () => $component->set('tableState.filters.born', ['from' => '2020-01-01']))
        ->toThrow(Exception::class, 'The filter [born] applies itself as an Eloquent query');
});

it('says what needs a query when a feature built on one is asked for', function () {
    // The selection as a SQL query is the model path's; a source's selection
    // is read as rows (getSelectedRecords). Asked for anyway, it says why not
    // rather than "No model or query defined for table."
    $component = Livewire::test(CollectionSourceTable::class)->call('toggleRecordSelection', '1');

    expect(fn () => $component->instance()->selectedRecordsQuery())
        ->toThrow(CustomDataSourceException::class, 'Eloquent query');
});

it('will not save a row that has nowhere to go', function () {
    expect(fn () => CollectionRow::from(['id' => 1, 'name' => 'Ada'])->save())
        ->toThrow(CustomDataSourceException::class, 'cannot be saved');
});

it('will not delete one either, and keeps a model a source already returns', function () {
    expect(fn () => CollectionRow::from(['id' => 1])->delete())
        ->toThrow(CustomDataSourceException::class, 'cannot be saved or deleted');

    $model = CollectionRow::from(['id' => 7]);

    expect(CollectionRow::from($model))->toBe($model);
});

it('names the source when something asks the table itself for a query', function () {
    $table = Table::make()->dataSource(new CollectionDataSource(CstPeople::$rows));

    expect(fn () => $table->getQuery())
        ->toThrow(CustomDataSourceException::class, CollectionDataSource::class);
});

it('refuses a search or a sort written as an Eloquent callback, once it is used', function () {
    CstPeople::$extraColumns = [
        TextColumn::make('nick')->searchable(true, fn ($query, $term) => $query)->sortable(true, fn ($query, $direction) => $query),
    ];

    $component = Livewire::test(CollectionSourceTable::class)->assertOk();

    expect(fn () => $component->set('tableState.search', 'Ada'))
        ->toThrow(Exception::class, 'Eloquent query');

    $component = Livewire::test(CollectionSourceTable::class);

    expect(fn () => $component->call('sortTable', 'nick'))
        ->toThrow(Exception::class, 'Eloquent query');
});

it('walks a selection record by record without a query to chunk', function () {
    $component = Livewire::test(CollectionSourceTable::class)
        ->call('toggleRecordSelection', '1')
        ->call('toggleRecordSelection', '3');

    $seen = [];
    $component->instance()->eachSelectedRecord(function ($record) use (&$seen): void {
        $seen[] = $record->name;
    });

    expect($seen)->toBe(['Ada Lovelace', 'Alan Turing']);
});

it('polls by comparing rows, having no COUNT and MAX to run', function () {
    $component = Livewire::test(CollectionSourceTable::class);

    $checksum = (fn () => $this->computePollChecksum(true))->call($component->instance());

    expect($checksum)->toBeNull();
});

it('pages simply without claiming a total, and refuses a cursor it cannot fake', function () {
    CstPeople::$paginated = true;
    CstPeople::$mode = 'simple';

    $page = Livewire::test(CollectionSourceTable::class)->instance()->getTableRecords();

    expect(collect($page->items())->pluck('name')->all())->toBe(['Ada Lovelace', 'Alan Turing'])
        ->and($page->hasMorePages())->toBeTrue();

    CstPeople::$mode = 'cursor';

    expect(fn () => Livewire::test(CollectionSourceTable::class))
        ->toThrow(Exception::class, 'cursor paging');
});
