<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\BulkAction;

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

it('resolves outlined warning/success/info via the canonical color owner (regression)', function () {
    // The old bulk-button match map only handled primary/danger for outlined,
    // rendering every other hue gray. Delegating to getButtonClasses() fixes it.
    foreach (['success' => 'emerald', 'warning' => 'amber', 'info' => 'cyan'] as $color => $hue) {
        $classes = BulkAction::make('act')->color($color)->outlined()->getButtonClasses();

        expect($classes)->toContain($hue)
            ->and($classes)->toContain('border');
    }
});
