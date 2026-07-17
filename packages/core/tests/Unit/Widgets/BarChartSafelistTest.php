<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\BarChartWidget;

/**
 * Guards the Tailwind safelist Blade file against drift from the canonical
 * HasColor resolvers. A consuming app scans the package views but not the
 * package src, so every gradient/text utility a bar can emit at runtime must
 * also appear literally in resources/views/widgets/bar-chart/safelist.blade.php
 * for the host's Tailwind build to generate it.
 */
$safelistPath = __DIR__.'/../../../resources/views/widgets/bar-chart/safelist.blade.php';

/** Every color key the chart palette resolvers branch on, plus an unknown one. */
$colorKeys = [
    'primary', 'blue', 'green', 'success', 'emerald', 'danger', 'red',
    'warning', 'yellow', 'amber', 'info', 'cyan', 'sky', 'purple', 'violet',
    'indigo', 'orange', 'lime', 'teal', 'fuchsia', 'pink', 'rose', 'slate',
    'zinc', 'neutral', 'stone', 'gray', 'secondary', 'white', 'black',
    'totally-unknown',
];

it('has a safelist file', function () use ($safelistPath) {
    expect(file_exists($safelistPath))->toBeTrue();
});

it('safelists every gradient fill class the widget can emit', function () use ($safelistPath, $colorKeys) {
    $safelist = (string) file_get_contents($safelistPath);

    foreach ($colorKeys as $color) {
        expect($safelist)->toContain(BarChartWidget::getGradientFillClasses($color));
    }
});

it('safelists every accent text class the widget can emit', function () use ($safelistPath, $colorKeys) {
    $safelist = (string) file_get_contents($safelistPath);

    foreach ($colorKeys as $color) {
        expect($safelist)->toContain(BarChartWidget::getFillTextClasses($color));
    }
});

it('safelists the direction and value-driven sizing utilities used by the partials', function () use ($safelistPath) {
    $safelist = (string) file_get_contents($safelistPath);

    expect($safelist)
        ->toContain('bg-gradient-to-r')
        ->toContain('bg-gradient-to-t')
        ->toContain('w-[var(--value)]')
        ->toContain('h-[var(--value)]');
});
