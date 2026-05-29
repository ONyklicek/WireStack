<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireCore\Actions\EditAction;
use NyonCode\WireCore\Actions\ForceDeleteBulkAction;
use NyonCode\WireCore\Actions\RestoreBulkAction;
use NyonCode\WireCore\Actions\ViewAction;

// ─── DeleteAction ──────────────────────────────────────────────────────────

it('DeleteAction has preconfigured settings', function () {
    $action = DeleteAction::make('delete');

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->getIcon())->toBe('trash')
        ->and($action->getColor())->toBe('danger')
        ->and($action->hasModal())->toBeTrue();
});

// ─── DeleteBulkAction ──────────────────────────────────────────────────────

it('DeleteBulkAction has preconfigured settings', function () {
    $action = DeleteBulkAction::make('delete');

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->hasModal())->toBeTrue();
});

it('DeleteBulkAction deselects records after completion by default', function () {
    $action = DeleteBulkAction::make('delete');

    expect($action->shouldDeselectRecordsAfterCompletion())->toBeTrue();
});

// ─── ForceDeleteBulkAction ─────────────────────────────────────────────────

it('ForceDeleteBulkAction has preconfigured settings', function () {
    $action = ForceDeleteBulkAction::make();

    expect($action->getName())->toBe('forceDelete')
        ->and($action->getLabel())->not->toBeEmpty()
        ->and($action->getIcon())->toBe('trash')
        ->and($action->getColor())->toBe('danger')
        ->and($action->hasModal())->toBeTrue()
        ->and($action->shouldDeselectRecordsAfterCompletion())->toBeTrue();
});

it('ForceDeleteBulkAction can use custom name', function () {
    $action = ForceDeleteBulkAction::make('permanentDelete');

    expect($action->getName())->toBe('permanentDelete');
});

// ─── RestoreBulkAction ────────────────────────────────────────────────────

it('RestoreBulkAction has preconfigured settings', function () {
    $action = RestoreBulkAction::make();

    expect($action->getName())->toBe('restore')
        ->and($action->getLabel())->not->toBeEmpty()
        ->and($action->getIcon())->toBe('arrow-uturn-left')
        ->and($action->getColor())->toBe('success')
        ->and($action->hasModal())->toBeTrue()
        ->and($action->shouldDeselectRecordsAfterCompletion())->toBeTrue();
});

it('RestoreBulkAction can use custom name', function () {
    $action = RestoreBulkAction::make('undelete');

    expect($action->getName())->toBe('undelete');
});

// ─── EditAction ────────────────────────────────────────────────────────────

it('EditAction has preconfigured settings', function () {
    $action = EditAction::make('edit');

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->getIcon())->toBe('pencil')
        ->and($action->getColor())->toBe('primary');
});

// ─── ViewAction ────────────────────────────────────────────────────────────

it('ViewAction has preconfigured settings', function () {
    $action = ViewAction::make('view');

    expect($action->getLabel())->not->toBeEmpty()
        ->and($action->getIcon())->toBe('eye')
        ->and($action->getColor())->toBe('gray');
});
