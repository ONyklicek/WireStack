<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use NyonCode\WireCore\Foundation\Icons\IconManager;

/**
 * The `icon()` helper (icon_helper_not_directive_2026-07-18).
 *
 * The Htmlable-first render path for icons: PHP produces the `<svg>` string
 * through the canonical, memoised {@see IconManager}; Blade only consumes it via
 * `{!! icon(...) !!}`. It replaces both the `@icon` directive (a Blade-compiler
 * construct that put presentation in the template) and `<x-wire::icon>` (a full
 * view render per call) in every framework render path.
 */
it('returns exactly what IconManager::render produces', function () {
    $manager = app(IconManager::class);

    expect(icon('pencil'))->toBe($manager->render('pencil'));
    expect(icon('trash', 'w-5 h-5', 'text-red-500'))
        ->toBe($manager->render('trash', 'w-5 h-5', 'text-red-500'));
    expect(icon('check', 'h-4 w-4', 'text-white', 'Selected'))
        ->toBe($manager->render('check', 'h-4 w-4', 'text-white', 'Selected'));
});

it('is a plain string echoed by {!! !!}, not a Blade construct', function () {
    // No directive is involved: the helper is a normal function call, so it works
    // identically inside {!! !!} and in raw PHP. The output is a complete <svg>.
    $html = icon('pencil', 'w-5 h-5', 'text-red-500');

    expect($html)->toStartWith('<svg')->toEndWith('</svg>')
        ->toContain('w-5 h-5')->toContain('text-red-500');
});

it('forwards $attributes onto the <svg> root (Alpine-bound icons come from PHP)', function () {
    // The one case that used to require <x-wire::icon>: a binding on the svg root.
    $html = icon('clipboard', 'w-4 h-4', 'text-gray-400', '', ['x-show' => '!copied']);

    expect($html)->toStartWith('<svg')
        ->toContain('x-show="!copied"')
        ->toContain('text-gray-400');

    // Different attributes ⇒ different cache entry (not collapsed by the manager).
    expect(icon('clipboard', 'w-4 h-4', 'text-gray-400', '', ['x-show' => 'copied']))
        ->toContain('x-show="copied"')
        ->not->toContain('x-show="!copied"');
});

it('is equivalent to a static <x-wire::icon> (the migration invariant)', function () {
    $helper = icon('pencil', 'w-5 h-5', 'text-red-500');
    $component = trim(Blade::render('<x-wire::icon name="pencil" size="w-5 h-5" class="text-red-500" />'));

    // The component merges attributes, so <svg> attribute ORDER can differ — that
    // is cosmetic (browsers ignore attribute order). Compare the order-independent
    // set plus the inner body.
    $normalize = function (string $svg): array {
        preg_match('/<svg\s+(.*?)>(.*)<\/svg>/s', $svg, $m);
        preg_match_all('/([\w:@-]+)="[^"]*"/', $m[1], $attrs);
        sort($attrs[0]);

        return ['attrs' => $attrs[0], 'body' => $m[2]];
    };

    expect($normalize($helper))->toBe($normalize($component));
});
