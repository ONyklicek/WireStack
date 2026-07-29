<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Actions\EmptyStateActionClickResolver;

it('routes a plain empty state action to executeHeaderAction', function () {
    $resolver = new EmptyStateActionClickResolver;

    expect($resolver->clickHandler(Action::make('create'), null))
        ->toBe("executeHeaderAction('create')");
});

it('routes a modal empty state action to openHeaderActionModal', function () {
    $resolver = new EmptyStateActionClickResolver;
    $action = Action::make('create')->modalHeading('Create');

    expect($resolver->clickHandler($action, null))
        ->toBe("openHeaderActionModal('create')");
});

// The empty state has no rows. Were a record ever threaded through, the click
// must still not carry it — the host resolves these without one.
it('ignores a record it is handed', function () {
    $resolver = new EmptyStateActionClickResolver;
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 3]);

    expect($resolver->clickHandler(Action::make('create'), $record))
        ->toBe("executeHeaderAction('create')");
});
