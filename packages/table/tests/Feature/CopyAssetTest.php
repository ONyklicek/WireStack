<?php

declare(strict_types=1);

use NyonCode\WireTable\WireTableServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Guards for the delegated clipboard bundle, mirroring SelectionAssetTest.
 *
 * The copy affordance is one document listener rather than an Alpine component per
 * cell (see record-copy.js): a copyable cell shrank from 2042 bytes and 11
 * whitespace nodes to one `<button data-copy>`. What that trades away is
 * self-containment — the markup no longer carries its own behaviour — so these pin
 * the two ways the bundle could silently stop arriving.
 */
test('the copy bundle is shipped inside the package', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-copy.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('data-copy');
});

test('the package serves the copy bundle without publishing or a build step', function () {
    $response = $this->get('/wire-table/assets/copy.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
});

test('the shipped bundle carries the whole copy surface', function () {
    // The click delegation, the clipboard write and the shared feedback pill.
    // Fails if the dist drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents(WireTableServiceProvider::ASSETS_PATH.'/wire-table-copy.js'))
        ->toContain('[data-copy]')
        ->toContain('data-copy-feedback')
        ->toContain('data-copy-message')
        ->toContain('writeText')
        ->toContain('addEventListener("click"');
});

test('the raw copy source stays import-free for the inline fallback', function () {
    // When the compiled bundle is missing, copy-assets.blade.php inlines this file
    // verbatim; an import statement would turn the fallback into a syntax error
    // inside a classic <script> tag.
    $source = file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH).'/resources/js/record-copy.js'
    );

    expect($source)->not->toMatch('/^\s*import\s/m')
        ->and($source)->not->toMatch('/^\s*export\s/m');
});

test('the copy source binds once per document, not once per Alpine tree', function () {
    $source = file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH).'/resources/js/record-copy.js'
    );

    // Deliberately NOT an Alpine component: a document listener is installed when
    // the script runs, so it cannot miss an `alpine:init` that already fired on a
    // wire:navigate visit — the failure ADR 0024 documents for every other bundle.
    // Matched on the calls, not the words: the file's own comment explains why it
    // stays off `alpine:init`, and asserting the bare string would forbid saying so.
    expect($source)
        ->not->toMatch("/addEventListener\(\s*'alpine:init'/")
        ->not->toMatch('/Alpine\s*\.\s*data\s*\(/')
        // Guarded on `window`, so two inlined IIFE copies still bind one listener
        // and a click is never copied — or announced — twice.
        ->toContain('window.wireTableCopyInstalled')
        ->toContain("document.addEventListener('click', onClick)");
});
