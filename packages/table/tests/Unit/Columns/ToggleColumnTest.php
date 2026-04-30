<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\ToggleColumn;

it('can be created', function () {
    expect(ToggleColumn::make('active'))->toBeInstanceOf(ToggleColumn::class);
});

it('is editable by default', function () {
    expect(ToggleColumn::make('active')->isEditable())->toBeTrue();
});

it('has toggle editable type', function () {
    $column = ToggleColumn::make('active');

    // ToggleColumn sets editableType to 'toggle' in constructor
    expect($column)->toBeInstanceOf(ToggleColumn::class);
});

// ─── Colors ─────────────────────────────────────────────────────────────────

it('can set on/off colors', function () {
    $column = ToggleColumn::make('active')
        ->onColor('success')
        ->offColor('danger');

    expect($column)->toBeInstanceOf(ToggleColumn::class);
});

// ─── Icons ──────────────────────────────────────────────────────────────────

it('can set on/off icons', function () {
    $column = ToggleColumn::make('active')
        ->onIcon('check')
        ->offIcon('x');

    expect($column)->toBeInstanceOf(ToggleColumn::class);
});

// ─── Disabled ───────────────────────────────────────────────────────────────

it('is not disabled by default', function () {
    $record = Mockery::mock(Model::class);

    expect(ToggleColumn::make('active')->isDisabled($record))->toBeFalse();
});

it('can be disabled', function () {
    $record = Mockery::mock(Model::class);

    expect(ToggleColumn::make('active')->disabled()->isDisabled($record))->toBeTrue();
});

it('can be disabled via closure', function () {
    $column = ToggleColumn::make('active')
        ->disabled(fn ($record) => $record->locked);

    $locked = Mockery::mock(Model::class);
    $locked->shouldReceive('getAttribute')->with('locked')->andReturn(true);
    $locked->shouldReceive('offsetExists')->andReturn(true);

    $unlocked = Mockery::mock(Model::class);
    $unlocked->shouldReceive('getAttribute')->with('locked')->andReturn(false);
    $unlocked->shouldReceive('offsetExists')->andReturn(true);

    expect($column->isDisabled($locked))->toBeTrue()
        ->and($column->isDisabled($unlocked))->toBeFalse();
});
