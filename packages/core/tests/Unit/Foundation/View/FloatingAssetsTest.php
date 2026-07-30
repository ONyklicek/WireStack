<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Assets\AssetManager;
use NyonCode\WireCore\Foundation\View\FloatingAssets;

/**
 * The floating-dropdown asset URL by the name a dozen partials ask for it
 * (render-engine-htmlable-first.md §4). Its reason to exist is that the emitting
 * partial is included many times per page; the route + mtime must resolve once.
 * The memo itself now belongs to the canonical AssetManager.
 */
it('builds the cache-busted dropdown asset URL', function () {
    $url = app(FloatingAssets::class)->url();

    expect($url)
        ->toContain('wire-core/assets/dropdown.js')
        ->toContain('?id='); // mtime cache-buster (the bundled asset exists)
});

it('delegates to the registered asset so the route + mtime resolve once per request', function () {
    $assets = app(FloatingAssets::class);

    // Same string, and the same string the canonical owner hands to every other
    // consumer — there is no second copy of the resolution to drift.
    expect($assets->url())
        ->toBe($assets->url())
        ->toBe(app(AssetManager::class)->url('wire-core', 'dropdown'));
});

it('is bound as a singleton so the memo spans the whole request', function () {
    expect(app(FloatingAssets::class))->toBe(app(FloatingAssets::class));
});
