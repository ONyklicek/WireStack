<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Assets\Bundle;

/**
 * The asset fallback route, as one owner rather than four copies.
 *
 * Every wireStack provider used to write this route out itself, and what the
 * copies actually carried — the 404 instead of a 500, the id pattern, the
 * immutable cache header — was asserted in two packages out of four. Deleting
 * `abort_unless`, the `where()` constraint or the cache headers from the table
 * and forms copies passed both suites. These are those assertions, once.
 */
beforeEach(function () {
    $this->dist = sys_get_temp_dir().'/wire-bundle-route-'.bin2hex(random_bytes(6));

    mkdir($this->dist);
});

afterEach(function () {
    array_map(unlink(...), glob($this->dist.'/*') ?: []);
    rmdir($this->dist);
});

test('it serves a package bundle by its id', function () {
    file_put_contents($this->dist.'/wire-demo-panel.js', 'window.demoPanel = 1;');
    Bundle::serve('wire-demo', $this->dist);

    $response = $this->get('/wire-demo/assets/panel.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))
        ->toBe('application/javascript; charset=utf-8');
});

test('it answers with the cache header that keeps a fallback from costing a request per page', function () {
    file_put_contents($this->dist.'/wire-cache-panel.js', 'window.cachePanel = 1;');
    Bundle::serve('wire-cache', $this->dist);

    // ADR 0024 puts static files first and this route behind them, so the route
    // is reached on every page of an app that cannot publish. Without a public,
    // long-lived max-age that is one request per bundle per page view; the file
    // is streamed straight out of the package and never varies per user.
    $cacheControl = $this->get('/wire-cache/assets/panel.js')->headers->get('Cache-Control');

    expect($cacheControl)
        ->toContain('public')
        ->toContain('max-age=31536000');
});

test('a bundle the package does not ship is a 404, not a 500', function () {
    Bundle::serve('wire-missing', $this->dist);

    // response()->file() on a path that is not there throws, so without the
    // guard a mistyped id surfaces as a server error in the consumer's logs.
    $this->get('/wire-missing/assets/nope.js')->assertNotFound();
});

test('the id pattern bars anything but a bundle id', function () {
    // Planted so the id alphabet is the only thing that can 404 this: with no
    // `where()` on the route, `a~b` is a perfectly good match, reaches the file
    // lookup and is served (measured — so are `a b` and `á`). A missing file
    // would 404 either way and assert nothing.
    file_put_contents($this->dist.'/wire-guard-a~b.js', 'window.guardPanel = 1;');
    Bundle::serve('wire-guard', $this->dist);

    // The traversal cases below are barred a step earlier, by the route pattern
    // itself, and stay here as the record of that.
    $this->get('/wire-guard/assets/a~b.js')->assertNotFound();
    $this->get('/wire-guard/assets/..%2F..%2Fcomposer.js')->assertNotFound();
    $this->get('/wire-guard/assets/sub/panel.js')->assertNotFound();
});

test('it finds a single-bundle package whose file carries only the wire- prefix', function () {
    // The wire-sortable shape: one bundle named after the package itself, so the
    // id `sortable` resolves `wire-sortable.js` and never `wire-sortable-sortable.js`.
    file_put_contents($this->dist.'/wire-solo.js', 'window.solo = 1;');
    Bundle::serve('wire-solo', $this->dist);

    $this->get('/wire-solo/assets/solo.js')->assertOk();
});

test('it serves a package outside this repo that named its bundle its own way', function () {
    // A third-party package following the documented pattern declares
    // `Bundle::make('my-field.js')` — no wireStack prefix at all. servedByRoute()
    // strips nothing and hands the renderer the id `my-field`, so the lookup has
    // to be able to find the file under that bare name or the fallback 404s a
    // bundle sitting in the package's own dist.
    file_put_contents($this->dist.'/my-field.js', 'window.myField = 1;');
    Bundle::serve('my-package', $this->dist);

    $this->get('/my-package/assets/my-field.js')->assertOk();
});

test('the package prefix wins over the bare wire- prefix', function () {
    file_put_contents($this->dist.'/wire-both-panel.js', 'window.specific = 1;');
    file_put_contents($this->dist.'/wire-panel.js', 'window.generic = 1;');
    Bundle::serve('wire-both', $this->dist);

    // Order is load-bearing: a package's own bundle must not be shadowed by a
    // sibling package's file that happens to sit in the same directory.
    $file = $this->get('/wire-both/assets/panel.js')->baseResponse->getFile();

    expect(file_get_contents($file->getPathname()))->toBe('window.specific = 1;');
});

test('the route it registers is the one servedByRoute() addresses', function () {
    Bundle::serve('wire-paired', $this->dist);

    // The two halves of one mapping: servedByRoute() strips the prefix off a
    // built filename to get an id, serve() puts it back on to find the file.
    // A drift between them 404s a bundle that is sitting right there.
    $url = (Bundle::servedByRoute('wire-paired'))('wire-paired-panel.js', 'ignored');

    expect($url)->toContain('/wire-paired/assets/panel.js');
});
