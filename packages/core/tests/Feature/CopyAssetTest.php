<?php

declare(strict_types=1);

use Foundation\View\CopyButton;
use NyonCode\WireCore\WireCoreServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Guards for the delegated clipboard bundle.
 *
 * The copy affordance is one document listener rather than an Alpine component per
 * cell or entry (see copy.js): a copyable table cell shrank from 2042 bytes and 11
 * whitespace nodes to one `<button data-copy>`, and an infolist entry lost its own
 * `copied` flag and timeout the same way. What that trades away is
 * self-containment — the markup no longer carries its own behaviour — so these pin
 * the two ways the bundle could silently stop arriving.
 *
 * It lives in core because two packages ask for the same affordance, and core is
 * the lowest layer that can own it ({@see CopyButton}).
 */
test('the copy bundle is shipped inside the package', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-copy.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('data-copy');
});

test('the package serves the copy bundle without publishing or a build step', function () {
    $response = $this->get('/wire-core/assets/copy.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
});

test('the shipped bundle carries the whole copy surface', function () {
    // The click delegation, the clipboard write and the shared feedback pill.
    // Fails if the dist drifts from source (needs `npm run build:core-assets`).
    expect(file_get_contents(WireCoreServiceProvider::ASSETS_PATH.'/wire-core-copy.js'))
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
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/js/copy.js'
    );

    expect($source)->not->toMatch('/^\s*import\s/m')
        ->and($source)->not->toMatch('/^\s*export\s/m');
});

test('the copy source binds once per document, not once per Alpine tree', function () {
    $source = file_get_contents(
        dirname(WireCoreServiceProvider::ASSETS_PATH).'/resources/js/copy.js'
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
        ->toContain('window.wireCoreCopyInstalled')
        ->toContain("document.addEventListener('click', onClick)");
});
