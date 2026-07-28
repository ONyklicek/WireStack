<?php

declare(strict_types=1);

use NyonCode\WireTable\WireTableServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the record-actions bundle is shipped inside the package', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-records.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireRecordActions');
});

test('the package serves the record-actions bundle without publishing or a build step', function () {
    $response = $this->get('/wire-table/assets/records.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireRecordActions');
});

test('the shipped bundle carries the delegated pointer and context-menu layer', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-records.js';

    // One controller on the <tbody> resolves the row from data-row-key and
    // ignores clicks landing on interactive elements inside the row. Fails if
    // the dist drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents($bundle))
        ->toContain('onPointer')
        ->toContain('onContextMenu')
        ->toContain('data-row-key')
        ->toContain('data-record-menu')
        ->toContain('openActionModal');
});

test('the shipped bundle carries the keyboard grid layer', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-records.js';

    // The roving tabindex and the active-row marker are Alpine bindings so they
    // survive Livewire morphs, the grid stays inert under an open dialog, and a
    // closed modal hands the focus back to the active row. Fails if the dist
    // drifts from source (needs `npm run build:table-assets`).
    expect(file_get_contents($bundle))
        ->toContain('rowTabindex')
        ->toContain('rowClass')
        ->toContain('dialogOpen')
        ->toContain('restoreFocus');
});

test('the shipped bundle bridges to the selection component and rejects stale views', function () {
    $bundle = WireTableServiceProvider::ASSETS_PATH.'/wire-table-records.js';

    // The anchor and the range gestures live in wireRecordSelection; this
    // bundle reaches them through the shared selection root and must refuse a
    // published view that predates that contract out loud instead of selecting
    // wrong ranges silently. Fails if the dist drifts from source (needs
    // `npm run build:table-assets`).
    expect(file_get_contents($bundle))
        ->toContain('data-selection-root')
        ->toContain('selectionVersion')
        ->toContain('vendor:publish')
        ->toContain('setAnchor');
});
