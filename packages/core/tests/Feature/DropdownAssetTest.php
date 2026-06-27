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

test('the named asset route resolves', function () {
    expect(route('wire-core.asset', ['asset' => 'dropdown'], false))
        ->toBe('/wire-core/assets/dropdown.js');
});

test('unknown assets return 404', function () {
    $this->get('/wire-core/assets/does-not-exist.js')->assertNotFound();
});
