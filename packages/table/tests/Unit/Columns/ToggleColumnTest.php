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

it('renders toggle on-color via the canonical Foundation palette', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 7, 'active' => true]);

    // Delegates to HasColor::getSolidBgClass — primary, success → emerald,
    // warning → amber (not the old blue/green/yellow drift).
    expect(ToggleColumn::make('active')->onColor('primary')->renderCell($record))->toContain('bg-primary-600')
        ->and(ToggleColumn::make('active')->onColor('success')->renderCell($record))->toContain('bg-emerald-600')
        ->and(ToggleColumn::make('active')->onColor('warning')->renderCell($record))->toContain('bg-amber-500')
        ->and(ToggleColumn::make('active')->onColor('gray')->renderCell($record))->toContain('bg-gray-600');
});

it('renders toggle off-color via the canonical Foundation palette', function () {
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 7, 'active' => false]);

    // The "off" track now honors offColor() and delegates to
    // HasColor::getSoftBgClass instead of the previously hardcoded gray.
    expect(ToggleColumn::make('active')->offColor('danger')->renderCell($record))->toContain('bg-red-200')
        ->and(ToggleColumn::make('active')->offColor('success')->renderCell($record))->toContain('bg-emerald-200')
        ->and(ToggleColumn::make('active')->renderCell($record))->toContain('bg-gray-200');
});
