<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;
use NyonCode\WireCore\Foundation\Icons\Icon;
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

it('normalizes full svg markup when registering custom icons', function () {
    $manager = new IconManager;

    $manager->registerIcons([
        'brand' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M1 1h2"/></svg>',
    ]);

    $svg = $manager->render('brand');

    expect($manager->has('brand'))->toBeTrue()
        ->and($svg)->toContain('M1 1h2')
        ->and(substr_count($svg, '<svg'))->toBe(1);
});

it('registers icons from a directory', function () {
    $dir = sys_get_temp_dir().'/wire-icons-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/logo.svg', '<svg viewBox="0 0 20 20"><path d="M9 9h2"/></svg>');
    file_put_contents($dir.'/mark.svg', '<path d="M5 5h5"/>');

    try {
        $manager = new IconManager;
        $manager->registerIconsFromDirectory($dir, 'brand');

        expect($manager->has('brand-logo'))->toBeTrue()
            ->and($manager->has('brand-mark'))->toBeTrue()
            ->and($manager->render('brand-logo'))->toContain('M9 9h2');
    } finally {
        @unlink($dir.'/logo.svg');
        @unlink($dir.'/mark.svg');
        @rmdir($dir);
    }
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

it('provides a real path for every Icon enum case', function () {
    $manager = new IconManager;
    $iconSet = new DefaultIconSet;

    foreach (Icon::cases() as $case) {
        $name = $case->value();

        expect($iconSet->has($name))
            ->toBeTrue("Expected DefaultIconSet to contain '{$name}' (case {$case->name})");

        expect($manager->render($name))
            ->toContain('<svg')
            ->toContain('<path');
    }
});

it('ships the complete Heroicons solid collection', function () {
    $iconSet = new DefaultIconSet;

    // The official Heroicons 2.2.0 solid set ships 324 icons; aliases add a few more.
    expect(count($iconSet->names()))->toBeGreaterThanOrEqual(324);

    // Spot-check a spread of icons across the alphabet that are not Wire aliases.
    $sample = [
        'academic-cap', 'banknotes', 'cog-6-tooth', 'document-text', 'envelope-open',
        'globe-alt', 'magnifying-glass', 'qr-code', 'square-3-stack-3d', 'squares-2x2',
        'wrench-screwdriver', 'x-mark',
    ];

    foreach ($sample as $icon) {
        expect($iconSet->has($icon))->toBeTrue("Expected Heroicon '{$icon}' to exist");
    }
});
