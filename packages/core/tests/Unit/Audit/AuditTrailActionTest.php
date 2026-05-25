<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Audit\Actions\AuditTrailAction;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    $action = AuditTrailAction::make();

    expect($action)->toBeInstanceOf(AuditTrailAction::class)
        ->and($action)->toBeInstanceOf(Action::class)
        ->and($action->getName())->toBe('auditTrail');
});

it('can be created with custom name', function () {
    $action = AuditTrailAction::make('history');

    expect($action->getName())->toBe('history');
});

// ─── Default Configuration ───────────────────────────────────────────────────

it('is configured as slide-over', function () {
    $action = AuditTrailAction::make();

    expect($action->isSlideOver())->toBeTrue();
});

it('has modal enabled', function () {
    $action = AuditTrailAction::make();

    expect($action->hasModal())->toBeTrue();
});

it('uses gray color', function () {
    $action = AuditTrailAction::make();

    expect($action->getColor())->toBe('gray');
});

it('uses clock icon', function () {
    $action = AuditTrailAction::make();

    expect($action->getIcon())->toBe('clock');
});

it('uses lg modal width', function () {
    $action = AuditTrailAction::make();

    expect($action->getModalWidth())->toBe('lg');
});
