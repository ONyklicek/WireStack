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
        // The range vocabulary owned here: the one-shot anchor, the far-edge
        // fallback, the [anchor…active] block and the mod+A page select.
        ->toContain('anchorKey')
        ->toContain('anchorFor')
        ->toContain('selectRange')
        ->toContain('selectPage')
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

test('the selection source registers unconditionally so it survives a wire:navigate', function () {
    $source = file_get_contents(
        dirname(WireTableServiceProvider::ASSETS_PATH).'/resources/js/record-selection.js'
    );

    // `alpine:init` fires exactly once per document. Navigating to the first
    // page that has a table loads this bundle after that event, so a listener is
    // only ever the cold-load fallback: the factory has to be registered
    // straight away when Alpine is already running, or the table wrapper's
    // x-data blows up and takes search, filters, the bulk bar and the modal
    // hosts down with it.
    expect($source)
        ->not->toMatch("/addEventListener\('alpine:init',\s*\(\s*\)\s*=>/")
        ->toMatch("/if \(window\.Alpine\) \{\s*(?:\/\/[^\n]*\n\s*)*registerWireRecordSelection\(\);/")
        ->toMatch("/document\.addEventListener\('alpine:init', registerWireRecordSelection\);/")
        ->toContain('if (registered || ! window.Alpine) return;')
        // A function, NOT an arrow: Alpine binds a data factory's `this` to the
        // magic context that carries $wire, and the entangles need it.
        ->toContain('function wireRecordSelection(config = {}) {');
});

test('the shipped selection bundle carries the SPA-proof registration', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-selection.js';

    // Minified shape of the idiom above:
    //   window.Alpine?d():document.addEventListener("alpine:init",d)
    //   function d(){u||!window.Alpine||(u=!0, …)}
    // Fails if the dist drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents($bundle))
        ->toMatch('/window\.Alpine\?\w+\(\):document\.addEventListener\("alpine:init",\w+\)/')
        ->toMatch('/\w+\|\|!window\.Alpine\|\|\(\w+=!0,/');
});
