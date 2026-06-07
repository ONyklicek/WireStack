<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\DefaultIconSet;
use NyonCode\WireCore\Foundation\Icons\HeroiconsOutlineSet;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\Icons\IconSet;
use NyonCode\WireCore\Foundation\Icons\ProvidesIconMetadata;
use NyonCode\WireCore\Foundation\Icons\ResolvedIcon;

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

it('registers a prefixed custom icon set', function () {
    $manager = new IconManager;

    $customSet = new class implements IconSet
    {
        public function getPath(string $name): ?string
        {
            return $name === 'star' ? '<path d="M10 0L20 20H0z"/>' : null;
        }

        public function has(string $name): bool
        {
            return $name === 'star';
        }

        public function names(): array
        {
            return ['star'];
        }
    };

    $manager->registerIconSet($customSet, 'brand');

    expect($manager->has('brand:star'))->toBeTrue();
    expect($manager->render('brand:star'))->toContain('M10 0L20 20H0z');
});

it('keeps non-default sets unreachable without their prefix', function () {
    $manager = new IconManager;

    $customSet = new class implements IconSet
    {
        public function getPath(string $name): ?string
        {
            return $name === 'sparkle' ? '<path d="M1 1h2"/>' : null;
        }

        public function has(string $name): bool
        {
            return $name === 'sparkle';
        }

        public function names(): array
        {
            return ['sparkle'];
        }
    };

    $manager->registerIconSet($customSet, 'brand');

    // Reachable with the prefix, not without it.
    expect($manager->has('brand:sparkle'))->toBeTrue()
        ->and($manager->has('sparkle'))->toBeFalse();

    // A bare name still resolves against the default Heroicons set.
    expect($manager->has('pencil'))->toBeTrue();
});

it('rejects registering a non-default set without a prefix', function () {
    $manager = new IconManager;

    $set = new DefaultIconSet;

    expect(fn () => $manager->registerIconSet($set))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $manager->registerIconSet($set, 'default'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a prefix containing a colon', function () {
    $manager = new IconManager;

    expect(fn () => $manager->registerIconSet(new DefaultIconSet, 'a:b'))
        ->toThrow(InvalidArgumentException::class);
});

it('swaps the unprefixed base set via setDefaultIconSet', function () {
    $manager = new IconManager;

    $base = new class implements IconSet
    {
        public function getPath(string $name): ?string
        {
            return $name === 'home' ? '<path d="M0 0h9v9H0z"/>' : null;
        }

        public function has(string $name): bool
        {
            return $name === 'home';
        }

        public function names(): array
        {
            return ['home'];
        }
    };

    $manager->setDefaultIconSet($base);

    // Bare names now resolve against the swapped base…
    expect($manager->render('home'))->toContain('M0 0h9v9H0z');

    // …and Heroicons-only names no longer resolve (fall back).
    expect($manager->has('pencil'))->toBeFalse();
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

it('renders a stroke-based set with its own viewBox alongside the default set', function () {
    $manager = new IconManager;

    $lucideStyle = new class implements IconSet, ProvidesIconMetadata
    {
        public function getIcon(string $name): ?ResolvedIcon
        {
            return $name === 'house'
                ? new ResolvedIcon(
                    '<path d="M3 9l9-7 9 7"/>',
                    '0 0 24 24',
                    ['fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '2'],
                )
                : null;
        }

        public function getPath(string $name): ?string
        {
            return $this->getIcon($name)?->body;
        }

        public function has(string $name): bool
        {
            return $name === 'house';
        }

        public function names(): array
        {
            return ['house'];
        }
    };

    $manager->registerIconSet($lucideStyle, 'lucide');

    $stroke = $manager->render('lucide:house');
    $solid = $manager->render('pencil');

    // The stroke icon keeps its own 24x24 viewBox and stroke styling…
    expect($stroke)->toContain('viewBox="0 0 24 24"')
        ->toContain('fill="none"')
        ->toContain('stroke="currentColor"')
        ->toContain('stroke-width="2"');

    // …while the bundled solid set still renders in its native 20x20 fill format.
    expect($solid)->toContain('viewBox="0 0 20 20"')
        ->toContain('fill="currentColor"');
});

it('preserves viewBox and stroke styling when registering a full svg element', function () {
    $manager = new IconManager;

    $manager->registerIcons([
        'feather-bell' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0"/></svg>',
    ]);

    $svg = $manager->render('feather-bell');

    expect($svg)->toContain('viewBox="0 0 24 24"')
        ->toContain('fill="none"')
        ->toContain('stroke="currentColor"')
        ->toContain('M18 8a6 6 0 0 0-12 0')
        ->and(substr_count($svg, '<svg'))->toBe(1);
});

it('exposes icons to assistive tech only when a label is given', function () {
    $manager = new IconManager;

    expect($manager->render('pencil'))->toContain('aria-hidden="true"');

    $labelled = $manager->render('pencil', 'w-4 h-4', '', 'Edit');
    expect($labelled)->toContain('role="img"')
        ->toContain('aria-label="Edit"')
        ->not->toContain('aria-hidden');
});

it('returns the inner markup via getPath for backward compatibility', function () {
    $manager = new IconManager;

    $path = $manager->getPath('pencil');

    expect($path)->toContain('<path')
        ->not->toContain('<svg');
});

it('lists every icon name across custom icons and registered sets', function () {
    $manager = new IconManager;
    $manager->registerIcons(['my-brand' => '<path d="M1 1h1"/>']);

    $names = $manager->allNames();

    expect($names)->toContain('my-brand')
        ->toContain('pencil')
        ->and($names)->toBe(array_values(array_unique($names)));
});

it('bundles the Heroicons outline set under the outline prefix', function () {
    // Available out of the box, without registering anything.
    $manager = new IconManager;

    expect($manager->has('outline:x-mark'))->toBeTrue();
});

it('renders the Heroicons outline set as 24x24 stroke alongside the solid default', function () {
    $manager = new IconManager;

    $outline = $manager->render('outline:x-mark', 'w-5 h-5');
    $solid = $manager->render('x-mark', 'w-5 h-5');

    // Outline keeps its native 24x24 stroke format…
    expect($outline)->toContain('viewBox="0 0 24 24"')
        ->toContain('fill="none"')
        ->toContain('stroke="currentColor"')
        ->toContain('stroke-width="1.5"');

    // …while the same bare name still resolves to solid 20x20 fill.
    expect($solid)->toContain('viewBox="0 0 20 20"')
        ->toContain('fill="currentColor"')
        ->not->toContain('stroke="currentColor"');
});

it('shares Wire aliases between the solid and outline variants', function () {
    $outline = new HeroiconsOutlineSet;

    // `close` and `edit` are Wire aliases; they must resolve in outline too.
    expect($outline->has('close'))->toBeTrue()
        ->and($outline->has('edit'))->toBeTrue()
        ->and($outline->getPath('close'))->toBe($outline->getPath('x-mark'));
});

it('ships the complete Heroicons outline collection', function () {
    $outline = new HeroiconsOutlineSet;

    expect(count($outline->names()))->toBeGreaterThanOrEqual(324)
        ->and($outline->has('check-circle'))->toBeTrue()
        ->and($outline->has('exclamation-triangle'))->toBeTrue()
        ->and($outline->has('queue-list'))->toBeTrue();
});

it('caches the solid and outline data files independently', function () {
    $manager = new IconManager;

    // Resolve solid first, then outline: the shared base-class cache must not
    // serve solid bodies for outline lookups (or vice versa).
    $solidInbox = $manager->render('inbox');
    $outlineInbox = $manager->render('outline:inbox');

    expect($solidInbox)->toContain('viewBox="0 0 20 20"')
        ->and($outlineInbox)->toContain('viewBox="0 0 24 24"');
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
