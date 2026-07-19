<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\View\FloatingAssets;

/**
 * Canonical owner of the floating-dropdown asset URL
 * (render-engine-htmlable-first.md §4). Its reason to exist is that the emitting
 * partial is included many times per page; the route + mtime must resolve once.
 */
it('builds the cache-busted dropdown asset URL', function () {
    $url = app(FloatingAssets::class)->url();

    expect($url)
        ->toContain('wire-core/assets/dropdown.js')
        ->toContain('?id='); // mtime cache-buster (the bundled asset exists)
});

it('memoises the URL so the route + mtime resolve once per request', function () {
    $assets = app(FloatingAssets::class);

    $first = $assets->url();

    // The memo is populated after the first resolve …
    expect((fn () => $this->url)->call($assets))->toBe($first);

    // … and reused on the next call.
    expect($assets->url())->toBe($first);
});

it('is bound as a singleton so the memo spans the whole request', function () {
    expect(app(FloatingAssets::class))->toBe(app(FloatingAssets::class));
});
