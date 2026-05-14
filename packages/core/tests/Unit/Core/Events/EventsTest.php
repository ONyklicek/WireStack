<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Events\ActionExecuted;
use NyonCode\WireCore\Core\Events\ActionExecuting;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireCore\Core\Events\CellUpdating;
use NyonCode\WireCore\Core\Events\TableFiltered;
use NyonCode\WireCore\Core\Events\TableFiltering;
use NyonCode\WireCore\Core\Events\TableRefreshed;
use NyonCode\WireCore\Core\Events\TableSearched;
use NyonCode\WireCore\Core\Events\TableSearching;

it('creates TableSearching event with properties', function () {
    $event = new TableSearching(
        tableId: 'users-table',
        term: 'john',
    );

    expect($event->tableId)->toBe('users-table')
        ->and($event->term)->toBe('john');
});

it('creates TableSearched event with properties', function () {
    $event = new TableSearched(
        tableId: 'users-table',
        term: 'john',
        resultsCount: 5,
    );

    expect($event->tableId)->toBe('users-table')
        ->and($event->term)->toBe('john')
        ->and($event->resultsCount)->toBe(5);
});

it('creates TableFiltering event with properties', function () {
    $event = new TableFiltering(
        tableId: 'orders-table',
        filters: ['status' => 'active'],
    );

    expect($event->tableId)->toBe('orders-table')
        ->and($event->filters)->toBe(['status' => 'active']);
});

it('creates TableFiltered event with properties', function () {
    $event = new TableFiltered(
        tableId: 'orders-table',
        filters: ['status' => 'active'],
        resultsCount: 12,
    );

    expect($event->tableId)->toBe('orders-table')
        ->and($event->filters)->toBe(['status' => 'active'])
        ->and($event->resultsCount)->toBe(12);
});

it('creates ActionExecuting event with properties', function () {
    $event = new ActionExecuting(
        tableId: 'users-table',
        actionName: 'delete',
        recordIds: [1, 2, 3],
    );

    expect($event->tableId)->toBe('users-table')
        ->and($event->actionName)->toBe('delete')
        ->and($event->recordIds)->toBe([1, 2, 3]);
});

it('creates ActionExecuted event with properties', function () {
    $event = new ActionExecuted(
        tableId: 'users-table',
        actionName: 'delete',
        recordIds: [1, 2, 3],
        success: true,
    );

    expect($event->tableId)->toBe('users-table')
        ->and($event->actionName)->toBe('delete')
        ->and($event->recordIds)->toBe([1, 2, 3])
        ->and($event->success)->toBeTrue();
});

it('creates CellUpdating event with properties', function () {
    $event = new CellUpdating(
        tableId: 'users-table',
        column: 'name',
        recordId: 42,
        value: 'New Name',
    );

    expect($event->tableId)->toBe('users-table')
        ->and($event->column)->toBe('name')
        ->and($event->recordId)->toBe(42)
        ->and($event->value)->toBe('New Name');
});

it('creates CellUpdated event with properties', function () {
    $event = new CellUpdated(
        tableId: 'users-table',
        column: 'name',
        recordId: 42,
        oldValue: 'Old Name',
        newValue: 'New Name',
    );

    expect($event->tableId)->toBe('users-table')
        ->and($event->column)->toBe('name')
        ->and($event->recordId)->toBe(42)
        ->and($event->oldValue)->toBe('Old Name')
        ->and($event->newValue)->toBe('New Name');
});

it('creates TableRefreshed event with properties', function () {
    $event = new TableRefreshed(
        tableId: 'users-table',
    );

    expect($event->tableId)->toBe('users-table');
});
