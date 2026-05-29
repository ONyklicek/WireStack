<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireCore\Actions\ForceDeleteBulkAction;
use NyonCode\WireCore\Actions\RestoreBulkAction;
use NyonCode\WireTable\Table;

// ─── BulkAction basics ──────────────────────────────────────────────────

it('can be created', function () {
    $action = BulkAction::make('delete');

    expect($action)->toBeInstanceOf(BulkAction::class)
        ->and($action->getName())->toBe('delete');
});

it('deselects records after completion by default', function () {
    expect(BulkAction::make('delete')->shouldDeselectRecordsAfterCompletion())->toBeTrue();
});

it('can disable deselect after completion', function () {
    $action = BulkAction::make('export')->deselectRecordsAfterCompletion(false);

    expect($action->shouldDeselectRecordsAfterCompletion())->toBeFalse();
});

it('inherits base action features', function () {
    $action = BulkAction::make('archive')
        ->label('Archivovat')
        ->color('warning')
        ->icon('archive')
        ->outlined();

    expect($action->getLabel())->toBe('Archivovat')
        ->and($action->getColor())->toBe('warning')
        ->and($action->getIcon())->toBe('archive')
        ->and($action->isOutlined())->toBeTrue();
});

it('can set an action callback', function () {
    $called = false;
    $action = BulkAction::make('approve')
        ->action(function () use (&$called) {
            $called = true;
        });

    expect($action->getActionCallback())->toBeInstanceOf(Closure::class);
});

it('supports confirmation modal', function () {
    $action = BulkAction::make('delete')
        ->requiresConfirmation()
        ->modalHeading('Delete records')
        ->modalDescription('Are you sure?');

    expect($action->hasModal())->toBeTrue()
        ->and($action->getModalHeading())->toBe('Delete records')
        ->and($action->getModalDescription())->toBe('Are you sure?');
});

it('supports visibility control', function () {
    $action = BulkAction::make('admin-only')->hidden();

    expect($action->isHidden())->toBeTrue()
        ->and($action->canExecute())->toBeFalse();
});

it('supports authorization', function () {
    $action = BulkAction::make('delete')->authorize('bulk-delete');

    expect($action->canExecute())->toBeFalse();
});

// ─── DeleteBulkAction preset ────────────────────────────────────────────

it('creates DeleteBulkAction with correct defaults', function () {
    $action = DeleteBulkAction::make();

    expect($action)->toBeInstanceOf(DeleteBulkAction::class)
        ->and($action->getName())->toBe('delete')
        ->and($action->getColor())->toBe('danger')
        ->and($action->getIcon())->toBe('trash')
        ->and($action->hasModal())->toBeTrue();
});

it('DeleteBulkAction has translated labels', function () {
    $action = DeleteBulkAction::make();

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->getModalHeading())->not->toBeEmpty()
        ->and($action->getModalDescription())->not->toBeEmpty();
});

// ─── ForceDeleteBulkAction preset ───────────────────────────────────────

it('creates ForceDeleteBulkAction with correct defaults', function () {
    $action = ForceDeleteBulkAction::make();

    expect($action)->toBeInstanceOf(ForceDeleteBulkAction::class)
        ->and($action->getName())->toBe('forceDelete')
        ->and($action->getColor())->toBe('danger')
        ->and($action->getIcon())->toBe('trash')
        ->and($action->hasModal())->toBeTrue();
});

it('ForceDeleteBulkAction has translated labels', function () {
    $action = ForceDeleteBulkAction::make();

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->getModalHeading())->not->toBeEmpty()
        ->and($action->getModalDescription())->not->toBeEmpty();
});

// ─── RestoreBulkAction preset ───────────────────────────────────────────

it('creates RestoreBulkAction with correct defaults', function () {
    $action = RestoreBulkAction::make();

    expect($action)->toBeInstanceOf(RestoreBulkAction::class)
        ->and($action->getName())->toBe('restore')
        ->and($action->getColor())->toBe('success')
        ->and($action->getIcon())->toBe('arrow-uturn-left')
        ->and($action->hasModal())->toBeTrue();
});

it('RestoreBulkAction has translated labels', function () {
    $action = RestoreBulkAction::make();

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->getModalHeading())->not->toBeEmpty()
        ->and($action->getModalDescription())->not->toBeEmpty();
});

// ─── Table integration ──────────────────────────────────────────────────

it('table is selectable when bulk actions are set', function () {
    $table = Table::make()->bulkActions([
        DeleteBulkAction::make(),
    ]);

    expect($table->isSelectable())->toBeTrue();
});

it('table is not selectable by default', function () {
    $table = Table::make();

    expect($table->isSelectable())->toBeFalse();
});

it('table can be explicitly selectable without bulk actions', function () {
    $table = Table::make()->selectable();

    expect($table->isSelectable())->toBeTrue()
        ->and($table->getBulkActions())->toBeEmpty();
});

it('table returns registered bulk actions', function () {
    $table = Table::make()->bulkActions([
        DeleteBulkAction::make(),
        BulkAction::make('export')->label('Export'),
    ]);

    expect($table->getBulkActions())->toHaveCount(2);
});
