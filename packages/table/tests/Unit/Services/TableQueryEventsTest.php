<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use NyonCode\WireCore\Core\Events\TableFiltered;
use NyonCode\WireCore\Core\Events\TableFiltering;
use NyonCode\WireCore\Core\Events\TableSearched;
use NyonCode\WireCore\Core\Events\TableSearching;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Services\TableQueryEvents;
use NyonCode\WireTable\Table;

/*
 * The four events a query run announces.
 *
 * WithTable's suites can see that a listener fired, but not the two things that
 * decide whether the contract holds: that a pair never comes apart, and that a
 * filter the user cleared is not announced as applied. Both used to depend on
 * twenty lines of distance staying correct.
 */

class TqeUser extends Model
{
    protected $table = 'tqe_users';

    protected $guarded = [];
}

function tqeTable(): Table
{
    return Table::make()->model(TqeUser::class)->columns([
        TextColumn::make('name')->searchable(),
        TextColumn::make('email')->searchable(),
        TextColumn::make('role'),
    ]);
}

function tqeEvents(): TableQueryEvents
{
    return app(TableQueryEvents::class);
}

beforeEach(fn () => Event::fake([TableSearching::class, TableSearched::class, TableFiltering::class, TableFiltered::class]));

it('announces a search before and after the build', function () {
    tqeEvents()->around('hostId', tqeTable(), 'ada', [], fn (): string => 'built');

    Event::assertDispatched(TableSearching::class);
    Event::assertDispatched(TableSearched::class);
});

it('tells the listener which columns the search covered', function () {
    tqeEvents()->around('hostId', tqeTable(), 'ada', [], fn (): string => 'built');

    Event::assertDispatched(TableSearching::class, function (TableSearching $e): bool {
        // The unsearchable column must not be listed: a listener logging "we
        // searched role" would be logging something that never happened.
        return $e->searchableColumns === ['name', 'email'];
    });
});

it('says nothing at all when there is no search and no filter', function () {
    tqeEvents()->around('hostId', tqeTable(), null, [], fn (): string => 'built');
    tqeEvents()->around('hostId', tqeTable(), '', [], fn (): string => 'built');

    Event::assertNotDispatched(TableSearching::class);
    Event::assertNotDispatched(TableFiltering::class);
});

it('ignores a filter the user cleared', function () {
    // Present in state, holding nothing. Announcing it would tell a listener a
    // filter was applied that the user had just removed.
    tqeEvents()->around('hostId', tqeTable(), null, [
        'role' => null,
        'status' => '',
        'tags' => [],
    ], fn (): string => 'built');

    Event::assertNotDispatched(TableFiltering::class);
});

it('announces only the filters that hold a value', function () {
    tqeEvents()->around('hostId', tqeTable(), null, [
        'role' => 'admin',
        'status' => '',
    ], fn (): string => 'built');

    Event::assertDispatched(TableFiltering::class, fn (TableFiltering $e): bool => $e->filters === ['role' => 'admin']);
    Event::assertDispatched(TableFiltered::class, fn (TableFiltered $e): bool => $e->filters === ['role' => 'admin']);
});

it('reports the count as not yet known', function () {
    // -1 is the contract, not an oversight: counting here would mean running a
    // query that is deliberately built lazily.
    tqeEvents()->around('hostId', tqeTable(), 'ada', ['role' => 'admin'], fn (): string => 'built');

    Event::assertDispatched(TableSearched::class, fn (TableSearched $e): bool => $e->resultsCount === -1);
    Event::assertDispatched(TableFiltered::class, fn (TableFiltered $e): bool => $e->resultsCount === -1);
});

it('hands back whatever the build returned', function () {
    expect(tqeEvents()->around('hostId', tqeTable(), 'ada', [], fn (): string => 'the query'))->toBe('the query');
});

it('does not announce a finish for a build that threw', function () {
    // The reason this wraps rather than exposing a before and an after: a
    // search that never completed must not be announced as completed.
    expect(fn () => tqeEvents()->around('hostId', tqeTable(), 'ada', [], function (): string {
        throw new RuntimeException('build failed');
    }))->toThrow(RuntimeException::class);

    Event::assertDispatched(TableSearching::class);
    Event::assertNotDispatched(TableSearched::class);
});
