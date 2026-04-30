<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSet;

it('renders default icons', function () {
    $manager = new IconManager;

    $svg = $manager->render('pencil');

    expect($svg)->toContain('<svg')
        ->toContain('class="w-4 h-4"')
        ->toContain('viewBox="0 0 20 20"')
        ->toContain('<path');
});

it('renders icons with custom size and class', function () {
    $manager = new IconManager;

    $svg = $manager->render('trash', 'w-5 h-5', 'text-red-500');

    expect($svg)->toContain('class="w-5 h-5 text-red-500"');
});

it('returns fallback for unknown icons', function () {
    $manager = new IconManager;

    $svg = $manager->render('nonexistent-icon');

    expect($svg)->toContain('<svg')
        ->toContain('<path');
});

it('checks icon existence', function () {
    $manager = new IconManager;

    expect($manager->has('pencil'))->toBeTrue()
        ->and($manager->has('nonexistent'))->toBeFalse();
});

it('registers custom icons with priority', function () {
    $manager = new IconManager;

    $manager->registerIcons([
        'custom-icon' => '<path d="M1 1h18v18H1z"/>',
    ]);

    expect($manager->has('custom-icon'))->toBeTrue();
    $svg = $manager->render('custom-icon');
    expect($svg)->toContain('M1 1h18v18H1z');
});

it('registers custom icon sets', function () {
    $manager = new IconManager;

    $customSet = new class implements IconSet
    {
        public function getPath(string $name): ?string
        {
            return $name === 'star-custom' ? '<path d="M10 0L20 20H0z"/>' : null;
        }

        public function has(string $name): bool
        {
            return $name === 'star-custom';
        }

        public function names(): array
        {
            return ['star-custom'];
        }
    };

    $manager->registerIconSet($customSet);

    expect($manager->has('star-custom'))->toBeTrue();
    $svg = $manager->render('star-custom');
    expect($svg)->toContain('M10 0L20 20H0z');
});

it('custom icons override default icons', function () {
    $manager = new IconManager;

    $manager->registerIcons([
        'pencil' => '<path d="CUSTOM"/>',
    ]);

    $svg = $manager->render('pencil');
    expect($svg)->toContain('CUSTOM');
});

it('default icon set has all expected icons', function () {
    $iconSet = new DefaultIconSet;

    $expectedIcons = ['pencil', 'trash', 'eye', 'plus', 'check', 'x', 'cog', 'user', 'calendar', 'filter', 'chevron-down'];

    foreach ($expectedIcons as $icon) {
        expect($iconSet->has($icon))->toBeTrue("Expected icon '{$icon}' to exist");
    }
});
