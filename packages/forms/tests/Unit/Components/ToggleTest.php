<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\Toggle;

test('make creates toggle with name', function () {
    expect(Toggle::make('active')->getName())->toBe('active');
});

test('on and off colors default to primary and gray', function () {
    $toggle = Toggle::make('active');

    expect($toggle->getOnColor())->toBe('primary')
        ->and($toggle->getOffColor())->toBe('gray');
});

test('on color produces resolved track classes (regression: view ignored the API)', function () {
    expect(Toggle::make('active')->onColor('success')->getOnColorClasses())->toContain('bg-emerald-600')
        ->and(Toggle::make('active')->getOnColorClasses())->toContain('bg-primary-600');
});

test('off color produces resolved track classes', function () {
    expect(Toggle::make('active')->offColor('danger')->getOffColorClasses())->toContain('bg-red-200')
        ->and(Toggle::make('active')->getOffColorClasses())->toContain('bg-gray-200');
});

test('icons are stored and exposed for the view', function () {
    $toggle = Toggle::make('active')->onIcon('check')->offIcon('x-mark');

    expect($toggle->getOnIcon())->toBe('check')
        ->and($toggle->getOffIcon())->toBe('x-mark');
});
