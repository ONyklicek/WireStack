<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;
use NyonCode\WireCore\Actions\EditAction;
use NyonCode\WireCore\Actions\ViewAction;

// ─── DeleteAction ──────────────────────────────────────────────────────────

it('DeleteAction has preconfigured settings', function () {
    $action = DeleteAction::make('delete');

    expect($action->getLabel())->toBe('delete_label')
        ->and($action->getIcon())->toBe('trash')
        ->and($action->getColor())->toBe('danger')
        ->and($action->hasModal())->toBeTrue();
});

// ─── DeleteBulkAction ──────────────────────────────────────────────────────

it('DeleteBulkAction has preconfigured settings', function () {
    $action = DeleteBulkAction::make('delete');

    expect($action->getLabel())->toBe('delete_bulk_label')
        ->and($action->hasModal())->toBeTrue();
});

it('DeleteBulkAction deselects records after completion by default', function () {
    $action = DeleteBulkAction::make('delete');

    expect($action->shouldDeselectRecordsAfterCompletion())->toBeTrue();
});

// ─── EditAction ────────────────────────────────────────────────────────────

it('EditAction has preconfigured settings', function () {
    $action = EditAction::make('edit');

    expect($action->getLabel())->toBe('edit_label')
        ->and($action->getIcon())->toBe('pencil')
        ->and($action->getColor())->toBe('primary');
});

// ─── ViewAction ────────────────────────────────────────────────────────────

it('ViewAction has preconfigured settings', function () {
    $action = ViewAction::make('view');

    expect($action->getLabel())->toBe('view_label')
        ->and($action->getIcon())->toBe('eye')
        ->and($action->getColor())->toBe('gray');
});
