<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\BulkAction;
use NyonCode\WireCore\Actions\HeaderAction;

// ─── Factory & Name ─────────────────────────────────────────────────────────

it('can be created via make()', function () {
    $action = Action::make('edit');

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getName())->toBe('edit');
});

// ─── Label ──────────────────────────────────────────────────────────────────

it('generates label from name if not set', function () {
    $action = Action::make('approve_request');

    expect($action->getLabel())->toBe('Approve Request');
});

it('can set custom label', function () {
    $action = Action::make('edit')->label('Upravit');

    expect($action->getLabel())->toBe('Upravit');
});

it('supports dynamic label via closure', function () {
    $action = Action::make('toggle')
        ->label(fn ($record) => $record->active ? 'Deaktivovat' : 'Aktivovat');

    $activeRecord = (object) ['active' => true];
    $inactiveRecord = (object) ['active' => false];

    expect($action->getLabel($activeRecord))->toBe('Deaktivovat')
        ->and($action->getLabel($inactiveRecord))->toBe('Aktivovat');
});

// ─── Color ──────────────────────────────────────────────────────────────────

it('has default primary color', function () {
    expect(Action::make('test')->getColor())->toBe('primary');
});

it('can set custom color', function () {
    expect(Action::make('delete')->color('danger')->getColor())->toBe('danger');
});

it('supports dynamic color via closure', function () {
    $action = Action::make('status')
        ->color(fn ($record) => $record->active ? 'success' : 'danger');

    $active = (object) ['active' => true];
    $inactive = (object) ['active' => false];

    expect($action->getColor($active))->toBe('success')
        ->and($action->getColor($inactive))->toBe('danger');
});

// ─── Icon ───────────────────────────────────────────────────────────────────

it('has no icon by default', function () {
    expect(Action::make('test')->getIcon())->toBeNull();
});

it('can set icon with position', function () {
    $action = Action::make('edit')->icon('pencil', 'after');

    expect($action->getIcon())->toBe('pencil')
        ->and($action->getIconPosition())->toBe('after');
});

it('supports dynamic icon via closure', function () {
    $action = Action::make('toggle')
        ->icon(fn ($record) => $record->active ? 'check' : 'x');

    $active = (object) ['active' => true];
    expect($action->getIcon($active))->toBe('check');
});

// ─── Size ───────────────────────────────────────────────────────────────────

it('has default sm size', function () {
    expect(Action::make('test')->getSize())->toBe('sm');
});

it('can set custom size', function () {
    expect(Action::make('test')->size('lg')->getSize())->toBe('lg');
});

it('supports dynamic size via closure', function () {
    $action = Action::make('test')
        ->size(fn ($record) => $record->important ? 'lg' : 'sm');

    $important = (object) ['important' => true];
    expect($action->getSize($important))->toBe('lg');
});

// ─── Tooltip ────────────────────────────────────────────────────────────────

it('has no tooltip by default', function () {
    expect(Action::make('test')->getTooltip())->toBeNull();
});

it('can set tooltip', function () {
    expect(Action::make('edit')->tooltip('Upravit záznam')->getTooltip())->toBe('Upravit záznam');
});

it('supports dynamic tooltip via closure', function () {
    $action = Action::make('info')
        ->tooltip(fn ($record) => "ID: {$record->id}");

    $record = (object) ['id' => 42];
    expect($action->getTooltip($record))->toBe('ID: 42');
});

// ─── Outlined ───────────────────────────────────────────────────────────────

it('is not outlined by default', function () {
    expect(Action::make('test')->isOutlined())->toBeFalse();
});

it('can be set to outlined', function () {
    expect(Action::make('test')->outlined()->isOutlined())->toBeTrue();
});

// ─── Action Callback ────────────────────────────────────────────────────────

it('has no action callback by default', function () {
    expect(Action::make('test')->getActionCallback())->toBeNull();
});

it('can set action callback', function () {
    $callback = fn ($record) => $record->delete();
    $action = Action::make('delete')->action($callback);

    expect($action->getActionCallback())->toBe($callback);
});

// ─── Extra Attributes ───────────────────────────────────────────────────────

it('has empty extra attributes by default', function () {
    expect(Action::make('test')->getExtraAttributes())->toBe([]);
});

it('can set extra attributes as array', function () {
    $action = Action::make('test')->extraAttributes(['data-id' => '1']);

    expect($action->getExtraAttributes())->toBe(['data-id' => '1']);
});

it('supports dynamic extra attributes via closure', function () {
    $action = Action::make('test')
        ->extraAttributes(fn ($record) => ['data-id' => $record->id]);

    $record = (object) ['id' => 5];
    expect($action->getExtraAttributes($record))->toBe(['data-id' => 5]);
});

// ─── Button class assembly (header/bulk action Blade fallback) ───────────────

it('builds button classes for a header action without per-record data (regression)', function () {
    // Regression: the button.blade fallback called protected resolve* helpers
    // with missing args and fataled for actions lacking getRenderData().
    $classes = HeaderAction::make('create')->getButtonClasses();

    expect($classes)->toBeString()
        ->and($classes)->toContain('inline-flex');
});

it('builds button classes for a bulk action and honours outlined color', function () {
    $solid = BulkAction::make('delete')->color('success')->getButtonClasses();
    $outlined = BulkAction::make('delete')->color('success')->outlined()->getButtonClasses();

    expect($solid)->toBeString()->not->toBe('')
        ->and($outlined)->toBeString()->not->toBe('')
        ->and($outlined)->not->toBe($solid);
});
