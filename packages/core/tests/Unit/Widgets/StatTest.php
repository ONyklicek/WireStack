<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\Stat;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    $stat = Stat::make('Revenue', '$45,231');

    expect($stat)->toBeInstanceOf(Stat::class)
        ->and($stat->getLabel())->toBe('Revenue')
        ->and($stat->getValue())->toBe('$45,231');
});

// ─── Fluent API ──────────────────────────────────────────────────────────────

it('supports all fluent setters', function () {
    $stat = Stat::make('Revenue', '$45,231')
        ->description('12% increase')
        ->descriptionIcon('heroicon-o-arrow-up')
        ->color('success')
        ->chart([7, 3, 4, 5, 6, 3, 5])
        ->icon('heroicon-o-currency-dollar');

    expect($stat->getDescription())->toBe('12% increase')
        ->and($stat->getDescriptionIcon())->toBe('heroicon-o-arrow-up')
        ->and($stat->getColor())->toBe('success')
        ->and($stat->getChart())->toBe([7, 3, 4, 5, 6, 3, 5])
        ->and($stat->hasChart())->toBeTrue()
        ->and($stat->getIcon())->toBe('heroicon-o-currency-dollar');
});

it('supports dynamic extra attributes', function () {
    $stat = Stat::make('Revenue', '$45,231')
        ->extraAttributes(fn (Stat $component) => [
            'data-label' => $component->getLabel(),
        ]);

    expect($stat->getExtraAttributes())->toBe([
        'data-label' => 'Revenue',
    ]);
});

// ─── Defaults ────────────────────────────────────────────────────────────────

it('has null defaults for optional properties', function () {
    $stat = Stat::make('Revenue', '$0');

    expect($stat->getDescription())->toBeNull()
        ->and($stat->getDescriptionIcon())->toBeNull()
        ->and($stat->getColor())->toBeNull()
        ->and($stat->getChart())->toBeNull()
        ->and($stat->hasChart())->toBeFalse()
        ->and($stat->getIcon())->toBeNull();
});

// ─── Chart ───────────────────────────────────────────────────────────────────

it('detects empty chart', function () {
    $stat = Stat::make('Revenue', '$0')->chart([]);

    expect($stat->hasChart())->toBeFalse();
});

it('detects non-empty chart', function () {
    $stat = Stat::make('Revenue', '$0')->chart([1, 2, 3]);

    expect($stat->hasChart())->toBeTrue()
        ->and($stat->getChart())->toHaveCount(3);
});
