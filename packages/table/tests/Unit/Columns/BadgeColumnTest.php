<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireTable\Columns\BadgeColumn;

it('can be created', function () {
    expect(BadgeColumn::make('status'))->toBeInstanceOf(BadgeColumn::class);
});

// ─── Colors ─────────────────────────────────────────────────────────────────

it('can set color map', function () {
    $column = BadgeColumn::make('status')->colors([
        'active' => 'success',
        'inactive' => 'danger',
    ]);

    expect($column->getColorForState('active'))->toBe('success')
        ->and($column->getColorForState('inactive'))->toBe('danger')
        ->and($column->getColorForState('unknown'))->toBe('gray');
});

it('accepts Color enum in colors map', function () {
    $column = BadgeColumn::make('status')->colors([
        'active' => Color::Success,
        'inactive' => Color::Danger,
    ]);

    expect($column->getColorForState('active'))->toBe('success')
        ->and($column->getColorForState('inactive'))->toBe('danger');
});

it('can use color callback', function () {
    $column = BadgeColumn::make('status')->colorUsing(fn ($state) => match ($state) {
        'active' => 'success',
        default => 'gray',
    });

    expect($column->getColorForState('active'))->toBe('success');
});

// ─── Icons ──────────────────────────────────────────────────────────────────

it('can set icon map', function () {
    $column = BadgeColumn::make('status')->icons([
        'active' => 'check',
        'inactive' => 'x',
    ]);

    expect($column->getIconForState('active'))->toBe('check')
        ->and($column->getIconForState('inactive'))->toBe('x')
        ->and($column->getIconForState('unknown'))->toBeNull();
});

it('accepts Icon enum in icons map', function () {
    $column = BadgeColumn::make('status')->icons([
        'active' => Icon::check,
        'inactive' => Icon::xMark,
    ]);

    expect($column->getIconForState('active'))->toBe('check')
        ->and($column->getIconForState('inactive'))->toBe('x-mark');
});

it('can use icon callback', function () {
    $column = BadgeColumn::make('status')->iconUsing(fn ($state) => $state === 'active' ? 'check' : 'x');

    expect($column->getIconForState('active'))->toBe('check');
});

// ─── Size ───────────────────────────────────────────────────────────────────

it('has default md size', function () {
    expect(BadgeColumn::make('status')->getSize())->toBe('md');
});

it('can set custom size', function () {
    expect(BadgeColumn::make('status')->size('xs')->getSize())->toBe('xs');
});

it('generates correct size classes', function () {
    expect(BadgeColumn::make('status')->size('xs')->getSizeClasses())
        ->toContain('text-[10px]')
        ->and(BadgeColumn::make('status')->size('lg')->getSizeClasses())
        ->toContain('text-sm');
});

// ─── Color Classes ──────────────────────────────────────────────────────────

it('generates correct color classes for known colors', function () {
    $column = BadgeColumn::make('status');

    expect($column->getColorClasses('success'))->toContain('bg-emerald-100')
        ->and($column->getColorClasses('danger'))->toContain('bg-red-100')
        ->and($column->getColorClasses('warning'))->toContain('bg-amber-100')
        ->and($column->getColorClasses('info'))->toContain('bg-cyan-100')
        ->and($column->getColorClasses('gray'))->toContain('bg-gray-100');
});

it('falls back to gray for unknown colors', function () {
    expect(BadgeColumn::make('status')->getColorClasses('nonexistent'))
        ->toContain('bg-gray-100');
});

it('color callback can return Color enum', function () {
    $column = BadgeColumn::make('status')->colorUsing(fn ($state) => $state === 'active' ? Color::Success : Color::Gray);

    expect($column->getColorForState('active'))->toBe('success')
        ->and($column->getColorForState('other'))->toBe('gray');
});

it('icon callback can return Icon enum', function () {
    $column = BadgeColumn::make('status')->iconUsing(fn ($state) => $state === 'active' ? Icon::check : null);

    expect($column->getIconForState('active'))->toBe('check')
        ->and($column->getIconForState('other'))->toBeNull();
});

it('renders the empty-cell text instead of a badge for a null state', function () {
    // An empty value must not render an empty coloured pill; it shows the
    // empty-cell placeholder like any other column.
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 1, 'status' => null]);

    $column = BadgeColumn::make('status')->placeholder('—');

    expect($column->renderCell($record))->toBe('—');
});
