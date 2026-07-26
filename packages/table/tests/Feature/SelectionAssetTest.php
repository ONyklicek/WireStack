<?php

declare(strict_types=1);

use NyonCode\WireTable\WireTableServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the selection bundle is shipped inside the package', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-selection.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireRecordSelection');
});

test('the package serves the selection bundle without publishing or a build step', function () {
    $response = $this->get('/wire-table/assets/selection.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireRecordSelection');
});

test('the shipped bundle carries the whole selection surface', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-selection.js';

    // Checkboxes, both select-all toggles, the all-matching escalation, the
    // bulk-bar controls and the debounced commit all live in this factory.
    // Fails if the dist drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents($bundle))
        ->toContain('alpine:init')
        ->toContain('entangle')
        ->toContain('toggleAll')
        ->toContain('selectAllMatching')
        ->toContain('selectOnlyPage')
        ->toContain('deselectAll')
        ->toContain('queueCommit')
        // The morph-refreshed DOM reads (step: matching must not bake in).
        ->toContain('.dataset.matching')
        ->toContain('.dataset.pageKeys');
});

test('the raw selection source stays import-free for the inline fallback', function () {
    // When the compiled bundle is missing, the asset partial inlines this file
    // verbatim; an import statement would turn the fallback into a syntax
    // error inside a classic <script> tag.
    $source = file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH).'/resources/js/record-selection.js'
    );

    expect($source)->not->toMatch('/^\s*import\s/m')
        ->and($source)->not->toMatch('/^\s*export\s/m');
});
