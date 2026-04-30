<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;

// ─── Hidden ────────────────────────────────────────────────────────────────

it('is visible by default', function () {
    $action = Action::make('test');

    expect($action->isHidden())->toBeFalse();
});

it('can be hidden', function () {
    $action = Action::make('test')->hidden();

    expect($action->isHidden())->toBeTrue();
});

it('can be explicitly set visible', function () {
    $action = Action::make('test')->hidden()->visible();

    expect($action->isHidden())->toBeFalse();
});

it('supports dynamic hidden via closure', function () {
    $action = Action::make('test')
        ->hidden(fn ($record) => $record->is_locked);

    $locked = (object) ['is_locked' => true];
    $unlocked = (object) ['is_locked' => false];

    expect($action->isHidden($locked))->toBeTrue()
        ->and($action->isHidden($unlocked))->toBeFalse();
});

// ─── Disabled ──────────────────────────────────────────────────────────────

it('is not disabled by default', function () {
    expect(Action::make('test')->isDisabled())->toBeFalse();
});

it('can be disabled', function () {
    expect(Action::make('test')->disabled()->isDisabled())->toBeTrue();
});

it('supports dynamic disabled via closure', function () {
    $action = Action::make('test')
        ->disabled(fn ($record) => $record->status === 'archived');

    $archived = (object) ['status' => 'archived'];
    $active = (object) ['status' => 'active'];

    expect($action->isDisabled($archived))->toBeTrue()
        ->and($action->isDisabled($active))->toBeFalse();
});

// ─── Permission ────────────────────────────────────────────────────────────

it('has no permission by default', function () {
    expect(Action::make('test')->getPermission())->toBeNull();
});

it('can set permission', function () {
    $action = Action::make('delete')->permission('delete-users');

    expect($action->getPermission())->toBe('delete-users');
});

// ─── canExecute ────────────────────────────────────────────────────────────

it('can execute when visible and not disabled', function () {
    $action = Action::make('test');

    expect($action->canExecute())->toBeTrue();
});

it('cannot execute when hidden', function () {
    $action = Action::make('test')->hidden();

    expect($action->canExecute())->toBeFalse();
});

it('can still execute when disabled (disabled is visual only)', function () {
    $action = Action::make('test')->disabled();

    // Disabled affects rendering, not execution permission
    expect($action->canExecute())->toBeTrue();
});
