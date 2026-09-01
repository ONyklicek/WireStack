<?php

declare(strict_types=1);

use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireTable\WireTableServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the fill-handle bundle is shipped inside the package', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-fill.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireFillHandle');
});

test('the package serves the fill-handle bundle without publishing or a build step', function () {
    $response = $this->get('/wire-table/assets/fill.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireFillHandle');
});

test('the shipped bundle registers the fill-handle Alpine data', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-fill.js';

    // Moved out of wire-core's dropdown bundle by ADR 0025 § step 10. Fails if the
    // dist drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents($bundle))
        ->toContain('alpine:init')
        ->toContain('wireFillHandle')
        // One request for the whole range, sent only on pointer release.
        ->toContain('fillTableCells')
        // Pointer capture is what keeps the drag alive once it leaves the handle,
        // and is why mouse, touch and pen need no separate code paths.
        ->toContain('setPointerCapture')
        // The preview class the drag paints on covered cells.
        ->toContain('wire-fill-target')
        // Auto-scroll runs on rAF, so holding still past the viewport edge keeps
        // scrolling instead of stalling on the last pointermove.
        ->toContain('requestAnimationFrame');
});

test('the entry registers on both the cold load and a wire:navigate', function () {
    $source = (string) file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH).'/resources/js/record-fill.js'
    );

    // `alpine:init` fires once per document. A bundle arriving on a wire:navigate
    // hop is arriving after it, so a listener alone registers nothing and every
    // `x-data="wireFillHandle()"` on the swapped-in page evaluates against an
    // empty registry — taking the whole data region with it.
    //
    // This is not a hypothetical. The extraction in ADR 0025 § step 10 wrote the
    // entry with the bare listener, because inside `wire-core-dropdown.js` the
    // registration was one line in a registrar that already had this shape, and
    // the line moved while the shape stayed behind. Nothing server-side saw it:
    // the script tag was delivered, `alpine:init` was in the bundle, and this
    // file's own "registers the fill-handle Alpine data" test passed. It took
    // verify-spa-navigate.mjs, which is the last gate before a commit.
    expect($source)
        ->not->toMatch("/addEventListener\('alpine:init',\s*\(\s*\)\s*=>/")
        ->toMatch("/if \(window\.Alpine\) \{\s*(?:\/\/[^\n]*\n\s*)*registerWireFillHandle\(\)/")
        ->toMatch("/document\.addEventListener\('alpine:init', registerWireFillHandle\)/")
        ->toContain('if (registered || ! window.Alpine) return');
});

test('the shipped bundle carries the SPA-proof registration', function () {
    // Minified shape of the idiom above, asserted on what actually ships:
    //   window.Alpine?P():document.addEventListener("alpine:init",P)
    //   P=()=>{M||!window.Alpine||(M=!0, …)}
    // Fails if the dist drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents(WireTableServiceProvider::ASSETS_PATH.'/wire-table-fill.js'))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toMatch('/\w+\|\|!window\.Alpine\|\|\(\w+=!0,/');
});

test('the two bundles keep both ends of the drag guard', function () {
    // The one contract the split created. wire-core's partial morph must not run
    // over a fill drag — skipping that guard is what made a targeted fill wipe the
    // cells it had just painted — and the two halves now live in separate IIFEs
    // that cannot import from each other. `wire-filling` on <body> is the seam:
    // the controller writes it on the same line it joins its own drag registry,
    // and core reads it. Either half alone is a silent data-loss bug, so both are
    // asserted here rather than in their own packages.
    expect(file_get_contents(WireTableServiceProvider::ASSETS_PATH.'/wire-table-fill.js'))
        ->toContain('classList.add("wire-filling")')
        ->toContain('classList.remove("wire-filling")');

    expect(file_get_contents(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js'))
        ->toContain('classList.contains("wire-filling")');
});

test('the shared bundle no longer carries the fill handle', function () {
    // The point of the split: a forms-only application ships wire-core's dropdown
    // bundle and must not pay for a gesture only a table can trigger. Re-adding the
    // import to dropdown.js is a one-line regression with no other symptom.
    expect(file_get_contents(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js'))
        ->not->toContain('wireFillHandle')
        ->not->toContain('fillTableCells')
        ->not->toContain('wire-fill-target');
});
