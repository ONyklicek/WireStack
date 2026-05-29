<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

// ─── Factory ────────────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    $table = Table::make();

    expect($table)->toBeInstanceOf(Table::class);
});

// ─── Defaults ───────────────────────────────────────────────────────────────

it('has correct default values', function () {
    $table = Table::make();

    expect($table->getPerPage())->toBe(10)
        ->and($table->getPerPageOptions())->toBe([10, 25, 50, 100])
        ->and($table->isSearchable())->toBeTrue()
        ->and($table->isSortable())->toBeTrue()
        ->and($table->isPaginated())->toBeTrue()
        ->and($table->isSelectable())->toBeFalse()
        ->and($table->isStriped())->toBeFalse()
        ->and($table->isHoverable())->toBeTrue()
        ->and($table->isCompact())->toBeFalse()
        ->and($table->isBordered())->toBeFalse()
        ->and($table->isLazy())->toBeFalse()
        ->and($table->isPolling())->toBeFalse()
        ->and($table->isStackedOnMobile())->toBeFalse()
        ->and($table->getDefaultSort())->toBeNull()
        ->and($table->getDefaultSortDirection())->toBe('asc')
        ->and($table->getPrimaryKey())->toBe('id')
        ->and($table->getActionsPosition())->toBe('end')
        ->and($table->getActionsAlignment())->toBe('right');
});

// ─── Fluent API (chaining) ──────────────────────────────────────────────────

it('supports fluent chaining for all setters', function () {
    $table = Table::make()
        ->perPage(25)
        ->perPageOptions([25, 50])
        ->searchable(false)
        ->sortable(false)
        ->paginated(false)
        ->selectable()
        ->striped()
        ->hoverable(false)
        ->compact()
        ->bordered()
        ->lazy()
        ->lazyPlaceholder('Načítání...')
        ->stackedOnMobile(true, 'lg')
        ->defaultSort('name', 'desc')
        ->primaryKey('uuid')
        ->actionsPosition('start')
        ->actionsAlignment('center')
        ->actionsColumnLabel('Akce')
        ->actionsColumnWidth('200px')
        ->tableClass('custom-table')
        ->headerClass('custom-header')
        ->rowClass('custom-row');

    expect($table->getPerPage())->toBe(25)
        ->and($table->getPerPageOptions())->toBe([25, 50])
        ->and($table->isSearchable())->toBeFalse()
        ->and($table->isSortable())->toBeFalse()
        ->and($table->isPaginated())->toBeFalse()
        ->and($table->isSelectable())->toBeTrue()
        ->and($table->isStriped())->toBeTrue()
        ->and($table->isHoverable())->toBeFalse()
        ->and($table->isCompact())->toBeTrue()
        ->and($table->isBordered())->toBeTrue()
        ->and($table->isLazy())->toBeTrue()
        ->and($table->getLazyPlaceholder())->toBe('Načítání...')
        ->and($table->isStackedOnMobile())->toBeTrue()
        ->and($table->getStackedBreakpoint())->toBe('lg')
        ->and($table->getDefaultSort())->toBe('name')
        ->and($table->getDefaultSortDirection())->toBe('desc')
        ->and($table->getPrimaryKey())->toBe('uuid')
        ->and($table->getActionsPosition())->toBe('start')
        ->and($table->getActionsAlignment())->toBe('center')
        ->and($table->getActionsColumnLabel())->toBe('Akce')
        ->and($table->getActionsColumnWidth())->toBe('200px')
        ->and($table->getTableClass())->toBe('custom-table')
        ->and($table->getHeaderClass())->toBe('custom-header')
        ->and($table->getRowClass())->toBe('custom-row');
});

// ─── Columns ────────────────────────────────────────────────────────────────

it('can set and get columns', function () {
    $columns = [
        Column::make('id'),
        TextColumn::make('name'),
    ];

    $table = Table::make()->columns($columns);

    expect($table->getColumns())->toHaveCount(2)
        ->and($table->getColumnNames())->toBe(['id', 'name']);
});

it('returns searchable columns', function () {
    $table = Table::make()->columns([
        Column::make('id'),
        Column::make('name')->searchable(),
        Column::make('email')->searchable(),
        Column::make('status'),
    ]);

    expect($table->getSearchableColumns())->toHaveCount(2);
});

it('returns sortable columns', function () {
    $table = Table::make()->columns([
        Column::make('id')->sortable(),
        Column::make('name')->sortable(),
        Column::make('email'),
    ]);

    expect($table->getSortableColumns())->toHaveCount(2);
});

// ─── Actions ────────────────────────────────────────────────────────────────

it('can set and get actions', function () {
    $actions = [Action::make('edit'), Action::make('delete')];
    $table = Table::make()->actions($actions);

    expect($table->getActions())->toHaveCount(2)
        ->and($table->hasActions())->toBeTrue();
});

it('reports no actions when empty', function () {
    expect(Table::make()->hasActions())->toBeFalse();
});

it('expands action groups in getAllActions()', function () {
    $table = Table::make()->actions([
        Action::make('view'),
        ActionGroup::make([
            Action::make('edit'),
            Action::make('delete'),
        ]),
    ]);

    expect($table->getAllActions())->toHaveCount(3);
});

it('can set bulk actions', function () {
    $table = Table::make()->bulkActions([
        BulkAction::make('delete'),
    ]);

    expect($table->getBulkActions())->toHaveCount(1);
});

it('is selectable when bulk actions exist', function () {
    $table = Table::make()->bulkActions([
        BulkAction::make('delete'),
    ]);

    expect($table->isSelectable())->toBeTrue();
});

it('can set header actions', function () {
    $table = Table::make()->headerActions([
        HeaderAction::make('create'),
    ]);

    expect($table->getHeaderActions())->toHaveCount(1);
});

// ─── Filters ────────────────────────────────────────────────────────────────

it('can set and get filters', function () {
    $filters = [
        SelectFilter::make('status')->options(['active' => 'Active']),
    ];

    $table = Table::make()->filters($filters);

    expect($table->getFilters())->toHaveCount(1);
});

// ─── Plugin Type Resolution ────────────────────────────────────────────────

it('resolves plugin registered column filter and action types', function () {
    $manager = app(PluginManager::class);
    $manager->addColumnType('text', Column::class);
    $manager->addFilterType('select', SelectFilter::class);
    $manager->addActionType('default', Action::class);

    expect(Table::resolveColumnType('text'))->toBe(Column::class)
        ->and(Table::resolveFilterType('select'))->toBe(SelectFilter::class)
        ->and(Table::resolveActionType('default'))->toBe(Action::class)
        ->and(Table::resolveColumnType('missing'))->toBeNull()
        ->and(Table::resolveFilterType('missing'))->toBeNull()
        ->and(Table::resolveActionType('missing'))->toBeNull();
});

// ─── Empty State ────────────────────────────────────────────────────────────

it('has default empty state texts from translation', function () {
    $table = Table::make();

    expect($table->getEmptyStateHeading())->toBe('No records')
        ->and($table->getEmptyStateDescription())->toBe('No records found matching your search.');
});

it('can set custom empty state', function () {
    $table = Table::make()->emptyState('No data', 'Try a different query', 'search');

    expect($table->getEmptyStateHeading())->toBe('No data')
        ->and($table->getEmptyStateDescription())->toBe('Try a different query')
        ->and($table->getEmptyStateIcon())->toBe('search');
});

// ─── Polling ────────────────────────────────────────────────────────────────

it('can enable polling with interval', function () {
    $table = Table::make()->poll('10s');

    expect($table->isPolling())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('10s');
});

it('polling alias works', function () {
    $table = Table::make()->polling('30s');

    expect($table->isPolling())->toBeTrue()
        ->and($table->getPollingInterval())->toBe('30s');
});

it('can configure polling options', function () {
    $table = Table::make()
        ->poll('5s')
        ->pollKeepAlive()
        ->pollMethod('reload')
        ->pollOnlyVisible(false);

    expect($table->isPollingKeepAlive())->toBeTrue()
        ->and($table->getPollingMethod())->toBe('reload')
        ->and($table->isPollingOnlyVisible())->toBeFalse();
});

it('generates correct polling directive', function () {
    $table = Table::make()->poll('10s')->pollKeepAlive();

    expect($table->getPollingDirective())->toBe('wire:poll.10s.keep-alive.visible');
});

it('returns null polling directive when polling is disabled', function () {
    expect(Table::make()->getPollingDirective())->toBeNull();
});

it('returns full polling config', function () {
    $table = Table::make()->poll('5s');
    $config = $table->getPollingConfig();

    expect($config)->toBeArray()
        ->and($config['enabled'])->toBeTrue()
        ->and($config['interval'])->toBe('5s')
        ->and($config['method'])->toBe('refresh');
});

// ─── Query ──────────────────────────────────────────────────────────────────

it('throws exception when no model or query defined', function () {
    Table::make()->getQuery();
})->throws(RuntimeException::class, 'No model or query defined for table.');

it('can set modifyQueryUsing callback', function () {
    $callback = fn ($q) => $q;
    $table = Table::make()->modifyQueryUsing($callback);

    expect($table->getModifyQueryCallback())->toBe($callback);
});

// ─── Record URL ─────────────────────────────────────────────────────────────

it('can set record url as string', function () {
    $table = Table::make()->recordUrl('/users/{id}');

    $model = Mockery::mock(Model::class);
    $model->allows('getKey')->andReturns(42);

    expect($table->getRecordUrl($model))->toBe('/users/42');
});

it('can set record url as closure', function () {
    $table = Table::make()->recordUrl(fn ($record) => '/users/'.$record->getKey());

    $model = Mockery::mock(Model::class);
    $model->allows('getKey')->andReturns(5);

    expect($table->getRecordUrl($model))->toBe('/users/5');
});

it('returns null record url when not set', function () {
    $model = Mockery::mock(Model::class);

    expect(Table::make()->getRecordUrl($model))->toBeNull();
});

// ─── Notification Driver ────────────────────────────────────────────────────

it('can set notification driver', function () {
    $driver = Mockery::mock(NotificationDriver::class);
    $table = Table::make()->notificationDriver($driver);

    expect($table->getNotificationDriver())->toBe($driver);
});

it('returns null notification driver by default', function () {
    expect(Table::make()->getNotificationDriver())->toBeNull();
});

// ─── Livewire Component ─────────────────────────────────────────────────────

it('can set and get livewire component', function () {
    $component = new stdClass;
    $table = Table::make()->livewireComponent($component);

    expect($table->getLivewireComponent())->toBe($component);
});
