<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\ActionGroup;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
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

it('resolves responsive stacked layout classes from the breakpoint', function () {
    $lg = Table::make()->stackedOnMobile(true, 'lg');

    expect($lg->getStackedTableHiddenClass())->toBe('hidden lg:block')
        ->and($lg->getStackedCardsVisibleClass())->toBe('lg:hidden');

    $default = Table::make()->stackedOnMobile(true, 'bogus');

    expect($default->getStackedTableHiddenClass())->toBe('hidden md:block')
        ->and($default->getStackedCardsVisibleClass())->toBe('md:hidden');

    // A Breakpoint enum resolves the same as its string token.
    $enum = Table::make()->stackedOnMobile(true, Breakpoint::Lg);

    expect($enum->getStackedBreakpoint())->toBe('lg')
        ->and($enum->getStackedTableHiddenClass())->toBe('hidden lg:block');
});

it('resolves canonical actions alignment/justify classes, accepting a string or Alignment enum', function () {
    expect(Table::make()->getActionsAlignmentClass())->toBe('text-right')
        ->and(Table::make()->actionsAlignment('left')->getActionsAlignmentClass())->toBe('text-left')
        ->and(Table::make()->actionsAlignment('center')->getActionsJustifyClass())->toBe('justify-center')
        ->and(Table::make()->actionsAlignment(Alignment::Right)->getActionsJustifyClass())->toBe('justify-end')
        ->and(Table::make()->actionsAlignment(Alignment::Center)->getActionsAlignment())->toBe('center');
});

it('hides the cards layout when mobile stacking is disabled', function () {
    $table = Table::make()->stackedOnMobile(false);

    expect($table->getStackedTableHiddenClass())->toBe('')
        ->and($table->getStackedCardsVisibleClass())->toBe('hidden');
});

it('does not collapse mobile actions by default', function () {
    $threeActions = [Action::make('a'), Action::make('b'), Action::make('c')];

    expect(Table::make()->actions($threeActions)->shouldCollapseActionsOnMobile())->toBeFalse()
        ->and(Table::make()->getCollapseActionsOnMobileThreshold())->toBe(3);
});

it('collapses mobile actions only once the row reaches the threshold (default 3)', function () {
    $twoActions = [Action::make('a'), Action::make('b')];
    $threeActions = [Action::make('a'), Action::make('b'), Action::make('c')];

    // Enabled but below the default threshold → stays inline.
    expect(Table::make()->actions($twoActions)->collapseActionsOnMobile()->shouldCollapseActionsOnMobile())->toBeFalse()
        // Reaches the default threshold of 3 → collapses.
        ->and(Table::make()->actions($threeActions)->collapseActionsOnMobile()->shouldCollapseActionsOnMobile())->toBeTrue();
});

it('honours a custom collapse threshold and clamps it to at least 1', function () {
    $twoActions = [Action::make('a'), Action::make('b')];

    // Lower threshold collapses sooner.
    expect(Table::make()->actions($twoActions)->collapseActionsOnMobile(threshold: 2)->shouldCollapseActionsOnMobile())->toBeTrue()
        ->and(Table::make()->actions($twoActions)->collapseActionsOnMobile(threshold: 2)->getCollapseActionsOnMobileThreshold())->toBe(2)
        // A zero/negative threshold is clamped to 1 (always collapse when any action exists).
        ->and(Table::make()->actions([Action::make('a')])->collapseActionsOnMobile(threshold: 0)->shouldCollapseActionsOnMobile())->toBeTrue()
        ->and(Table::make()->collapseActionsOnMobile(threshold: 0)->getCollapseActionsOnMobileThreshold())->toBe(1);
});

it('counts flattened non-divider actions against the collapse threshold', function () {
    // A group of 2 + a divider + 1 action = 3 executable actions, dividers ignored.
    $table = Table::make()
        ->actions([
            ActionGroup::make([Action::make('edit'), Action::make('delete')]),
            Action::divider(),
            Action::make('view'),
        ])
        ->collapseActionsOnMobile();

    expect($table->shouldCollapseActionsOnMobile())->toBeTrue();

    // Two real actions plus a divider stays below the threshold of 3.
    $below = Table::make()
        ->actions([Action::make('edit'), Action::divider(), Action::make('view')])
        ->collapseActionsOnMobile();

    expect($below->shouldCollapseActionsOnMobile())->toBeFalse();
});

it('is disabled outright when collapse is turned off, regardless of action count', function () {
    $manyActions = [Action::make('a'), Action::make('b'), Action::make('c'), Action::make('d')];

    expect(Table::make()->actions($manyActions)->collapseActionsOnMobile(false)->shouldCollapseActionsOnMobile())->toBeFalse();
});

it('flattens row actions and nested groups into one mobile action group', function () {
    $table = Table::make()->actions([
        Action::make('view'),
        ActionGroup::make([
            Action::make('edit'),
            Action::make('delete'),
        ]),
    ]);

    $group = $table->getMobileActionGroup();

    expect($group)->toBeInstanceOf(ActionGroup::class)
        // view + edit + delete, all under a single trigger.
        ->and($group->getActions())->toHaveCount(3)
        ->and($group->getActions())->each->toBeInstanceOf(Action::class);
});

it('the mobile action group inherits the table mobile-sheet settings', function () {
    $table = Table::make()
        ->actions([Action::make('edit'), Action::make('delete')])
        ->sheetOnMobile(false)
        ->mobileBreakpoint('lg');

    $group = $table->getMobileActionGroup();

    expect($group->usesSheetOnMobile())->toBeFalse()
        ->and($group->getMobileBreakpoint())->toBe('lg');
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

it('defaults to the solid row-action style', function () {
    $table = Table::make()->actions([Action::make('edit'), Action::make('delete')]);

    expect($table->getActionsStyle())->toBe('solid');

    foreach ($table->getRowActionsForDisplay() as $action) {
        expect($action->isQuiet())->toBeFalse();
    }
});

it('flags row actions quiet when actionsStyle(quiet) is set', function () {
    $table = Table::make()
        ->actions([Action::make('edit'), Action::divider(), Action::make('delete')])
        ->actionsStyle('quiet');

    expect($table->getActionsStyle())->toBe('quiet');

    $display = $table->getRowActionsForDisplay();

    // Real actions are flagged quiet; the divider is left untouched.
    expect($display[0]->isQuiet())->toBeTrue()
        ->and($display[1]->isDivider())->toBeTrue()
        ->and($display[1]->isQuiet())->toBeFalse()
        ->and($display[2]->isQuiet())->toBeTrue();
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

// ─── Row color & class (conditional per-record) ─────────────────────────────

function rowRecord(array $attributes): Model
{
    return (new class extends Model
    {
        protected $guarded = [];
    })->forceFill($attributes);
}

it('keeps a static row class backwards compatible (no-arg getter)', function () {
    $table = Table::make()->rowClass('custom-row');

    expect($table->getRowClass())->toBe('custom-row')
        ->and($table->getRowClass(rowRecord([])))->toBe('custom-row');
});

it('resolves a per-record row class closure', function () {
    $table = Table::make()->rowClass(
        fn (Model $record) => $record->flagged ? 'font-semibold' : null,
    );

    expect($table->getRowClass(rowRecord(['flagged' => true])))->toBe('font-semibold')
        ->and($table->getRowClass(rowRecord(['flagged' => false])))->toBeNull()
        // A closure with no record cannot run, so the no-arg getter stays null.
        ->and($table->getRowClass())->toBeNull();
});

it('resolves a static row color to canonical tint classes', function () {
    $table = Table::make()->rowColor('danger');

    expect($table->getRowColor(rowRecord([])))->toBe('danger')
        ->and($table->getRowClasses(rowRecord([]), 0))
        ->toContain('bg-red-50')
        ->toContain('dark:bg-red-900/20')
        ->toContain('hover:bg-red-100');
});

it('resolves a per-record row color closure (null = no tint)', function () {
    $table = Table::make()->rowColor(
        fn (Model $record) => $record->overdue ? 'warning' : null,
    );

    expect($table->getRowColor(rowRecord(['overdue' => true])))->toBe('warning')
        ->and($table->getRowColor(rowRecord(['overdue' => false])))->toBeNull()
        ->and($table->getRowClasses(rowRecord(['overdue' => true]), 0))->toContain('bg-amber-50');
});

it('treats an empty-string row color as no tint', function () {
    $table = Table::make()->rowColor(fn () => '');

    expect($table->getRowColor(rowRecord([])))->toBeNull();
});

it('a tinted row suppresses the neutral hover and zebra striping', function () {
    $table = Table::make()->striped()->hoverable()->rowColor('success');

    $classes = $table->getRowClasses(rowRecord([]), 1); // odd row would normally stripe

    expect($classes)->toContain('bg-emerald-50')
        ->not->toContain('hover:bg-gray-50')
        ->not->toContain('bg-gray-50/50');
});

it('an untinted row keeps hover and striping, plus the custom class', function () {
    $table = Table::make()->striped()->hoverable()->rowClass('extra');

    $odd = $table->getRowClasses(rowRecord([]), 1);
    $even = $table->getRowClasses(rowRecord([]), 0);

    expect($odd)->toContain('hover:bg-gray-50')
        ->toContain('bg-gray-50/50')
        ->toContain('extra')
        ->and($even)->toContain('hover:bg-gray-50')
        ->not->toContain('bg-gray-50/50');
});

it('combines a row color tint with an additional custom row class', function () {
    $table = Table::make()
        ->rowColor('info')
        ->rowClass(fn (Model $record) => $record->pinned ? 'ring-2' : null);

    $classes = $table->getRowClasses(rowRecord(['pinned' => true]), 0);

    expect($classes)->toContain('bg-cyan-50')->toContain('ring-2');
});

it('falls back to a gray tint for an unknown row color', function () {
    $table = Table::make()->rowColor('not-a-color');

    expect($table->getRowClasses(rowRecord([]), 0))->toContain('bg-gray-50');
});

it('tints the mobile card the same as the desktop row', function () {
    $table = Table::make()->rowColor('danger')->rowClass('font-semibold');

    $card = $table->getRowCardClasses(rowRecord([]));

    expect($card)->toContain('bg-red-50')
        ->toContain('border-b') // keeps the card divider
        ->toContain('font-semibold')
        ->not->toContain('bg-white'); // tint replaces the default card background
});

it('keeps the default white card background when no row color is set', function () {
    expect(Table::make()->getRowCardClasses(rowRecord([])))
        ->toContain('bg-white')
        ->toContain('border-b');
});

// ─── Per-page options ───────────────────────────────────────────────────────

it('offers the configured page size even when it is not in the options list', function () {
    $table = Table::make()->perPage(3);

    expect($table->getPerPageOptions())->toBe([3, 10, 25, 50, 100]);
});

it('does not duplicate a configured page size that is already offered', function () {
    $table = Table::make()->perPage(25)->perPageOptions([10, 25]);

    expect($table->getPerPageOptions())->toBe([10, 25]);
});
