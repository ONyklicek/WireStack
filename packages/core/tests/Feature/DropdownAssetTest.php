<?php

declare(strict_types=1);

use NyonCode\WireCore\WireCoreServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('the dropdown bundle is shipped inside the package', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))->toContain('wireDropdown');
});

test('the package serves the dropdown bundle without publishing or a build step', function () {
    $response = $this->get('/wire-core/assets/dropdown.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('javascript');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class)
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toContain('wireDropdown');
});

test('the shipped bundle keeps the panel pinned across Livewire morphs', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // A Livewire morph can detach the trigger or strip Floating UI's inline
    // left/top styles, dropping the teleported panel into the top-left corner.
    // The compiled bundle must skip positioning against a disconnected node and
    // re-pin via a MutationObserver. This also fails if the dist drifts from the
    // source because the asset was not recompiled.
    expect(file_get_contents($bundle))
        ->toContain('isConnected')
        ->toContain('MutationObserver');
});

test('the shipped bundle caps floating panels to the available viewport height', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // The shared $float primitive must size panels to the room left after
    // flip/shift (availableHeight) with internal scroll, so a tall panel — a
    // calendar, a long option list — never spills off a short viewport. Fails if
    // the dist drifts from source because the asset was not recompiled.
    expect(file_get_contents($bundle))
        ->toContain('availableHeight')
        ->toContain('maxHeight')
        ->toContain('overflowY');
});

test('the shipped bundle supports the sheet-on-mobile mode', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // Panels that opt into sheetOnMobile skip Floating UI below the breakpoint
    // (matchMedia) so their max-sm: bottom-sheet classes take over. Fails if the
    // dist drifts from source because the asset was not recompiled.
    expect(file_get_contents($bundle))
        ->toContain('matchMedia')
        ->toContain('sheetOnMobile');
});

test('the shipped bundle registers the sheet drag-to-dismiss directive', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // The grabber's x-sheet-dismiss directive lets a mobile sheet be swiped down
    // to close. Fails if the dist drifts from source (asset not recompiled).
    expect(file_get_contents($bundle))
        ->toContain('sheet-dismiss')
        ->toContain('touchmove');
});

test('the named asset route resolves', function () {
    expect(route('wire-core.asset', ['asset' => 'dropdown'], false))
        ->toBe('/wire-core/assets/dropdown.js');
});

test('unknown assets return 404', function () {
    $this->get('/wire-core/assets/does-not-exist.js')->assertNotFound();
});

test('the shipped bundle registers the sheet focus-trap directive', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // Mobile sheets trap focus like a dialog (aria-modal, Tab cycle, restore).
    // Fails if the dist drifts from source (asset not recompiled).
    expect(file_get_contents($bundle))
        ->toContain('focus-trap')
        ->toContain('aria-modal');
});

test('the shipped bundle registers the tabs and wizard Alpine data', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // Standalone <x-wire::tabs>/<x-wire::wizard> tags depend on these Alpine
    // data factories; fails if the dist drifts from source.
    expect(file_get_contents($bundle))
        ->toContain('wireTabs')
        ->toContain('wireWizard')
        ->toContain('registerStep');
});

test('the shipped bundle registers the editable-cell Alpine data', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // Inline-editable table cells (TextInput/Select/Toggle columns) depend on the
    // shared wireEditableCell factory; fails if the dist drifts from source.
    expect(file_get_contents($bundle))
        ->toContain('wireEditableCell')
        ->toContain('updateTableCell');
});

test('the shipped bundle registers the row context-menu Alpine data', function () {
    $bundle = WireCoreServiceProvider::ASSETS_PATH.'/wire-core-dropdown.js';

    // Table::rowContextMenu() renders x-data="wireContextMenu()"; fails if the
    // dist drifts from source (needs a rebuild via `npm run build:core-assets`).
    expect(file_get_contents($bundle))->toContain('wireContextMenu');
});
