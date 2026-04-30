<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Actions\Action;

// ─── Divider ────────────────────────────────────────────────────────────────

it('can create a divider action', function () {
    $divider = Action::divider();

    expect($divider->isDivider())->toBeTrue()
        ->and($divider->getName())->toBe('__divider__');
});

it('is not a divider by default', function () {
    expect(Action::make('edit')->isDivider())->toBeFalse();
});

// ─── URL ────────────────────────────────────────────────────────────────────

it('can set url as string', function () {
    $action = Action::make('view')->url('/users/1');

    $model = Mockery::mock(Model::class);

    expect($action->getUrl($model))->toBe('/users/1');
});

it('can set url as closure', function () {
    $action = Action::make('view')->url(fn ($record) => '/users/'.$record->getKey());

    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn(42);

    expect($action->getUrl($model))->toBe('/users/42');
});

it('returns null url when not set', function () {
    $model = Mockery::mock(Model::class);

    expect(Action::make('test')->getUrl($model))->toBeNull();
});

it('can open url in new tab', function () {
    $action = Action::make('view')->url('/users/1', openInNewTab: true);

    expect($action->shouldOpenUrlInNewTab())->toBeTrue();
});

it('does not open url in new tab by default', function () {
    $action = Action::make('view')->url('/users/1');

    expect($action->shouldOpenUrlInNewTab())->toBeFalse();
});

// ─── Icon Button ────────────────────────────────────────────────────────────

it('is not an icon button by default', function () {
    expect(Action::make('test')->isIconButton())->toBeFalse();
});

it('can be set as icon button', function () {
    expect(Action::make('test')->iconButton()->isIconButton())->toBeTrue();
});

// ─── Hide Label ─────────────────────────────────────────────────────────────

it('does not hide label by default', function () {
    expect(Action::make('test')->isHideLabel())->toBeFalse();
});

it('can hide label', function () {
    expect(Action::make('test')->hideLabel()->isHideLabel())->toBeTrue();
});

it('onlyIcon is an alias for hideLabel', function () {
    expect(Action::make('test')->onlyIcon()->isHideLabel())->toBeTrue();
});

it('deprecated hiddeLabel works', function () {
    expect(Action::make('test')->hiddeLabel()->isHideLabel())->toBeTrue();
});
