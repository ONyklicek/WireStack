<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;

it('extends Action class', function () {
    expect(DeleteAction::make())->toBeInstanceOf(Action::class);
});

it('has default name "delete"', function () {
    expect(DeleteAction::make()->getName())->toBe('delete');
});

it('can have custom name', function () {
    expect(DeleteAction::make('remove')->getName())->toBe('remove');
});

it('has preconfigured delete settings', function () {
    $action = DeleteAction::make();

    expect($action->getColor())->toBe('danger')
        ->and($action->getIcon())->toBe('trash')
        ->and($action->hasModal())->toBeTrue();
});
