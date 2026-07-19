<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Icons\IconManager;
use NyonCode\WireCore\Foundation\View\Primitives;

/**
 * Canonical owner of record-invariant primitive markup
 * (render-engine-htmlable-first.md §3). Its whole reason to exist is that a caller
 * in a table row loop pays one render for the whole table, not one per row — so the
 * memoisation is the load-bearing behaviour and is pinned here.
 */
it('renders the canonical spinner as a ready-to-echo string', function () {
    $svg = app(Primitives::class)->spinner();

    expect($svg)->toContain('<svg')
        ->toContain('animate-spin')
        ->toContain('h-4 w-4 text-primary-500') // default class
        ->not->toContain('wire:target');        // record-invariant by default
});

it('carries a wire:target on the spinner only when asked', function () {
    $svg = app(Primitives::class)->spinner('w-5 h-5', 'submitAction');

    expect($svg)->toContain('wire:target="submitAction"')
        ->toContain('w-5 h-5');
});

it('memoises each distinct spinner once', function () {
    $primitives = app(Primitives::class);

    $a = $primitives->spinner();
    $b = $primitives->spinner();

    expect($b)->toBe($a);

    $cache = (fn () => $this->cache)->call($primitives);
    expect($cache)->toHaveCount(1);

    // A different parameter set is a distinct entry.
    $primitives->spinner('w-5 h-5', 'submitAction');
    $cache = (fn () => $this->cache)->call($primitives);
    expect($cache)->toHaveCount(2);
});

it('renders the success check by delegating to the icon owner', function () {
    $svg = app(Primitives::class)->successCheck();

    // check-circle icon, green by default — the same markup IconManager produces,
    // so it is themeable and memoised there.
    expect($svg)
        ->toContain('<svg')
        ->toContain('text-green-500')
        ->toBe(app(IconManager::class)->render('check-circle', 'h-4 w-4', 'text-green-500'));
});

it('is bound as a singleton so its memo spans the whole request', function () {
    expect(app(Primitives::class))->toBe(app(Primitives::class));
});
